<?php

/**
 * Online payment manager
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
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace VuFind\OnlinePayment;

use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\SessionManager;
use Laminas\Stdlib\RequestInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Service\PaymentEventLogServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Exception\PaymentException;
use VuFind\ILS\Connection;
use VuFind\Log\LoggerAwareTrait;
use VuFind\OnlinePayment\Handler\AbstractBase as BaseHandler;
use VuFind\OnlinePayment\Handler\HandlerInterface;
use VuFind\OnlinePayment\Handler\PluginManager as HandlerPluginManager;

/**
 * Online payment manager
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OnlinePaymentManager implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use OnlinePaymentEventLogTrait;

    /**
     * Constructor.
     *
     * @param HandlerPluginManager            $handlerManager   Handler plugin manager
     * @param Connection                      $ils              ILS Connection
     * @param PaymentServiceInterface         $paymentService   Payment database service
     * @param UserCardServiceInterface        $userCardService  User card database service
     * @param PaymentEventLogServiceInterface $eventLogService  Payment event log database service
     * @param ILSAuthenticator                $ilsAuthenticator ILS authenticator
     * @param Receipt                         $receipt          Receipt handler
     * @param SessionManager                  $sessionManager   Session manager
     */
    public function __construct(
        protected HandlerPluginManager $handlerManager,
        protected Connection $ils,
        protected ILSAuthenticator $ilsAuthenticator,
        protected PaymentServiceInterface $paymentService,
        protected UserCardServiceInterface $userCardService,
        PaymentEventLogServiceInterface $eventLogService,
        protected Receipt $receipt,
        protected SessionManager $sessionManager
    ) {
        $this->eventLogService = $eventLogService;
    }

    /**
     * Get online payment handler
     *
     * @param string $sourceIls Source ILS
     *
     * @return HandlerInterface
     *
     * @throws PaymentException
     */
    public function getHandler(string $sourceIls): HandlerInterface
    {
        if (!($handlerName = $this->getHandlerName($sourceIls))) {
            throw new PaymentException("Online payment handler not defined for '$sourceIls'");
        }
        if (!$this->handlerManager->has($handlerName)) {
            throw new PaymentException("Online payment handler '$handlerName' not found for '$sourceIls'");
        }

        $handler = $this->handlerManager->get($handlerName);
        $handler->init($this->getOnlinePaymentConfig($sourceIls));
        return $handler;
    }

    /**
     * Get online payment handler name.
     *
     * @param string $sourceIls Source ILS
     *
     * @return string
     */
    public function getHandlerName(string $sourceIls): string
    {
        if ($config = $this->getOnlinePaymentConfig($sourceIls)) {
            return $config['handler'] ?? '';
        }
        return '';
    }

    /**
     * Check if online payment is enabled for an ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return bool
     */
    public function isEnabled(string $sourceIls): bool
    {
        $config = $this->getOnlinePaymentConfig($sourceIls);
        return (bool)($config['enabled'] ?? false);
    }

    /**
     * Get session for storing payment data.
     *
     * @return SessionContainer
     */
    public function getOnlinePaymentSession(): SessionContainer
    {
        return new \Laminas\Session\Container('OnlinePayment', $this->sessionManager);
    }

    /**
     * Process a response from a payment handler
     *
     * @param PaymentEntityInterface $payment    Payment
     * @param RequestInterface       $request    Request
     * @param bool                   $fromNotify Is the request from notification handler?
     *
     * @return array Associative array with result and markedAsPaid
     *
     * @throws PaymentException
     */
    public function processPaymentHandlerResponse(
        PaymentEntityInterface $payment,
        RequestInterface $request,
        bool $fromNotify
    ): array {
        $paymentHandler = $this->getHandler($payment->getSourceIls());
        $resultCode = $paymentHandler->processPaymentResponse($payment, $request);
        $linkType = $fromNotify ? 'notify handler' : 'backlink';
        $this->debug("Online payment $linkType for " . $payment->getLocalIdentifier() . " result: $resultCode");
        $markedAsPaid = false;
        if (BaseHandler::PAYMENT_SUCCESS === $resultCode) {
            if ($markedAsPaid = $payment->isInProgress()) {
                $payment->setPaid();
                $this->paymentService->persistEntity($payment);
            }
        }

        // Send receipt if the payment was marked as paid and receipt is enabled:
        if (
            $markedAsPaid
            && ($patron = $this->getPatronForPayment($payment))
            && ($paymentConfig = $this->getOnlinePaymentConfig($patron['__source'] ?? 'default'))
            && ($paymentConfig['receipt'] ?? false)
        ) {
            try {
                // Get full profile for receipt:
                $patronProfile = array_merge(
                    $patron,
                    $this->ils->getMyProfile($patron)
                );
                $res = $this->receipt->sendEmail($payment->getUser(), $patronProfile, $payment, $paymentConfig);
                $this->addPaymentEvent($payment, $res ? 'Receipt sent' : 'Receipt not sent (no email address)');
            } catch (\Exception $e) {
                $this->logError(
                    'Failed to send email receipt for ' . $payment->getLocalIdentifier() . ': ' . (string)$e
                );
                $this->addPaymentEvent($payment, 'Sending of receipt failed', ['error' => (string)$e]);
            }
        }

        return compact('resultCode', 'markedAsPaid');
    }

    /**
     * Find patron for a payment
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return array Patron, or null on failure
     */
    public function getPatronForPayment(PaymentEntityInterface $payment): ?array
    {
        if (!($user = $payment->getUser())) {
            return null;
        }

        // Check if user's current credentials match (typical case):
        $catPassword = $this->ilsAuthenticator->getCatPasswordForUser($user);
        if (
            mb_strtolower($user->getCatUsername(), 'UTF-8') === mb_strtolower($payment->getCatUsername(), 'UTF-8')
            && ($patron = $this->ils->patronLogin($user->getCatUsername(), $catPassword))
        ) {
            // Success!
            return $patron;
        }

        // Check for a matching library card:
        $cards = $this->userCardService->getLibraryCards($user, null, $payment->getCatUsername());

        // Make sure to try all cards with a matching user name:
        foreach ($cards as $card) {
            $userCopy = clone $user;
            // Note: these changes are not persisted, so there's no harm in setting them here:
            $userCopy->setCatUsername($card->getCatUsername());
            $userCopy->setRawCatPassword($card->getRawCatPassword());
            $userCopy->setCatPassEnc($card->getCatPassEnc());
            $catPassword = $this->ilsAuthenticator->getCatPasswordForUser($userCopy);

            try {
                if ($patron = $this->ils->patronLogin($userCopy->getCatUsername(), $catPassword)) {
                    // Success!
                    return $patron;
                }
            } catch (\Exception $e) {
                $this->logError('Patron login error: ' . $e->getMessage());
                $this->logException($e);
            }
        }
        return null;
    }

    /**
     * Register the given payment with ILS
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return bool
     */
    public function registerPaymentWithILS(PaymentEntityInterface $payment): bool
    {
        if (!($patron = $this->getPatronForPayment($payment))) {
            $this->logError(
                'Error processing payment id ' . $payment->getId()
                . ': patronLogin error (cat_username: ' . $payment->getCatUsername()
                . ', user id: ' . $payment->getUserId() . ')'
            );

            $payment->setRegistrationFailed('patron login error');
            $this->paymentService->persistEntity($payment);
            $this->addPaymentEvent($payment, 'Patron login failed');
            return false;
        }

        $result = $this->registerPaymentForPatron($payment, $patron);
        if ($result) {
            $this->storePaymentSuccessFlag();
        }
        return $result;
    }

    /**
     * Register a payment with ILS for the given patron
     *
     * @param PaymentEntityInterface $payment Payment
     * @param array                  $patron  Patron information
     *
     * @return bool
     */
    public function registerPaymentForPatron(PaymentEntityInterface $payment, array $patron): bool
    {
        // Check that registration is not already in progress (i.e. registration started within 120 seconds)
        if ($payment->isRegistrationInProgress()) {
            $this->debug(
                '    Payment ' . $payment->getLocalIdentifier() . ' already being registered since '
                . ($payment->getRegistrationStartDate()?->format('Y-m-d H:i:s') ?? '[date missing]')
            );
            $this->addPaymentEvent($payment, 'Payment already being registered');
            return false;
        }

        $payment->setRegistrationStarted();
        $this->paymentService->persistEntity($payment);
        $this->addPaymentEvent($payment, 'Started registration with the ILS');

        $paymentConfig = $this->ils->getConfig('OnlinePayment', $patron);
        $fineIds = $this->paymentService->getFineIds($payment);

        if (
            ($paymentConfig['exactBalanceRequired'] ?? true)
            || !empty($paymentConfig['creditUnsupported'])
        ) {
            try {
                $fines = $this->ils->getMyFines($patron);
                // Filter by fines selected for the payment if fine_id field is available:
                $finesAmount = $this->ils->getOnlinePaymentDetails(
                    $patron,
                    $fines,
                    $fineIds ?: null
                );
            } catch (\Exception $e) {
                $this->logException($e);
                return false;
            }

            // Check that payable sum has not been updated if exact balance is required
            $exact = $paymentConfig['exactBalanceRequired'] ?? true;
            $noCredit = $exact || !empty($paymentConfig['creditUnsupported']);
            if (
                $finesAmount['payable'] && !empty($finesAmount['amount'])
                && (($exact && $payment->getAmount() != $finesAmount['amount'])
                || ($noCredit && $payment->getAmount() > $finesAmount['amount']))
            ) {
                // Payable sum updated. Skip registration and inform user
                // that payment processing has been delayed.
                $this->logError(
                    'Payment ' . $payment->getLocalIdentifier() . ': payable sum updated.'
                    . ' Paid amount: ' . $payment->getAmount() . ', payable: '
                    . var_export($finesAmount, true)
                );
                $payment->setFinesUpdated();
                $this->paymentService->persistEntity($payment);
                $this->addPaymentEvent($payment, 'Registration with the ILS failed: fines updated');
                return false;
            }
        }

        try {
            $this->debug('Payment ' . $payment->getLocalIdentifier() . ': start marking fees as paid.');
            $res = $this->ils->registerPayment(
                $patron,
                $payment->getAmount(),
                $payment->getLocalIdentifier(),
                $payment->getRemoteIdentifier(),
                $payment->getId(),
                ($paymentConfig['selectFines'] ?? false) ? $fineIds : null
            );
            $this->debug(
                'Payment ' . $payment->getLocalIdentifier() . ': done marking fees as paid, result: '
                . var_export($res, true)
            );
            if (true !== $res) {
                $this->logError(
                    'Payment registration error (patron ' . $patron['id'] . '): '
                    . 'registerPayment failed: ' . ($res ?: 'no error information')
                );
                if ('fines_updated' === $res) {
                    $payment->setFinesUpdated();
                    $this->paymentService->persistEntity($payment);
                    $this->addPaymentEvent($payment, 'Registration with the ILS failed: fines updated');
                } else {
                    $payment->setRegistrationFailed('Failed to mark fees paid: ' . ($res ?: 'no error information'));
                    $this->paymentService->persistEntity($payment);
                    $this->addPaymentEvent(
                        $payment,
                        'Registration with the ILS failed: ' . ($res ?: 'no error information')
                    );
                }
                return false;
            }
            $payment->setRegistered();
            $this->paymentService->persistEntity($payment);
            $this->debug("Registration of payment {$payment->getLocalIdentifier()} successful");
            $this->addPaymentEvent($payment, 'Successfully registered with the ILS');
        } catch (\Exception $e) {
            $this->logError('Payment registration error (patron ' . $patron['id'] . '): ' . $e->getMessage());
            $this->logException($e);
            $payment->setRegistrationFailed($e->getMessage());
            $this->paymentService->persistEntity($payment);
            $this->addPaymentEvent($payment, 'Registration with the ILS failed', ['error' => $e->getMessage()]);
            return false;
        }
        return true;
    }

    /**
     * Get online payment configuration for an ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return array
     */
    public function getOnlinePaymentConfig(string $sourceIls): array
    {
        return $this->ils->getConfig('OnlinePayment', ['__source' => $sourceIls]) ?? [];
    }

    /**
     * Get any successful payment flag from session and clear the session
     *
     * @return bool
     */
    public function getAndClearPaymentSuccessFlag(): bool
    {
        $session = $this->getOnlinePaymentSession();
        $result = $session->paymentSuccessful === true;
        unset($session->paymentSuccessful);
        return $result;
    }

    /**
     * Store a flag for successful payment in the session
     *
     * @return void
     */
    protected function storePaymentSuccessFlag(): void
    {
        $this->getOnlinePaymentSession()->paymentSuccessful = true;
    }
}
