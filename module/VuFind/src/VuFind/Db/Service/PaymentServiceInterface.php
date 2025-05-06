<?php

/**
 * Database service interface for payment transactions.
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
use VuFind\Db\Entity\PaymentFeeEntityInterface;

/**
 * Database service interface for payment transactions.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface PaymentServiceInterface extends DbServiceInterface
{
    /**
     * Create a Payment entity object.
     *
     * @return PaymentEntityInterface
     */
    public function createEntity(): PaymentEntityInterface;

    /**
     * Retrieve a payment object.
     *
     * @param int $id Numeric ID for existing payment.
     *
     * @return PaymentEntityInterface
     * @throws RecordMissingException
     */
    public function getPaymentById(int $id): PaymentEntityInterface;

    /**
     * Get fines associated with a payment.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return PaymentFeeEntityInterface[]
     */
    public function getFines(PaymentEntityInterface $payment): array;

    /**
     * Get IDs from fines associated with a payment
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return array
     */
    public function getFineIds(PaymentEntityInterface $payment): array;

    /**
     * Get last paid payment for a patron
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return ?PaymentEntityInterface
     */
    public function getLastPaidPaymentForPatron(string $catUsername): ?PaymentEntityInterface;

    /**
     * Check if payment is in progress for the patron.
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return bool
     */
    public function isPaymentInProgressForPatron(string $catUsername): bool;

    /**
     * Get payment by identifier.
     *
     * @param string $paymentIdentifier Payment identifier
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaymentByIdentifier(string $paymentIdentifier): ?PaymentEntityInterface;

    /**
     * Check if a payment is started for the patron, but not progressed further.
     *
     * @param string $catUsername        Patron's catalog username
     * @param int    $paymentMaxDuration Max duration for a payment in minutes
     *
     * @return ?PaymentEntityInterface
     */
    public function getStartedPaymentForPatron(
        string $catUsername,
        int $paymentMaxDuration
    ): ?PaymentEntityInterface;

    /**
     * Get paid payments whose registration failed.
     *
     * @param int $minimumPaidAge How old a paid payment must be (in seconds) for it to be considered failed
     *
     * @return PaymentEntityInterface[]
     */
    public function getFailedPayments(int $minimumPaidAge = 120): array;

    /**
     * Get unresolved payments for reporting.
     *
     * @param int $interval Minimum number of hours since last report was sent.
     *
     * @return PaymentEntityInterface[]
     */
    public function getUnresolvedPaymentsToReport(int $interval): array;
}
