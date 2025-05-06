<?php

/**
 * Abstract base class for online payment handlers.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Session\Container as SessionContainer;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Service\PaymentEventLogServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ILS\Connection;
use VuFind\OnlinePayment\OnlinePayment;
use VuFind\OnlinePayment\Receipt;
use VuFind\Session\Settings as SessionSettings;

/**
 * Abstract base class for online payment handlers.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractOnlinePaymentAction extends \VuFind\AjaxHandler\AbstractBase implements
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\OnlinePayment\OnlinePaymentEventLogTrait;
    use \VuFind\OnlinePayment\OnlinePaymentHandlerTrait;

    /**
     * Constructor
     *
     * @param SessionSettings                 $sessionSettings      Session settings
     * @param Connection                      $ils                  ILS connection
     * @param ILSAuthenticator                $ilsAuthenticator     ILS Authenticator
     * @param PaymentServiceInterface         $paymentService       Payment database service
     * @param UserServiceInterface            $userService          User database service
     * @param UserCardServiceInterface        $userCardService      User card database service (for
     *                                                              OnlinePaymentHandlerTrait)
     * @param OnlinePayment                   $onlinePayment        Online payment manager
     * @param SessionContainer                $onlinePaymentSession Online payment session
     * @param array                           $dataSourceConfig     Data source configuration
     * @param Receipt                         $receipt              Receipt
     * @param PaymentEventLogServiceInterface $eventLogService      Payment event log database service
     */
    public function __construct(
        SessionSettings $sessionSettings,
        protected Connection $ils,
        protected ILSAuthenticator $ilsAuthenticator,
        protected PaymentServiceInterface $paymentService,
        protected UserServiceInterface $userService,
        protected UserCardServiceInterface $userCardService,
        protected OnlinePayment $onlinePayment,
        protected SessionContainer $onlinePaymentSession,
        protected array $dataSourceConfig,
        protected Receipt $receipt,
        PaymentEventLogServiceInterface $eventLogService
    ) {
        $this->sessionSettings = $sessionSettings;
        $this->eventLogService = $eventLogService;
    }

    /**
     * Mark fees paid for the given payment
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return bool
     */
    protected function markFeesAsPaidForPayment(PaymentEntityInterface $payment): bool
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

        $result = $this->markFeesAsPaidForPatron($patron, $payment);
        if ($result) {
            $this->onlinePaymentSession->paymentOk = true;
        }
        return $result;
    }

    /**
     * Return online payment handler.
     *
     * @param string $driver Patron MultiBackend ILS source
     *
     * @return mixed \VuFind\OnlinePayment\BaseHandler or false on failure.
     */
    protected function getOnlinePaymentHandler($driver)
    {
        if (!$this->onlinePayment->isEnabled($driver)) {
            return false;
        }
        try {
            return $this->onlinePayment->getHandler($driver);
        } catch (\Exception $e) {
            $this->logError(
                "Error retrieving online payment handler for driver $driver" . ' (' . $e->getMessage() . ')'
            );
            return false;
        }
    }

    /**
     * Log an exception
     *
     * @param \Exception $exception Exception to log
     *
     * @return void
     */
    public function logException(\Exception $exception): void
    {
        if ($this->logger instanceof \VuFind\Log\Logger) {
            $this->logger
                ->logException($exception, new \Laminas\Stdlib\Parameters());
        }
    }

    /**
     * Log a payment info message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function logPaymentInfo(string $msg): void
    {
        $this->logWarning($msg);
    }
}
