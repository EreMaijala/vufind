<?php

/**
 * Database service for payment transactions.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Type\PaymentStatus;
use VuFind\Db\Service\AbstractDbService;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Exception\RecordMissing as RecordMissingException;

/**
 * Database service for payment transactions.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class PaymentService extends AbstractDbService implements
    DbTableAwareInterface,
    PaymentServiceInterface
{
    use DbTableAwareTrait;

    /**
     * Create a Payment entity object.
     *
     * @return PaymentEntityInterface
     */
    public function createEntity(): PaymentEntityInterface
    {
        $payment = $this->getDbTable('Transaction')->createRow();
        $payment->created = date('Y-m-d H:i:s');
        $payment->complete = 0;
        $payment->status = 'started';
        return $payment;
    }

    /**
     * Retrieve a paymet object.
     *
     * @param int $id Numeric ID for existing payment.
     *
     * @return PaymentEntityInterface
     * @throws RecordMissingException
     */
    public function getPaymentById(int $id): PaymentEntityInterface
    {
        $result = $this->getDbTable('Payment')->select(['id' => $id])->current();
        if (empty($result)) {
            throw new RecordMissingException("Cannot load payment $id");
        }
        return $result;
    }

    /**
     * Get fines associated with a payment
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return PaymentFeeEntityInterface[]
     */
    public function getFines(PaymentEntityInterface $payment): array
    {
        $feeTable = $this->getDbTable('Fee');
        return iterator_to_array($feeTable->select(['payment_id' => $payment->getId()]));
    }

    /**
     * Get IDs from fines associated with a payment
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return array
     */
    public function getFineIds(PaymentEntityInterface $payment): array
    {
        $fineIds = [];
        foreach ($this->getFines($payment) as $fee) {
            if (!empty($fee['fine_id'])) {
                $fineIds[] = $fee['fine_id'];
            }
        }
        return $fineIds;
    }

    /**
     * Get last paid payment for a patron
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return ?PaymentEntityInterface
     */
    public function getLastPaidPaymentForPatron(string $catUsername): ?PaymentEntityInterface
    {
        $statuses = [
            PaymentStatus::Complete->value,
            PaymentStatus::Paid->value,
            PaymentStatus::RegistrationFailed->value,
            PaymentStatus::RegistrationExpired->value,
            PaymentStatus::RegistrationResolved->value,
            PaymentStatus::FinesUpdated->value,
        ];

        $callback = function (\Laminas\Db\Sql\Select $select) use (
            $catUsername,
            $statuses
        ) {
            $select->where->equalTo('cat_username', $catUsername);
            $select->where('complete in (' . implode(',', $statuses) . ')');
            $select->order('paid desc');
        };

        return $this->getDbTable('Payment')->select($callback)->current();
    }

    /**
     * Check if payment is in progress for the patron.
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return bool
     */
    public function isPaymentInProgressForPatron(string $catUsername): bool
    {
        $statuses = [
            PaymentStatus::Paid->value,
            PaymentStatus::RegistrationFailed->value,
            PaymentStatus::RegistrationExpired->value,
            PaymentStatus::FinesUpdated->value,
        ];

        $callback = function ($select) use ($catUsername, $statuses) {
            $select->where->equalTo('cat_username', $catUsername);
            $select->where('complete in (' . implode(',', $statuses) . ')');
        };

        return $this->getDbTable('Payment')->select($callback)->count() ? true : false;
    }

    /**
     * Get payment by identifier.
     *
     * @param string $paymentIdentifier Payment identifier
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaymentByIdentifier(string $paymentIdentifier): ?PaymentEntityInterface
    {
        return $this->getDbTable('Payment')->select(['payment_id' => $paymentIdentifier])->current();
    }

    /**
     * Check if a payment is started for the patron, but not progressed further.
     *
     * @param string $catUsername            Patron's catalog username
     * @param int    $paymentMaxDuration Max duration for a payment in minutes
     *
     * @return ?PaymentEntityInterface
     */
    public function getStartedPaymentForPatron(
        string $catUsername,
        int $paymentMaxDuration
    ): ?PaymentEntityInterface {
        $callback = function ($select) use ($catUsername, $paymentMaxDuration) {
            $select->where->equalTo('cat_username', $catUsername);
            $select->where->equalTo('complete', PaymentStatus::InProgress->value);
            $select->where->lessThan(
                    'created',
                    date('Y-m-d H:i:s', time() + $paymentMaxDuration * 60)
                );
        };

        return $this->getDbTable('Payment')->select($callback)->current();
    }

    /**
     * Get paid payments whose registration failed.
     *
     * @param int $minimumPaidAge How old a paid payment must be (in seconds) for it to be considered failed
     *
     * @return PaymentEntityInterface[]
     */
    public function getFailedPayments(int $minimumPaidAge = 120): array
    {
        $callback = function ($select) use ($minimumPaidAge) {
            $select->where->nest
                ->equalTo('complete', PaymentStatus::RegistrationFailed->value)
                ->greaterThan('paid', '2000-01-01 00:00:00')
                ->unnest
                ->or->nest
                ->equalTo('complete', PaymentStatus::Paid->value)
                ->greaterThan('paid', '2000-01-01 00:00:00')
                ->lessThan(
                    'paid',
                    date('Y-m-d H:i:s', time() - $minimumPaidAge)
                );

            $select->order('user_id');
        };

        return iterator_to_array($this->getDbTable('Payment')->select($callback));
    }

    /**
     * Get unresolved payments for reporting.
     *
     * @param int $interval Minimum number of hours since last report was sent.
     *
     * @return PaymentEntityInterface[] payments
     */
    public function getUnresolvedPaymentsToReport(int $interval): array
    {
        $callback = function ($select) use (
            $interval
        ) {
            $select->where->in(
                'complete',
                [
                    PaymentStatus::FinesUpdated->value,
                    PaymentStatus::RegistrationExpired->value,
                ]
            );
            $select->where->greaterThan('paid', '2000-01-01 00:00:00');
            $select->where->lessThan('reported', date('Y-m-d H:i:s', time() - $interval * 3600));
            $select->order('user_id');
        };

        $items = [];
        foreach ($this->getDbTable('Payment')->select($callback) as $t) {
            $items[] = $t;
        }
        return $items;
    }
}
