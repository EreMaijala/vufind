<?php

/**
 * Entity model for audit_event table
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
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

/**
 * Entity model for audit_event table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
#[ORM\Table(name: 'audit_event')]
#[ORM\Entity]
class AuditEvent implements AuditEventEntityInterface
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
     * Date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'date', type: 'datetime', nullable: false)]
    protected $date;

    /**
     * Event type.
     *
     * @var string
     */
    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: false)]
    protected $type;

    /**
     * Event subtype.
     *
     * @var string
     */
    #[ORM\Column(name: 'subtype', type: 'string', length: 50, nullable: false)]
    protected $subtype;

    /**
     * User.
     *
     * @var User
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: \VuFind\Db\Entity\User::class)]
    protected $user;

    /**
     * Username.
     *
     * @var string
     */
    #[ORM\Column(name: 'username', type: 'string', length: 255, nullable: true)]
    protected $username;

    /**
     * Client IP address.
     *
     * @var string
     */
    #[ORM\Column(name: 'client_ip', type: 'string', length: 255, nullable: true)]
    protected $clientIp;

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
     * Log message.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'message', type: 'string', length: 255, nullable: true)]
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
        $this->date = new DateTime();
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
     * Get type.
     *
     * @return ?AuditEventType
     */
    public function getType(): ?AuditEventType
    {
        return AuditEventType::tryFrom($this->type);
    }

    /**
     * Set type.
     *
     * @param AuditEventType $type Type
     *
     * @return static
     */
    public function setType(AuditEventType $type): static
    {
        $this->type = $type->value;
        return $this;
    }

    /**
     * Get subtype.
     *
     * @return ?AuditEventSubtype
     */
    public function getSubtype(): ?AuditEventSubtype
    {
        return AuditEventSubtype::tryFrom($this->subtype);
    }

    /**
     * Set subtype.
     *
     * @param AuditEventSubtype $subtype Subtype
     *
     * @return static
     */
    public function setSubtype(AuditEventSubtype $subtype): static
    {
        $this->subtype = $subtype->value;
        return $this;
    }

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user;
    }

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static
    {
        $this->user = $user;
        $this->username = $user?->getUsername();
        return $this;
    }

    /**
     * Get client IP address.
     *
     * @return ?string
     */
    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * Set client IP address.
     *
     * @param ?string $clientIp Client IP address
     *
     * @return static
     */
    public function setClientIp(?string $clientIp): static
    {
        $this->clientIp = $clientIp;
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
