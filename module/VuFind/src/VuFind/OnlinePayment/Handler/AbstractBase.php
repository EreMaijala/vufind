<?php

/**
 * Abstract payment handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2025.
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
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */

namespace VuFind\OnlinePayment\Handler;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\PaymentEventLogServiceInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\I18n\Translator\TranslatorAwareInterface;

use function count;
use function is_array;
use function is_object;

/**
 * Abstract payment handler
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
abstract class AbstractBase implements
    HandlerInterface,
    LoggerAwareInterface,
    TranslatorAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\OnlinePayment\OnlinePaymentEventLogTrait;

    /**
     * Result codes for processPaymentResponse
     *
     * @var int
     */
    public const PAYMENT_SUCCESS = 0; // Payment successful
    public const PAYMENT_CANCEL = 1;  // Payment canceled
    public const PAYMENT_FAILURE = 2; // Payment failed
    public const PAYMENT_PENDING = 3; // Payment in progress

    /**
     * Payment Configuration.
     *
     * @var array
     */
    protected array $paymentConfig = [];

    /**
     * Basic mappings from fine types to product codes
     *
     * @var array
     */
    protected array $productCodeMappings = [];

    /**
     * Mappings from fine types to tax rates
     *
     * @var array
     */
    protected array $taxRateMappings = [];

    /**
     * Fine organization-specific mappings from fine types to product codes
     *
     * @var array
     */
    protected array $organizationProductCodePrefixMappings = [];

    /**
     * Constructor
     *
     * @param array                           $config          VuFind configuration
     * @param \VuFindHttp\HttpService         $http            HTTP service
     * @param LocaleSettings                  $localeSettings  Locale settings
     * @param PaymentServiceInterface         $paymentService  Payment database service
     * @param PaymentFeeServiceInterface      $feeService      Payment fee database service
     * @param PaymentEventLogServiceInterface $eventLogService Payment event log database service
     */
    public function __construct(
        protected array $config,
        protected \VuFindHttp\HttpService $http,
        protected LocaleSettings $localeSettings,
        protected PaymentServiceInterface $paymentService,
        protected PaymentFeeServiceInterface $feeService,
        PaymentEventLogServiceInterface $eventLogService
    ) {
        $this->eventLogService = $eventLogService;
    }

    /**
     * Initialize the handler
     *
     * @param array $paymentConfig Online payment configuration
     *
     * @return void
     */
    public function init(array $paymentConfig): void
    {
        $this->paymentConfig = $paymentConfig;

        $this->productCodeMappings = $this->parseMappings($this->paymentConfig['productCodeMappings'] ?? '');
        $this->taxRateMappings = $this->parseMappings($this->paymentConfig['taxRateMappings'] ?? '');
        $this->organizationProductCodePrefixMappings
            = $this->parseMappings($this->paymentConfig['organizationProductCodePrefixMappings'] ?? '');
    }

    /**
     * Return name of handler.
     *
     * @return string name
     */
    public function getName(): string
    {
        return $this->paymentConfig['handler'];
    }

    /**
     * Generate the internal payment transaction identifier.
     *
     * @param string $patronId Patron's Catalog Username (barcode)
     *
     * @return string
     */
    protected function generateLocalIdentifier(string $patronId): string
    {
        return md5($patronId . '_' . microtime(true));
    }

    /**
     * Add query parameters to an url
     *
     * @param string $url    URL
     * @param array  $params Parameters to add
     *
     * @return string
     */
    protected function addQueryParams(string $url, array $params): string
    {
        $url .= !str_contains($url, '?') ? '?' : '&';
        $url .= http_build_query($params);
        return $url;
    }

    /**
     * Store payment to database.
     *
     * @param string              $localIdentifier  Local payment identifier
     * @param ?string             $remoteIdentifier Handler's payment identifier
     * @param string              $sourceIls        Patron MultiBackend source ILS
     * @param UserEntityInterface $user             User
     * @param string              $patronId         Patron's catalog username (e.g. barcode)
     * @param int                 $amount           Amount (excluding service fee)
     * @param int                 $serviceFee       Service fee
     * @param string              $currency         Currency
     * @param array               $fines            Fines data
     *
     * @return PaymentEntityInterface
     */
    protected function createPaymentEntity(
        string $localIdentifier,
        ?string $remoteIdentifier,
        string $sourceIls,
        UserEntityInterface $user,
        string $patronId,
        int $amount,
        int $serviceFee,
        string $currency,
        array $fines
    ): PaymentEntityInterface {
        $payment = $this->paymentService->createEntity()
            ->setLocalIdentifier($localIdentifier)
            ->setRemoteIdentifier($remoteIdentifier)
            ->setSourceIls($sourceIls)
            ->setUser($user)
            ->setCatUsername($patronId)
            ->setAmount($amount)
            ->setServiceFee($serviceFee)
            ->setCurrency($currency);
        $this->paymentService->persistEntity($payment);

        foreach ($fines as $fine) {
            // Sanitize fine strings
            $fee = $this->feeService->createEntity()
                ->setUser($user)
                ->setPayment($payment)
                ->setAmount($fine['balance'])
                ->setType(iconv('UTF-8', 'UTF-8//IGNORE', $fine['fine'] ?? ''))
                ->setDescription(iconv('UTF-8', 'UTF-8//IGNORE', $fine['description'] ?? ''))
                ->setFineId($fine['fine_id'])
                ->setOrganization(iconv('UTF-8', 'UTF-8//IGNORE', $fine['organization'] ?? ''))
                ->setTitle(iconv('UTF-8', 'UTF-8//IGNORE', $fine['title'] ?? ''));
            $this->feeService->persistEntity($fee);
        }

        $this->addPaymentEvent($payment, 'Payment created');

        return $payment;
    }

    /**
     * Redirect to payment handler.
     *
     * @param string                 $url     URL
     * @param PaymentEntityInterface $payment Payment
     *
     * @return void
     */
    protected function redirectToPayment(string $url, PaymentEntityInterface $payment): void
    {
        header("Location: $url", true, 302);
        $this->addPaymentEvent($payment, 'Redirected to payment service');
        exit();
    }

    /**
     * Parse a mappings configuration to an array
     *
     * @param string $mappings Mappings
     *
     * @return array
     */
    protected function parseMappings(string $mappings): array
    {
        if (!$mappings) {
            return [];
        }
        $result = [];
        foreach (explode(':', $mappings) as $item) {
            $parts = explode('=', $item, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ('' !== $key && '' !== $value) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Log an error
     *
     * @param string $msg  Error message
     * @param array  $data Additional data to log
     *
     * @return void
     */
    protected function logPaymentError(string $msg, array $data = []): void
    {
        $msg = "Online payment: $msg";
        if ($data) {
            $msg .= ". Additional data:\n" . $this->dumpData($data);
        }
        $this->logError($msg);
    }

    /**
     * Extract first name and last name from user
     *
     * @param UserEntityInterface $user User
     *
     * @return array Associative array with 'firstname' and 'lastname'
     */
    protected function extractUserNames(UserEntityInterface $user): array
    {
        $lastname = trim($user->getLastname());
        if (!empty($user->getFirstname())) {
            $firstname = trim($user->getFirstname());
        } else {
            // We don't have both names separately, try to extract first name from
            // last name.
            if (strpos($lastname, ',') > 0) {
                // Lastname, Firstname
                [$lastname, $firstname] = explode(',', $lastname, 2);
            } else {
                // First Middle Last
                if (preg_match('/^(.*) (.*?)$/', $lastname, $matches)) {
                    $firstname = $matches[1];
                    $lastname = $matches[2];
                } else {
                    $firstname = '';
                }
            }
            $lastname = trim($lastname);
            $firstname = trim($firstname);
        }
        return compact('firstname', 'lastname');
    }

    /**
     * Dump a data array with mixed content
     *
     * @param array $data  Data array
     * @param int   $level Indentation level
     *
     * @return string
     */
    protected function dumpData(array $data, int $level = 0): string
    {
        // Don't go too deep:
        if ($level > 3) {
            return '';
        }

        $results = [];
        $indent = str_repeat('  ', $level);

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $value = $value->toArray();
                } else {
                    $key = "$key: " . $value::class;
                    $value = get_object_vars($value);
                }
            }
            if (is_array($value)) {
                $results[] = "$key: {\n"
                    . $this->dumpData($value, $level + 1)
                    . "\n$indent}";
            } else {
                $results[] = "$key: " . var_export($value, true);
            }
        }

        return $indent . implode(",\n$indent", $results);
    }

    /**
     * Get user's locale string (e.g. 'en' or 'en-GB')
     *
     * @return string
     */
    protected function getCurrentLocale(): string
    {
        $parts = explode('-', $this->localeSettings->getUserLocale(), 2);
        return isset($parts[1]) ? ($parts[0] . '-' . mb_strtoupper($parts[1], 'UTF-8')) : $parts[0];
    }

    /**
     * Get two character language code from user's current locale
     *
     * @return string
     */
    protected function getCurrentLanguageCode(): string
    {
        [$lang] = explode('-', $this->getCurrentLocale(), 2);
        return $lang;
    }

    /**
     * Get the currency code
     *
     * @return string
     */
    protected function getCurrencyCode(): string
    {
        return $this->paymentConfig['currency'] ?? 'USD';
    }

    /**
     * Get the default product code
     *
     * @return ?string
     */
    protected function getDefaultProductCode(): ?string
    {
        return $this->paymentConfig['productCode'] ?? null;
    }

    /**
     * Get the service fee product code
     *
     * @return ?string
     */
    protected function getServiceFeeProductCode(): ?string
    {
        return $this->paymentConfig['serviceFeeProductCode'] ?? null;
    }

    /**
     * Get the service fee tax rate
     *
     * @return ?string
     */
    protected function getServiceFeeTaxRate(): ?string
    {
        return $this->paymentConfig['serviceFeeTaxRate'] ?? null;
    }

    /**
     * Get a product code for a fine
     *
     * @param array $fine Fine
     *
     * @return ?string
     */
    protected function getFineProductCode(array $fine): ?string
    {
        // If we don't have any mappings, assume no products:
        if (
            !$this->productCodeMappings
            && !$this->organizationProductCodePrefixMappings
            && !$this->getDefaultProductCode()
            && !isset($fine['product_code'])
        ) {
            return null;
        }

        $fineType = $fine['fine'] ?? '';

        // Determine product code:
        $code = $fine['product_code'] ?? null;
        if (null === $code) {
            $code = $this->productCodeMappings[$fineType] ?? null;
        }
        if (null === $code) {
            $code = $this->getDefaultProductCode();
        }
        if (null === $code) {
            $code = $fineType;
        }

        // Add any organization prefix:
        $fineOrg = $fine['organization'] ?? '';
        if (null !== ($orgProductCode = $this->organizationProductCodePrefixMappings[$fineOrg] ?? null)) {
            $code = $orgProductCode . $code;
        }

        return $code;
    }

    /**
     * Get tax rate for a fine
     *
     * @param array $fine Fine
     *
     * @return mixed Tax rate percent or code depending on payment handler, or null if not defined
     */
    protected function getFineTaxRate(array $fine)
    {
        $fineType = $fine['fine'] ?? '';
        return $fine['tax_rate'] ?? $this->taxRateMappings[$fineType] ?? null;
    }

    /**
     * Get fine description
     *
     * Description includes fine type and record title
     *
     * @param array $fine      Fine
     * @param int   $maxLength Maximum length of the description
     *
     * @return string
     */
    protected function getFineDescription(array $fine, int $maxLength): string
    {
        if ('' !== ($fineDesc = $fine['description'] ?? '')) {
            return mb_substr($fineDesc, 0, $maxLength, 'UTF-8');
        }

        $fineType = $fine['fine'] ?? '';
        if ('' !== $fineType) {
            $fineDesc = mb_substr($this->translator->translate($fineType), 0, $maxLength, 'UTF-8');
        }
        if ('' !== ($title = $fine['title'] ?? '')) {
            $title = mb_substr(
                $title,
                0,
                $maxLength - 4 - mb_strlen($fineDesc, 'UTF-8'),
                'UTF-8'
            );
            $fineDesc .= " ($title)";
        }
        return $fineDesc;
    }
}
