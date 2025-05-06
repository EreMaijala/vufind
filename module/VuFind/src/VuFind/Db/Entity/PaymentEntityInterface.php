<?php

/**
 * Interface for representing a payment transaction.
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
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;
use VuFind\Db\Type\PaymentStatus;

/**
 * Interface for representing a payment transaction.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface PaymentEntityInterface extends EntityInterface
{
    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Payment Identifier setter
     *
     * @param ?string $paymentIdentifier Payment Identifier.
     *
     * @return static
     */
    public function setPaymentIdentifier(?string $paymentIdentifier): static;

    /**
     * Payment Identifier getter
     *
     * @return ?string
     */
    public function getPaymentIdentifier(): ?string;

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static;

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface;

    /**
     * Get user id
     *
     * @return ?int
     */
    public function getUserId(): ?int;

    /**
     * Source setter
     *
     * @param string $source Source
     *
     * @return static
     */
    public function setSource(string $source): static;

    /**
     * Source getter
     *
     * @return string
     */
    public function getSource(): string;

    /**
     * Amount setter
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setAmount(int $amount): static;

    /**
     * Amount getter
     *
     * @return int
     */
    public function getAmount(): int;

    /**
     * Currency setter
     *
     * @param string $currency Currency.
     *
     * @return static
     */
    public function setCurrency(string $currency): static;

    /**
     * Currency getter
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Service fee setter
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setServiceFee(int $amount): static;

    /**
     * Service fee getter
     *
     * @return int
     */
    public function getServiceFee(): int;

    /**
     * Created setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): Datetime;

    /**
     * Paid date setter
     *
     * @param ?DateTime $dateTime Paid date
     *
     * @return static
     */
    public function setPaidDate(?DateTime $dateTime): static;

    /**
     * Paid date getter
     *
     * @return DateTime
     */
    public function getPaidDate(): ?Datetime;

    /**
     * Registration started setter
     *
     * @param ?DateTime $dateTime Registration start date
     *
     * @return static
     */
    public function setRegistrationStartDate(?DateTime $dateTime): static;

    /**
     * Registration started getter
     *
     * @return ?DateTime
     */
    public function getRegistrationStartDate(): ?Datetime;

    /**
     * Registration date setter
     *
     * @param ?DateTime $dateTime Registration date
     *
     * @return static
     */
    public function setRegistrationDate(?DateTime $dateTime): static;

    /**
     * Registration date getter
     *
     * @return ?DateTime
     */
    public function getRegistrationDate(): ?Datetime;

    /**
     * Status setter
     *
     * @param PaymentStatus $status Status
     *
     * @return static
     */
    public function setStatus(PaymentStatus $status): static;

    /**
     * Status getter
     *
     * @return PaymentStatus
     */
    public function getStatus(): PaymentStatus;

    /**
     * Status message setter
     *
     * @param string $message Status message
     *
     * @return static
     */
    public function setStatusMessage(string $message): static;

    /**
     * Status message getter
     *
     * @return string
     */
    public function getStatusMessage(): string;

    /**
     * Catalog username setter
     *
     * @param string $catUsername Catalog username
     *
     * @return static
     */
    public function setCatUsername(string $catUsername): static;

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string;

    /**
     * Check if the payment is in progress
     *
     * @return bool
     */
    public function isInProgress(): bool;

    /**
     * Check if the payment is registered (fees marked paid in the ILS)
     *
     * @return bool
     */
    public function isRegistered(): bool;

    /**
     * Set payment canceled
     *
     * @return void
     */
    public function setCanceled(): static;

    /**
     * Check if the payment is paid and needs registration with the ILS
     *
     * @return bool
     */
    public function needsRegistration(): bool;

    /**
     * Check if registration is in progress (i.e. started within 120 seconds)
     *
     * @return bool
     */
    public function isRegistrationInProgress(): bool;

    /**
     * Set payment paid
     *
     * @return static
     */
    public function setPaid(): static;

    /**
     * Set payment registered
     *
     * @return static
     */
    public function setRegistered(): static;

    /**
     * Set payment status to "registration failed"
     *
     * @param string $msg Message
     *
     * @return static
     */
    public function setRegistrationFailed(string $msg): static;

    /**
     * Set registration start timestamp
     *
     * @return static
     */
    public function setRegistrationStarted(): static;

    /**
     * Set payment status to "registration expired"
     *
     * @return static
     */
    public function setExpired(): static;

    /**
     * Set payment reported date
     *
     * @return static
     */
    public function setReported(): static;

    /**
     * Set payment status to "fines updated"
     *
     * @return static
     */
    public function setFinesUpdated(): static;
}
