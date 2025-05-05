<?php

/**
 * "Register Online Payment" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * "Register Online Payment" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RegisterOnlinePayment extends AbstractOnlinePaymentAction
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
        $paymentId = $params->fromPost('paymentId') ?? $params->fromQuery('paymentId');
        if (!$paymentId) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        $payment = $this->paymentService->getPaymentByIdentifier($paymentId);
        if (!$payment) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        if ($payment->isRegistered()) {
            // Already registered, return success:
            return $this->formatResponse('');
        }
        if (!$payment->needsRegistration()) {
            // Bad status, return error:
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }
        if ($payment->isRegistrationInProgress()) {
            // Registration already in progress, return error:
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        return $this->markFeesAsPaidForPayment($payment)
            ? $this->formatResponse('')
            : $this->formatResponse('', self::STATUS_HTTP_ERROR);
    }
}
