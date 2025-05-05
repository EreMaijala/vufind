<?php

/**
 * Database service for payment transaction event log.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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

use DateTime;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\PaymentEventEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Db\Table\PaymentEventLog;

/**
 * Database service for payment transaction event log.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class PaymentEventLogService extends AbstractDbService implements
    DbTableAwareInterface,
    PaymentEventLogServiceInterface
{
    use DbTableAwareTrait;

    /**
     * Create a payment event entity object.
     *
     * @return PaymentEventEntityInterface
     */
    public function createEntity(): PaymentEventEntityInterface
    {
        return $this->getDbTable(PaymentEventLog::class)->createRow();
    }

    /**
     * Add an event for a payment
     *
     * @param PaymentEntityInterface $payment Payment
     * @param string                 $status  Status message
     * @param array                  $data    Additional data
     *
     * @return void
     */
    public function addEvent(PaymentEntityInterface $payment, string $status, array $data = []): void
    {
        $event = $this->createEntity();
        $event->setPayment($payment)
            ->setDate(new DateTime())
            ->setServerIp($_SERVER['SERVER_ADDR'] ?? '')
            ->setServerName($_SERVER['SERVER_NAME'] ?? '')
            ->setRequestUri($_SERVER['REQUEST_URI'] ?? '')
            ->setMessage($status)
            ->setData($data ? json_encode($data) : null);
        $this->persistEntity($event);
    }
}
