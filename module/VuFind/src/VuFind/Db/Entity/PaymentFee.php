<?php

/**
 * Entity model for payment_fee table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for payment_fee table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
#[ORM\Table(name: 'payment_fee')]
#[ORM\Entity]
class PaymentFee implements PaymentFeeEntityInterface
{
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
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \VuFind\Db\Entity\Payment::class)]
    protected $payment;

    /**
     * Title
     *
     * @var string
     */
    #[ORM\Column(name: 'title', type: 'string', length: 255, nullable: false)]
    protected $title;

    /**
     * Type.
     *
     * @var string
     */
    #[ORM\Column(name: 'type', type: 'string', length: 255, nullable: false)]
    protected $type;

    /**
     * Description.
     *
     * @var string
     */
    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: false)]
    protected $description;

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
     * Fine ID.
     *
     * @var string
     */
    #[ORM\Column(name: 'fine_id', type: 'string', length: 1024, nullable: false, options: ['default' => ''])]
    protected $fineId;

    /**
     * Organization.
     *
     * @var string
     */
    #[ORM\Column(name: 'organization', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected $organization;

    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Payment setter
     *
     * @param PaymentEntityInterface $payment Payment.
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
     * Title setter
     *
     * @param string $title Title
     *
     * @return static
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Title getter
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Type setter
     *
     * @param string $type Type
     *
     * @return static
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Type getter
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Description setter
     *
     * @param string $description Description
     *
     * @return static
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Description getter
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Amount setter
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
     * Amount getter
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Currency setter
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
     * Currency getter
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Fine Id setter
     *
     * @param string $fineId Fine ID (ILS)
     *
     * @return static
     */
    public function setFineId(string $fineId): static
    {
        $this->fineId = $fineId;
        return $this;
    }

    /**
     * Fine Id getter
     *
     * @return string
     */
    public function getFineId(): string
    {
        return $this->fineId ?? '';
    }

    /**
     * Organization setter
     *
     * @param string $organization Organization
     *
     * @return static
     */
    public function setOrganization(string $organization): static
    {
        $this->organization = $organization;
        return $this;
    }

    /**
     * Organization getter
     *
     * @return string
     */
    public function getOrganization(): string
    {
        return $this->organization ?? '';
    }
}
