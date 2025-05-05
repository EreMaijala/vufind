<?php

/**
 * Entity model for payment table
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
use VuFind\Db\Type\PaymentStatus;

use function in_array;

/**
 * Entity model for payment table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
#[ORM\Table(name: 'payment')]
#[ORM\Entity]
class Payment implements PaymentEntityInterface
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
     * Local identifier.
     *
     * @var string
     */
    #[ORM\Column(name: 'local_identifier', type: 'string', length: 255, nullable: false)]
    protected $localIdentifier;

    /**
     * Remote identifier.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'remote_identifier', type: 'string', length: 255, nullable: true)]
    protected $remoteIdentifier = null;

    /**
     * User.
     *
     * @var User
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \VuFind\Db\Entity\User::class)]
    protected $user;

    /**
     * Source ILS.
     *
     * @var string
     */
    #[ORM\Column(name: 'source_ils', type: 'string', length: 255, nullable: false)]
    protected $sourceIls;

    /**
     * Catalog username.
     *
     * @var string
     */
    #[ORM\Column(name: 'cat_username', type: 'string', length: 50, nullable: false)]
    protected $catUsername;

    /**
     * Amount.
     *
     * @var int
     */
    #[ORM\Column(name: 'amount', type: 'integer', nullable: false)]
    protected $amount;

    /**
     * Currency.
     *
     * @var string
     */
    #[ORM\Column(name: 'currency', type: 'string', length: 3, nullable: false)]
    protected $currency;

    /**
     * Service fee.
     *
     * @var int
     */
    #[ORM\Column(name: 'service_fee', type: 'integer', nullable: false)]
    protected $serviceFee;

    /**
     * Created date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected $created;

    /**
     * Paid date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'paid', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected $paid;

    /**
     * Registration started date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'registration_started', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected $registrationStarted;

    /**
     * Registered date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'registered', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected $registered;

    /**
     * Status.
     *
     * @var int
     */
    #[ORM\Column(name: 'status', type: 'integer', nullable: false)]
    protected $status;

    /**
     * Status message.
     *
     * @var string
     */
    #[ORM\Column(name: 'status_message', type: 'string', length: 255, nullable: false)]
    protected $statusMessage;

    /**
     * Reported date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'reported', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected $reported;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $noDate = $this->getUnassignedDefaultDateTime();
        $this->created = $noDate;
        $this->paid = $noDate;
        $this->registrationStarted = $noDate;
        $this->registered = $noDate;
        $this->reported = $noDate;
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get Local payment identifier.
     *
     * @return string
     */
    public function getLocalIdentifier(): string
    {
        return $this->localIdentifier;
    }

    /**
     * Set local payment identifier.
     *
     * @param string $localIdentifier Local identifier
     *
     * @return static
     */
    public function setLocalIdentifier(string $localIdentifier): static
    {
        $this->localIdentifier = $localIdentifier;
        return $this;
    }

    /**
     * Get remote payment identifier.
     *
     * @return ?string
     */
    public function getRemoteIdentifier(): ?string
    {
        return $this->remoteIdentifier;
    }

    /**
     * Set remote payment identifier.
     *
     * @param ?string $remoteIdentifier Remote identifier
     *
     * @return static
     */
    public function setRemoteIdentifier(?string $remoteIdentifier): static
    {
        $this->remoteIdentifier = $remoteIdentifier;
        return $this;
    }

    /**
     * Get user (only null if entity has not been populated yet).
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
     * @param UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get source ILS.
     *
     * @return string
     */
    public function getSourceIls(): string
    {
        return $this->sourceIls;
    }

    /**
     * Set source ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return static
     */
    public function setSourceIls(string $sourceIls): static
    {
        $this->sourceIls = $sourceIls;
        return $this;
    }

    /**
     * Get amount.
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Set amount.
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Get currency.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Set currency.
     *
     * @param string $currency Currency
     *
     * @return static
     */
    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Get service fee.
     *
     * @return int
     */
    public function getServiceFee(): int
    {
        return $this->serviceFee;
    }

    /**
     * Set service fee.
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setServiceFee(int $amount): static
    {
        $this->serviceFee = $amount;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): Datetime
    {
        return $this->created;
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Get paid date.
     *
     * @return DateTime
     */
    public function getPaidDate(): ?Datetime
    {
        return $this->getNullableDateTimeFromNonNullable($this->paid);
    }

    /**
     * Set paid date.
     *
     * @param ?DateTime $dateTime Paid date
     *
     * @return static
     */
    public function setPaidDate(?DateTime $dateTime): static
    {
        $this->paid = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Get registration start date.
     *
     * @return ?DateTime
     */
    public function getRegistrationStartDate(): ?Datetime
    {
        return $this->getNullableDateTimeFromNonNullable($this->registrationStarted);
    }

    /**
     * Set registration start date.
     *
     * @param ?DateTime $dateTime Registration start date
     *
     * @return static
     */
    public function setRegistrationStartDate(?DateTime $dateTime): static
    {
        $this->registrationStarted = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Get registration date.
     *
     * @return ?DateTime
     */
    public function getRegistrationDate(): ?Datetime
    {
        return $this->getNullableDateTimeFromNonNullable($this->registered);
    }

    /**
     * Set registration date.
     *
     * @param ?DateTime $dateTime Registration date
     *
     * @return static
     */
    public function setRegistrationDate(?DateTime $dateTime): static
    {
        $this->registered = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Get status.
     *
     * @return PaymentStatus
     */
    public function getStatus(): PaymentStatus
    {
        return PaymentStatus::from($this->status);
    }

    /**
     * Set status.
     *
     * @param PaymentStatus $status Status
     *
     * @return static
     */
    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }

    /**
     * Get status message.
     *
     * @return string
     */
    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    /**
     * Set status message.
     *
     * Note that some other methods override the status message, so ensure that this is called last if required!
     *
     * @param string $msg Status message
     *
     * @return static
     */
    public function setStatusMessage(string $msg): static
    {
        $this->statusMessage = $msg;
        return $this;
    }

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string
    {
        return $this->catUsername;
    }

    /**
     * Set catalog username.
     *
     * @param string $catUsername Catalog username
     *
     * @return static
     */
    public function setCatUsername(string $catUsername): static
    {
        $this->catUsername = $catUsername;
        return $this;
    }

    /**
     * Check if the payment is in progress
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->status === PaymentStatus::InProgress->value;
    }

    /**
     * Check if the payment is registered with the ILS
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->status === PaymentStatus::Complete->value;
    }

    /**
     * Set payment canceled
     *
     * @return static
     */
    public function setCanceled(): static
    {
        $this->status = PaymentStatus::Canceled->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Check if the payment is paid and needs registration with the ILS
     *
     * @return bool
     */
    public function needsRegistration(): bool
    {
        return in_array(
            $this->status,
            [
                PaymentStatus::Paid->value,
                PaymentStatus::RegistrationFailed->value,
            ]
        );
    }

    /**
     * Set payment paid
     *
     * @return static
     */
    public function setPaid(): static
    {
        $this->paid = new DateTime();
        $this->status = PaymentStatus::Paid->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment registered
     *
     * @return static
     */
    public function setRegistered(): static
    {
        $this->registered = new DateTime();
        $this->status = PaymentStatus::Complete->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment status to "registration failed"
     *
     * @param string $msg Message
     *
     * @return static
     */
    public function setRegistrationFailed(string $msg): static
    {
        $this->status = PaymentStatus::RegistrationFailed->value;
        $this->statusMessage = $msg;
        $this->registrationStarted = $this->getUnassignedDefaultDateTime();
        return $this;
    }

    /**
     * Set registration start timestamp
     *
     * @return static
     */
    public function setRegistrationStarted(): static
    {
        $this->registrationStarted = new DateTime();
        return $this;
    }

    /**
     * Check if registration is in progress (i.e. started within 120 seconds)
     *
     * @return bool
     */
    public function isRegistrationInProgress(): bool
    {
        $startDate = $this->getRegistrationStartDate();
        return $startDate && (time() - $startDate->getTimestamp() < 120);
    }

    /**
     * Set payment status to "registration expired"
     *
     * @return static
     */
    public function setExpired(): static
    {
        $this->status = PaymentStatus::RegistrationExpired->value;
        $this->statusMessage = '';
        return $this;
    }

    /**
     * Set payment reported
     *
     * @return static
     */
    public function setReported(): static
    {
        $this->reported = new DateTime();
        return $this;
    }

    /**
     * Set payment status to "fines updated"
     *
     * @return static
     */
    public function setFinesUpdated(): static
    {
        $this->status = PaymentStatus::FinesUpdated->value;
        $this->statusMessage = '';
        return $this;
    }
}
