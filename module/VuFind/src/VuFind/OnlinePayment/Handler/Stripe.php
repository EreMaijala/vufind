<?php

/**
 * Payment handler for Stripe
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
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */

namespace VuFind\OnlinePayment\Handler;

use Laminas\Log\LoggerAwareInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Exception\PaymentException;

/**
 * Payment handler for Stripe
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
class Stripe extends AbstractBase implements
    HandlerInterface,
    LoggerAwareInterface,
    TranslatorAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\OnlinePayment\OnlinePaymentEventLogTrait;

    /**
     * Start payment.
     *
     * Starts payment with the payment service and redirects the user to the service.
     *
     * @param string              $returnBaseUrl Return URL
     * @param string              $notifyBaseUrl Notify URL
     * @param UserEntityInterface $user          User
     * @param array               $patron        Patron information
     * @param string              $sourceIls     Patron MultiBackend source ILS
     * @param int                 $amount        Amount (excluding service fee)
     * @param int                 $serviceFee    Service fee
     * @param array               $fines         Fines data
     * @param string              $currency      Currency
     * @param string              $paymentParam  Payment status URL parameter
     *
     * @return void
     *
     * @throws PaymentException
     */
    public function startPayment(
        string $returnBaseUrl,
        string $notifyBaseUrl,
        UserEntityInterface $user,
        array $patron,
        string $sourceIls,
        int $amount,
        int $serviceFee,
        array $fines,
        string $currency,
        string $paymentParam
    ): void {
        $patronId = $patron['cat_username'];
        $localIdentifier = $this->generateLocalIdentifier($patronId);

        $returnUrl = $this->addQueryParams(
            $returnBaseUrl,
            [$paymentParam => $localIdentifier]
        );
        $notifyUrl = $this->addQueryParams(
            $notifyBaseUrl,
            [$paymentParam => $localIdentifier]
        );

        // Map fines to items:
        $lineItems = [];
        foreach ($fines as $fine) {
            if (null === ($code = $this->getFineProductCode($fine))) {
                continue;
            }
            $code = mb_substr($code, 0, 100, 'UTF-8');

            $description = $this->getFineDescription($fine, 255);
            $taxCode = $this->getFineTaxRate($fine);

            $item = [
                'price_data' => [
                    'currency' => $this->getCurrencyCode(),
                    'product_data' => [
                        'name' => $code,
                        'description' => $description,
                    ],
                    'unit_amount' => round($fine['balance']),
                    'quantity' => 1,
                ],
            ];
            if (null !== $taxCode) {
                $item['price_data']['product_data']['tax_code'] = $taxCode;
            }

            $lineItems[] = $item;
        }
        if ($lineItems && $serviceFee) {
            $item = [
                'price_data' => [
                    'currency' => $this->getCurrencyCode(),
                    'product_data' => [
                        'name' => $this->getServiceFeeProductCode() ?? $this->getDefaultProductCode(),
                        'description' => $this->translator->translate('Service fee'),
                    ],
                    'unit_amount' => $serviceFee,
                    'quantity' => 1,
                ],
            ];
            if (null !== ($taxCode = $this->getServiceFeeTaxRate())) {
                $item['price_data']['product_data']['tax_code'] = $taxCode;
            }
            $lineItems[] = $item;
        }

        $sessionSettings = [
            'mode' => 'payment',
            'client_reference_id' => $localIdentifier,
            'success_url' => $returnUrl,
            'cancel_url' => $returnUrl,
            'line_items' => $lineItems,
            'locale' => $this->getCurrentLocale(),
            'customer_creation' => 'if_required',
        ];
        if ($email = $user->getEmail()) {
            $sessionSettings['customer_email'] = $email;
        }

        try {
            $stripeSession = Session::create($sessionSettings);
        } catch (ApiErrorException $e) {
            $request = json_encode($sessionSettings, JSON_PRETTY_PRINT);
            $this->logPaymentError(
                'Exception creating a Stripe session: ' . $e->getMessage(),
                compact('user', 'patron', 'fines', 'request')
            );
            throw new PaymentException('An error has occurred');
        }

        $payment = $this->createPaymentEntity(
            $localIdentifier,
            $stripeSession->id,
            $sourceIls,
            $user,
            $patronId,
            $amount,
            $serviceFee,
            $currency,
            $fines
        );

        $this->redirectToPayment($stripeSession->url, $payment);
    }

    /**
     * Process the response from payment service.
     *
     * Validates the response from the payment service and marks the payment as paid as appropriate.
     * Registration with ILS happens elsewhere.
     *
     * @param PaymentEntityInterface $payment Payment
     * @param \Laminas\Http\Request  $request Request
     *
     * @return int One of the result codes defined in AbstractBase
     */
    public function processPaymentResponse(
        PaymentEntityInterface $payment,
        \Laminas\Http\Request $request
    ): int
    {
        try {
            $stripeSession = Session::retrieve($payment->getRemoteIdentifier());
        } catch (ApiErrorException $e) {
            return self::PAYMENT_FAILURE;
        }
        return $stripeSession->payment_status === 'paid' ? self::PAYMENT_SUCCESS : self::PAYMENT_CANCEL;
    }
}
