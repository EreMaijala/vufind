<?php

/**
 * Entity model interface for payment_event table
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for payment_event table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface PaymentEventEntityInterface extends EntityInterface
{
    /**
     * Get payment.
     *
     * @return PaymentEntityInterface
     */
    public function getPayment(): PaymentEntityInterface;

    /**
     * Set payment.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return static
     */
    public function setPayment(PaymentEntityInterface $payment): static;

    /**
     * Get date.
     *
     * @return DateTime
     */
    public function getDate(): Datetime;

    /**
     * Set date.
     *
     * @param DateTime $dateTime Date
     *
     * @return static
     */
    public function setDate(DateTime $dateTime): static;

    /**
     * Get server IP address.
     *
     * @return ?string
     */
    public function getServerIp(): ?string;

    /**
     * Set server IP address.
     *
     * @param ?string $serverIp Server IP address
     *
     * @return static
     */
    public function setServerIp(?string $serverIp): static;

    /**
     * Get server name.
     *
     * @return ?string
     */
    public function getServerName(): ?string;

    /**
     * Set server name.
     *
     * @param ?string $serverName Server name
     *
     * @return static
     */
    public function setServerName(?string $serverName): static;

    /**
     * Get request URI.
     *
     * @return ?string
     */
    public function getRequestUri(): ?string;

    /**
     * Set request URI.
     *
     * @param ?string $requestUri Request URI
     *
     * @return static
     */
    public function setRequestUri(?string $requestUri): static;

    /**
     * Get message.
     *
     * @return ?string
     */
    public function getMessage(): ?string;

    /**
     * Set message.
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static;

    /**
     * Get additional data.
     *
     * @return ?string
     */
    public function getData(): ?string;

    /**
     * Set additional data.
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static;
}
