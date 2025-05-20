<?php

/**
 * "Online Payment Notify" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2024.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\OnlinePayment\Handler\AbstractBase as BaseHandler;

/**
 * "Online Payment Notify" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OnlinePaymentNotify extends AbstractOnlinePaymentAction
{
    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $request = $params->getController()->getRequest();

        $this->logger->warn(
            'Online payment notify handler called. Request: '
            . (string)$request
        );

        $reqParams = array_merge(
            $request->getQuery()->toArray(),
            $request->getPost()->toArray()
        );

        if (empty($reqParams['vufind_payment_id'])) {
            $this->logError(
                'Error processing payment: vufind_payment_id not provided. Query: '
                . $request->getQuery()->toString()
                . ', post parameters: ' . $request->getPost()->toString()
            );
            // If this is an old (invalid) request, return success:
            if (
                !empty($reqParams['driver'])
                && '1' == ($reqParams['payment'] ?? '')
            ) {
                return $this->formatResponse('');
            }
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        $localIdentifier = $reqParams['vufind_payment_id'];
        if (!($payment = $this->paymentService->getPaymentByLocalIdentifier($localIdentifier))) {
            $this->logError(
                "Error processing payment: payment $localIdentifier not found"
            );
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $this->addPaymentEvent($payment, 'Notify handler called');

        if ($payment->isRegistered()) {
            $this->addPaymentEvent($payment, 'Payment already registered');
            // Already registered, treat as success:
            return $this->formatResponse('');
        }

        $handler = $this->getOnlinePaymentHandler($payment->getSourceIls());
        if (!$handler) {
            $this->logError(
                'Error processing payment: could not initialize payment handler ' . $payment->getSourceIls()
                . " for $localIdentifier"
            );
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        $paymentResult = $handler->processPaymentResponse($payment, $request);
        $this->logger->warn("Online payment notify handler for $localIdentifier result: $paymentResult");

        $markedAsPaid = false;
        if (BaseHandler::PAYMENT_SUCCESS === $paymentResult) {
            if ($payment->isInProgress()) {
                $payment->setPaid();
                $this->paymentService->persistEntity($payment);
                $markedAsPaid = true;
            }
        } elseif (BaseHandler::PAYMENT_FAILURE == $paymentResult) {
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        if (
            $markedAsPaid
            && ($patron = $this->getPatronForPayment($payment))
            && ($paymentConfig = $this->ils->getConfig('OnlinePayment', $patron))
            && ($paymentConfig['receipt'] ?? false)
        ) {
            try {
                $res = $this->receipt->sendEmail($payment->getUser(), $patron, $payment, $paymentConfig);
                $this->addPaymentEvent($payment, $res ? 'Receipt sent' : 'Receipt not sent (no email address)');
            } catch (\Exception $e) {
                $this->logger->err("Failed to send email receipt for $localIdentifier: " . (string)$e);
                $this->addPaymentEvent($payment, 'Sending of receipt failed', ['error' => (string)$e]);
            }
        }

        // This handler does not mark fees as paid since that happens in the response
        // handler or online payment monitor.

        return $this->formatResponse('');
    }
}
