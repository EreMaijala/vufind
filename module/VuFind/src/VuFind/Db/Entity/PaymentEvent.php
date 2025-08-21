<?php

/**
 * Entity model for payment event table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @link     http://vufind.org   Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Entity model for payment event table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
#[ORM\Table(name: 'payment_event')]
#[ORM\Index(name: 'payment_event_payment_id_idx', columns: ['payment_id'])]
#[ORM\Entity]
class PaymentEvent implements PaymentEventEntityInterface
{
    use DateTimeTrait;

    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * Payment.
     *
     * @var Payment
     */
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: PaymentEntityInterface::class)]
    protected Payment $payment;

    /**
     * Date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'date', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $date;

    /**
     * Server IP address.
     *
     * @var string
     */
    #[ORM\Column(name: 'server_ip', type: 'string', length: 255, nullable: true)]
    protected string $serverIp;

    /**
     * Server name.
     *
     * @var string
     */
    #[ORM\Column(name: 'server_name', type: 'string', length: 255, nullable: true)]
    protected string $serverName;

    /**
     * Request URI.
     *
     * @var string
     */
    #[ORM\Column(name: 'request_uri', type: 'string', length: 1024, nullable: true)]
    protected string $requestUri;

    /**
     * Log message.
     *
     * @var string
     */
    #[ORM\Column(name: 'message', type: 'string', length: 255, nullable: false)]
    protected string $message;

    /**
     * Additional data (JSON).
     *
     * @var ?string
     */
    #[ORM\Column(name: 'data', type: 'text', length: 16777215, nullable: true)]
    protected ?string $data;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->date = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get payment.
     *
     * @return PaymentEntityInterface
     */
    public function getPayment(): PaymentEntityInterface
    {
        return $this->payment;
    }

    /**
     * Set payment.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return static
     */
    public function setPayment(PaymentEntityInterface $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * Get date.
     *
     * @return DateTime
     */
    public function getDate(): Datetime
    {
        return $this->date;
    }

    /**
     * Set date.
     *
     * @param DateTime $dateTime Date
     *
     * @return static
     */
    public function setDate(DateTime $dateTime): static
    {
        $this->date = $dateTime;
        return $this;
    }

    /**
     * Get server IP address.
     *
     * @return ?string
     */
    public function getServerIp(): ?string
    {
        return $this->serverIp;
    }

    /**
     * Set server IP address.
     *
     * @param ?string $serverIp Server IP address
     *
     * @return static
     */
    public function setServerIp(?string $serverIp): static
    {
        $this->serverIp = $serverIp;
        return $this;
    }

    /**
     * Get server name.
     *
     * @return ?string
     */
    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    /**
     * Set server name.
     *
     * @param ?string $serverName Server name
     *
     * @return static
     */
    public function setServerName(?string $serverName): static
    {
        $this->serverName = $serverName;
        return $this;
    }

    /**
     * Get request URI.
     *
     * @return ?string
     */
    public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    /**
     * Set request URI.
     *
     * @param ?string $requestUri Request URI
     *
     * @return static
     */
    public function setRequestUri(?string $requestUri): static
    {
        $this->requestUri = $requestUri;
        return $this;
    }

    /**
     * Get message.
     *
     * @return ?string
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Set message.
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get additional data.
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * Set additional data.
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static
    {
        $this->data = $data;
        return $this;
    }
}
