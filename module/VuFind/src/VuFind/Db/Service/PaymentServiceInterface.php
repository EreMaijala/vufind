<?php

/**
 * Database service for payment table.
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

use VuFind\Db\Entity\PaymentEntityInterface;

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
     * @return ?PaymentEntityInterface
     */
    public function getPaymentById(int $id): ?PaymentEntityInterface;

    /**
     * Get payment by local identifier.
     *
     * @param string $localIdentifier Payment identifier
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaymentByLocalIdentifier(string $localIdentifier): ?PaymentEntityInterface;

    /**
     * Get last paid payment for a patron
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return ?PaymentEntityInterface
     */
    public function getLastPaidPaymentForPatron(string $catUsername): ?PaymentEntityInterface;

    /**
     * Get latest paid payment that requires registration for the patron.
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaidPaymentInProgressForPatron(string $catUsername): ?PaymentEntityInterface;

    /**
     * Get any payment that has been started for the patron, but not progressed further.
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
     * @param int $interval Minimum number of minutes since last report was sent.
     *
     * @return PaymentEntityInterface[]
     */
    public function getUnresolvedPaymentsToReport(int $interval): array;

    /**
     * Refresh an entity from the database.
     *
     * @param PaymentEntityInterface $entity Entity to refresh.
     *
     * @return void
     */
    public function refreshEntity(PaymentEntityInterface $entity): void;
}
