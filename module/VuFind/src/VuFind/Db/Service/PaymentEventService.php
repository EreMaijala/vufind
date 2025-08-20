<?php

/**
 * Database service for payment_event table.
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

/**
 * Database service for payment_event table.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class PaymentEventService extends AbstractDbService implements PaymentEventServiceInterface
{
    /**
     * Create a payment event entity object.
     *
     * @return PaymentEventEntityInterface
     */
    public function createEntity(): PaymentEventEntityInterface
    {
        return $this->entityPluginManager->get(PaymentEventEntityInterface::class);
    }

    /**
     * Add an event for a payment.
     *
     * @param PaymentEntityInterface $payment Payment
     * @param string                 $message Status message
     * @param array                  $data    Additional data
     *
     * @return void
     */
    public function addEvent(PaymentEntityInterface $payment, string $message, array $data = []): void
    {
        $event = $this->createEntity();
        $event->setPayment($payment)
            ->setDate(new DateTime())
            ->setServerIp($_SERVER['SERVER_ADDR'] ?? '')
            ->setServerName($_SERVER['SERVER_NAME'] ?? '')
            ->setRequestUri($_SERVER['REQUEST_URI'] ?? '')
            ->setMessage($message)
            ->setData($data ? json_encode($data) : null);
        $this->persistEntity($event);
    }

    /**
     * Get events for a payment.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return PaymentEventEntityInterface[]
     */
    public function getEventsForPayment(PaymentEntityInterface $payment): array
    {
        $dql = 'SELECT pe FROM ' . PaymentEventEntityInterface::class
            . ' pe WHERE pe.payment = :payment ORDER BY pe.date DESC, pe.id DESC';
        $parameters = compact('payment');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }
}
