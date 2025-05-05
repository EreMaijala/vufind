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
        $paymentIdentifier = $reqParams['vufind_payment_id'];
        if (!($t = $this->paymentService->getPaymentByIdentifier($paymentIdentifier))) {
            $this->logError(
                "Error processing payment: payment $paymentIdentifier not found"
            );
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $this->addPaymentEvent($t, 'Notify handler called');

        if ($t->isRegistered()) {
            $this->addPaymentEvent($t, 'Payment already registered');
            // Already registered, treat as success:
            return $this->formatResponse('');
        }

        $handler = $this->getOnlinePaymentHandler($t->getSourceId());
        if (!$handler) {
            $this->logError(
                'Error processing payment: could not initialize payment handler ' . $t->driver
                . " for $paymentIdentifier"
            );
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        [$paymentResult, $markedAsPaid] = $handler->processPaymentResponse($t, $request);

        $this->logger->warn("Online payment notify handler for $paymentIdentifier result: $paymentResult");

        if ($handler::PAYMENT_FAILURE == $paymentResult) {
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        if (
            $handler::PAYMENT_SUCCESS === $paymentResult
            && $markedAsPaid
            && ($patron = $this->getPatronForPayment($t))
            && ($this->dataSourceConfig[$patron['source']]['onlinePayment']['receipt'] ?? false)
        ) {
            try {
                $res = $this->receipt->sendEmail($t->getUser(), $patron, $t);
                $this->addPaymentEvent(
                    $t,
                    $res ? 'Receipt sent' : 'Receipt not sent (no email address)'
                );
            } catch (\Exception $e) {
                $this->logger->err("Failed to send email receipt for $paymentIdentifier: " . (string)$e);
                $this->addPaymentEvent($t, 'Sending of receipt failed', ['error' => (string)$e]);
            }
        }

        // This handler does not mark fees as paid since that happens in the response
        // handler or online payment monitor.

        return $this->formatResponse('');
    }
}
