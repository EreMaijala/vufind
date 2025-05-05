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
    protected $id;

    /**
     * Payment.
     *
     * @var Payment
     */
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: \VuFind\Db\Entity\Payment::class)]
    protected $payment;

    /**
     * Date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'date', type: 'datetime', nullable: false)]
    protected $date;

    /**
     * Server IP address.
     *
     * @var string
     */
    #[ORM\Column(name: 'server_ip', type: 'string', length: 255, nullable: true)]
    protected $serverIp;

    /**
     * Server name.
     *
     * @var string
     */
    #[ORM\Column(name: 'server_name', type: 'string', length: 255, nullable: true)]
    protected $serverName;

    /**
     * Request URI.
     *
     * @var string
     */
    #[ORM\Column(name: 'request_uri', type: 'string', length: 1024, nullable: true)]
    protected $requestUri;

    /**
     * Log message.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'message', type: 'string', length: 255, nullable: false)]
    protected $message;

    /**
     * Additional data (JSON).
     *
     * @var ?string
     */
    #[ORM\Column(name: 'data', type: 'text', length: 16777215, nullable: true)]
    protected $data;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->date = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Payment setter
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
     * Payment getter
     *
     * @return PaymentEntityInterface
     */
    public function getPayment(): PaymentEntityInterface
    {
        return $this->payment;
    }

    /**
     * Date setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setDate(DateTime $dateTime): static
    {
        $this->date = $dateTime;
        return $this;
    }

    /**
     * Date getter
     *
     * @return DateTime
     */
    public function getDate(): Datetime
    {
        return $this->date;
    }

    /**
     * Server IP address setter
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
     * Server IP address getter
     *
     * @return ?string
     */
    public function getServerIp(): ?string
    {
        return $this->serverIp;
    }

    /**
     * Server name setter
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
     * Server name getter
     *
     * @return ?string
     */
    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    /**
     * Request URI setter
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
     * Request URI getter
     *
     * @return ?string
     */
    public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    /**
     * Message setter
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
     * Message getter
     *
     * @return ?string
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Additional Data setter
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

    /**
     * Additional data getter
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->data;
    }
}
