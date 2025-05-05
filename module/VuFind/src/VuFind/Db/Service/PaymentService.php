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

use DateTime;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Feature\DateTimeTrait;
use VuFind\Db\Type\PaymentStatus;

/**
 * Database service for payment transactions.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class PaymentService extends AbstractDbService implements PaymentServiceInterface
{
    use DateTimeTrait;

    /**
     * Create a Payment entity object.
     *
     * @return PaymentEntityInterface
     */
    public function createEntity(): PaymentEntityInterface
    {
        $class = $this->getEntityClass(PaymentEntityInterface::class);
        $entity = new $class();
        $entity->setCreated(new DateTime());
        $entity->setStatus(PaymentStatus::InProgress);
        $entity->setStatusMessage('');
        return $entity;
    }

    /**
     * Retrieve a payment object.
     *
     * @param int $id Numeric ID for existing payment.
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaymentById(int $id): ?PaymentEntityInterface
    {
        $dql = 'SELECT p '
                . 'FROM ' . $this->getEntityClass(PaymentEntityInterface::class) . ' p '
                . 'WHERE p.id = :id';
        $parameters = compact('id');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getOneOrNullResult();
    }

    /**
     * Get payment by local identifier.
     *
     * @param string $localIdentifier Payment identifier
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaymentByLocalIdentifier(string $localIdentifier): ?PaymentEntityInterface
    {
        $dql = 'SELECT p '
                . 'FROM ' . $this->getEntityClass(PaymentEntityInterface::class) . ' p '
                . 'WHERE p.localIdentifier = :localIdentifier';
        $parameters = compact('localIdentifier');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getOneOrNullResult();
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

        $dql = 'SELECT p FROM ' . $this->getEntityClass(PaymentEntityInterface::class) . ' p'
            . ' WHERE p.catUsername = :catUsername AND p.status IN (' . implode(',', $statuses) . ')'
            . ' ORDER BY p.paid DESC';
        $parameters = compact('catUsername');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setMaxResults(1);
        return $query->getOneOrNullResult();
    }

    /**
     * Get latest paid payment that requires registration for the patron.
     *
     * @param string $catUsername Patron's catalog username
     *
     * @return ?PaymentEntityInterface
     */
    public function getPaidPaymentInProgressForPatron(string $catUsername): ?PaymentEntityInterface
    {
        $statuses = [
            PaymentStatus::Paid->value,
            PaymentStatus::RegistrationFailed->value,
            PaymentStatus::RegistrationExpired->value,
            PaymentStatus::FinesUpdated->value,
        ];

        $dql = 'SELECT p FROM ' . $this->getEntityClass(PaymentEntityInterface::class) . ' p'
            . ' WHERE p.catUsername = :catUsername AND p.status IN (' . implode(',', $statuses) . ')'
            . ' ORDER BY p.created DESC';
        $parameters = compact('catUsername');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setMaxResults(1);
        return $query->getOneOrNullResult();
    }

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
    ): ?PaymentEntityInterface {
        $dql = 'SELECT p FROM ' . $this->getEntityClass(PaymentEntityInterface::class) . ' p'
            . ' WHERE p.catUsername = :catUsername'
            . ' AND p.status = :status'
            . ' AND p.created > :createdLimit'
            . ' ORDER BY p.created DESC';
        $parameters = [
            'catUsername' => $catUsername,
            'status' => PaymentStatus::InProgress->value,
            'createdLimit' => date('Y-m-d H:i:s', time() - $paymentMaxDuration * 60),
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setMaxResults(1);
        return $query->getOneOrNullResult();
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
        $dql = <<<DQL
            SELECT p FROM {$this->getEntityClass(PaymentEntityInterface::class)} p
              WHERE p.paid > {$this->getUnassignedDefaultDateTime()}
                AND (
                  p.status = {PaymentStatus::RegistrationFailed->value}
                  OR
                  (
                    p.status = {PaymentStatus::Paid->value}
                    AND
                    p.paid < :paidLimit
                  )
                )
              ORDER BY p.created
            DQL;
        $parameters = [
            'paidLimit' => date('Y-m-d H:i:s', time() - $minimumPaidAge),
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Get unresolved payments for reporting.
     *
     * @param int $interval Minimum number of minutes since last report was sent.
     *
     * @return PaymentEntityInterface[] payments
     */
    public function getUnresolvedPaymentsToReport(int $interval): array
    {
        $dql = <<<DQL
            SELECT p FROM {$this->getEntityClass(PaymentEntityInterface::class)} p
              WHERE p.status IN ({PaymentStatus::FinesUpdated->value}, {PaymentStatus::RegistrationExpired->value})
                AND p.paid > {$this->getUnassignedDefaultDateTime()}
                AND p.reported < :reportedLimit
              ORDER BY p.created
            DQL;
        $parameters = [
            'reportedLimit' => date('Y-m-d H:i:s', time() - $interval * 60),
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Refresh an entity from the database.
     *
     * @param PaymentEntityInterface $entity Entity to refresh.
     *
     * @return void
     */
    public function refreshEntity(PaymentEntityInterface $entity): void
    {
        $this->entityManager->refresh($entity);
    }
}
