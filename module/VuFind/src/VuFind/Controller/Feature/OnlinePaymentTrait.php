<?php

/**
 * Online payment controller feature trait.
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
 * @package  Controller
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller\Feature;

use Laminas\View\Model\ViewModel;
use VuFind\Exception\PaymentException;
use VuFind\OnlinePayment\Handler\AbstractBase as BaseHandler;
use VuFind\OnlinePayment\Handler\HandlerInterface;
use VuFind\OnlinePayment\OnlinePaymentEventLogTrait;
use VuFind\OnlinePayment\OnlinePaymentManager;

use function count;

/**
 * Online payment controller feature trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait OnlinePaymentTrait
{
    use \VuFind\Log\LoggerAwareTrait;
    use OnlinePaymentEventLogTrait;

    /**
     * Checks if the given list of fines is identical to the listing
     * preserved in the session variable.
     *
     * @param array $patron Patron.
     * @param int   $amount Total amount to pay without fees
     *
     * @return bool
     */
    protected function checkIfFinesUpdated(array $patron, int $amount): bool
    {
        $session = $this->serviceLocator->get(OnlinePaymentManager::class)->getOnlinePaymentSession();
        if (!$session) {
            $this->handleError('PaymentSessionError: Session empty for patron: ' . json_encode($patron));
            return true;
        }
        if ($session->catUsername !== $patron['cat_username']) {
            $this->handleError(
                'PaymentSessionError: Patron cat_username does not match session: '
                . $patron['cat_username'] . ' != ' . $session->catUsername
            );
            return true;
        }
        if ($session->amount !== $amount) {
            $this->handleError('PaymentSessionError: Payment amount updated: ' . $session->amount . ' != ' . $amount);
            return true;
        }
        return false;
    }

    /**
     * Return online payment handler.
     *
     * @param string $sourceIls Patron ILS source
     *
     * @return ?HandlerInterface Handler, or null on failure.
     */
    protected function getOnlinePaymentHandler(string $sourceIls): ?HandlerInterface
    {
        $onlinePaymentManager = $this->serviceLocator->get(\VuFind\OnlinePayment\OnlinePaymentManager::class);
        if (!$onlinePaymentManager->isEnabled($sourceIls)) {
            return null;
        }

        try {
            return $onlinePaymentManager->getHandler($sourceIls);
        } catch (\Exception $e) {
            $this->handleError("Error retrieving online payment handler for source $sourceIls: " . (string)$e);
            return null;
        }
    }

    /**
     * Support method for handling online payments.
     *
     * @param array     $patron Patron
     * @param array     $fines  List of fines
     * @param ViewModel $view   View
     *
     * @return void
     */
    protected function handleOnlinePayment(array $patron, array $fines, ViewModel $view): void
    {
        $view->onlinePaymentEnabled = false;
        $sourceIls = $patron['__source'] ?? 'default';
        // Check if payment handler is configured
        $onlinePaymentManager = $this->serviceLocator->get(OnlinePaymentManager::class);
        if (!$onlinePaymentManager->isEnabled($sourceIls)) {
            $this->handleDebugMsg("Online payment not enabled for $sourceIls");
            return;
        }
        $session = $onlinePaymentManager->getOnlinePaymentSession();

        try {
            if (!($paymentHandler = $this->getOnlinePaymentHandler($sourceIls))) {
                $this->handleDebugMsg("No online payment handler defined for $sourceIls");
                return;
            }
        } catch (\Exception $e) {
            $this->handleError("Could not initialize payment handler for $sourceIls: " . (string)$e);
            return;
        }

        $catalog = $this->getILS();

        // Check if online payment configuration exists for the ILS driver
        $paymentConfig = $onlinePaymentManager->getOnlinePaymentConfig($sourceIls);
        if (empty($paymentConfig)) {
            $this->handleDebugMsg("No online payment ILS configuration for $sourceIls");
            return;
        }

        // Check if online payment is enabled for the ILS driver
        if (!$catalog->checkFunction('registerPayment', compact('patron'))) {
            $this->handleDebugMsg("registerPayment not available for $sourceIls");
            return;
        }

        // Check that mandatory settings exist
        if (!isset($paymentConfig['currency'])) {
            $this->handleError("Mandatory setting 'currency' missing from ILS driver for $sourceIls");
            return;
        }

        if (!($user = $this->getUser())) {
            $this->handleError('Could not get user');
            return;
        }

        $selectFees = $paymentConfig['selectFines'] ?? false;
        $pay = $this->formWasSubmitted('pay-confirm');
        $selectedIds = ($selectFees && $pay)
            ? $this->getRequest()->getPost()->get('selectedIDS', [])
            : null;
        $payableOnline = $catalog->getOnlinePaymentDetails(
            $patron,
            $fines,
            $selectedIds
        );
        if ($selectedIds && empty($payableOnline['fines'])) {
            $this->handleError("Fines to pay missing from ILS driver for $sourceIls");
            return;
        }

        $payableFines = array_filter(
            $fines,
            function ($fine) {
                return $fine['payable_online'];
            }
        );

        $view->onlinePayment = true;
        $view->paymentHandler = $onlinePaymentManager->getHandlerName($sourceIls);
        $view->serviceFee = $paymentConfig['serviceFee'] ?? 0;
        $view->minimumFee = $paymentConfig['minimumFee'] ?? 0;
        $view->payableOnline = $payableOnline['amount'];
        $view->payableTotal = $payableOnline['amount'] + $view->serviceFee;
        $view->payableOnlineCnt = count($payableFines);
        $view->nonPayableFines = count($fines) != count($payableFines);
        $view->registerPayment = false;
        $view->selectFees = $selectFees;

        $paymentService = $this->getDbService(\VuFind\Db\Service\PaymentServiceInterface::class);
        $lastPayment = null;
        $receiptEnabled = $paymentConfig['receipt'] ?? false;
        if ($receiptEnabled) {
            $lastPayment = $paymentService->getLastPaidPaymentForPatron($patron['cat_username']);
        }
        if (
            $lastPayment
            && $this->params()->fromQuery('paymentReceipt') === 'true'
        ) {
            $receipt = $this->serviceLocator->get(\VuFind\OnlinePayment\Receipt::class);
            $data = $receipt->createReceiptPDF($lastPayment, $paymentConfig);
            header('Content-Type: application/pdf');
            header(
                'Content-disposition: inline; filename="' .
                addcslashes($data['filename'], '"') . '"'
            );
            echo $data['pdf'];
            exit(0);
        }
        $view->lastPayment = $lastPayment;

        $paymentInProgress = $paymentService->isPaymentInProgressForPatron($patron['cat_username']);
        if (
            $pay && $session && $payableOnline
            && $payableOnline['payable'] && $payableOnline['amount']
            && !$paymentInProgress
        ) {
            // Check CSRF:
            $csrfValidator = $this->serviceLocator->get(\VuFind\Validator\CsrfInterface::class);
            $csrf = $this->getRequest()->getPost()->get('csrf');
            if (!$csrfValidator->isValid($csrf)) {
                $this->flashMessenger()->addErrorMessage('Payment::error_payment_request_failed');
                header('Location: ' . $this->getServerUrl('myresearch-fines'));
                exit();
            }
            // After successful token verification, clear list to shrink session and
            // ensure that the form is not re-sent:
            $csrfValidator->trimTokenList(0);

            // Payment requested, do preliminary checks:
            if ($paymentService->isPaymentInProgressForPatron($patron['cat_username'])) {
                $this->flashMessenger()->addErrorMessage('Payment::error_payment_request_failed');
                header('Location: ' . $this->getServerUrl('myresearch-fines'));
                exit();
            }
            if (
                (($paymentConfig['exactBalanceRequired'] ?? true)
                || !empty($paymentConfig['creditUnsupported']))
                && !$selectFees
                && $this->checkIfFinesUpdated($patron, $payableOnline['amount'])
            ) {
                // Fines updated, redirect and show updated list.
                $this->flashMessenger()->addErrorMessage('Payment::error_fines_changed');
                header('Location: ' . $this->getServerUrl('myresearch-fines'));
                exit();
            }
            $returnUrl = $this->getServerUrl('myresearch-fines');
            $notifyUrl = $this->getServerUrl('home') . 'AJAX/onlinePaymentNotify?lng='
                . urlencode($this->getTranslatorLocale());
            [$driver, ] = explode('.', $patron['cat_username'], 2);

            $patronProfile = array_merge(
                $patron,
                $catalog->getMyProfile($patron)
            );

            // Start payment
            try {
                $paymentHandler->startPayment(
                    $returnUrl,
                    $notifyUrl,
                    $user,
                    $patronProfile,
                    $driver,
                    $payableOnline['amount'],
                    $view->serviceFee,
                    $payableOnline['fines'] ?? $payableFines,
                    $paymentConfig['currency'],
                    'local_payment_id'
                );
            } catch (PaymentException $e) {
                $this->flashMessenger()->addErrorMessage($e->getMessage());
            }
            // We should only end up here on error, but redirect always just in case
            // the payment handler somehow misbehaves:
            header('Location: ' . $this->getServerUrl('myresearch-fines'));
            exit();
        }

        // Now check for local payment identifier in the URL and process any payment handler response:
        $request = $this->getRequest();
        $localIdentifier = $request->getQuery()->get('local_payment_id');
        if (
            $localIdentifier
            && ($payment = $paymentService->getPaymentByLocalIdentifier($localIdentifier))
        ) {
            $this->ensureLogger();
            $this->handleDebugMsg('Online payment response handler called. Request: ' . (string)$request);
            $this->addPaymentEvent($payment, 'Response handler called');

            if ($payment->isRegistered()) {
                // Already registered, treat as success:
                $this->flashMessenger()->addSuccessMessage('Payment::Payment Successful');
            } else {
                // Process payment response:
                try {
                    $result = $onlinePaymentManager->processPaymentHandlerResponse($payment, $request, false);
                    if (BaseHandler::PAYMENT_SUCCESS === $result['resultCode']) {
                        // Reload payment and check if registration is still pending:
                        $payment = $paymentService->getPaymentByLocalIdentifier($localIdentifier);
                        if ($payment?->needsRegistration()) {
                            // Display page with success message and register payment with ILS asynchronously:
                            $this->flashMessenger()->addSuccessMessage('Payment::Payment Successful');
                            $view->registerPaymentLocalIdentifier = $payment->getLocalIdentifier();
                            $this->addPaymentEvent($payment, 'Registration requested');
                        }
                    } elseif (BaseHandler::PAYMENT_CANCEL === $result) {
                        $this->flashMessenger()->addSuccessMessage('Payment::Payment Canceled');
                    } elseif (BaseHandler::PAYMENT_FAILURE === $result) {
                        $this->flashMessenger()->addErrorMessage('Payment::error_payment_request_failed');
                    }
                } catch (PaymentException $e) {
                    $this->handleError(
                        'Error processing payment handler response for ' . $payment->getSourceIls()
                        . ", payment $localIdentifier: " . (string)$e
                    );
                }
            }
        }

        if (!$view->registerPaymentLocalIdentifier) {
            if ($paymentInProgress) {
                $this->flashMessenger()->addErrorMessage('Payment::registration_failed');
            } else {
                // Check if payment is permitted:
                $allowPayment = $payableOnline && $payableOnline['payable'] && $payableOnline['amount'];

                // Store current fines to session:
                $this->storeFines($patron, $payableOnline['amount']);

                if ($onlinePaymentManager->getAndClearPaymentSuccessFlag()) {
                    $this->flashMessenger()->addSuccessMessage('Payment::Payment Successful');
                }

                $view->onlinePaymentEnabled = $allowPayment;
                $view->selectedIds = $this->getRequest()->getPost()->get('selectedIDS', []);
                if (!empty($payableOnline['reason'])) {
                    $view->nonPayableReason = $payableOnline['reason'];
                } elseif ($this->formWasSubmitted('pay')) {
                    $view->setTemplate('myresearch/fines-confirm-pay.phtml');
                } else {
                    // Check for a started payment:
                    $view->startedPayment = $paymentService->getStartedPaymentForPatron(
                        $patron['cat_username'],
                        (int)($paymentConfig['paymentMaxDuration'] ?? 15)
                    );
                }
            }
        }
    }

    /**
     * Store fines to session.
     *
     * @param array $patron Patron
     * @param int   $amount Total payable amount excluding fees
     *
     * @return void
     */
    protected function storeFines(array $patron, int $amount): void
    {
        $session = $this->serviceLocator->get(OnlinePaymentManager::class)->getOnlinePaymentSession();
        $session->catUsername = $patron['cat_username'];
        $session->amount = $amount;
    }

    /**
     * Make sure that logger is available.
     *
     * @return void
     */
    protected function ensureLogger(): void
    {
        if (null === $this->getLogger()) {
            $this->setLogger($this->serviceLocator->get(\VuFind\Log\Logger::class));
        }
    }

    /**
     * Log error message.
     *
     * @param string $msg Error message.
     *
     * @return void
     */
    protected function handleError($msg)
    {
        $this->ensureLogger();
        $this->logError($msg);
    }

    /**
     * Log a debug message.
     *
     * @param string $msg Debug message.
     *
     * @return void
     */
    protected function handleDebugMsg($msg)
    {
        $this->ensureLogger();
        $this->logger->debug($msg);
    }
}
