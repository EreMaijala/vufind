<?php

/**
 * Console service for processing unregistered online payments.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2024.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace VuFindConsole\Command\Util;

use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Service\PaymentEventLogServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\OnlinePayment\OnlinePaymentEventLogTrait;
use VuFind\OnlinePayment\OnlinePaymentManager;

use function count;
use function intval;

/**
 * Console service for processing unregistered online payments.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
#[AsCommand(
    name: 'util/online_payment_monitor'
)]
class OnlinePaymentMonitor extends Command
{
    use OnlinePaymentEventLogTrait;
    use ConsoleLoggerTrait;

    /**
     * Number of hours before considering unregistered payments to be expired.
     *
     * @var int
     */
    protected $expireHours = 3;

    /**
     * Sender email address for notification of expired payments.
     *
     * @var string
     */
    protected $fromEmail = '';

    /**
     * Payments successfully registered
     *
     * @var int
     */
    protected $registeredCount = 0;

    /**
     * Payments that failed to register
     *
     * @var int
     */
    protected $failedCount = 0;

    /**
     * Expired payments
     *
     * @var int
     */
    protected $expiredCount = 0;

    /**
     * Constructor
     *
     * @param Connection                      $ils                  Catalog connection
     * @param PaymentServiceInterface         $paymentService       Payment database service
     * @param PhpRenderer                     $viewRenderer         View renderer
     * @param Mailer                          $mailer               Mailer
     * @param OnlinePaymentManager            $onlinePaymentManager Online payment manager
     * @param PaymentEventLogServiceInterface $eventLogService      Payment event log database service
     */
    public function __construct(
        protected Connection $ils,
        protected PaymentServiceInterface $paymentService,
        protected PhpRenderer $viewRenderer,
        protected Mailer $mailer,
        protected OnlinePaymentManager $onlinePaymentManager,
        PaymentEventLogServiceInterface $eventLogService,
    ) {
        $this->eventLogService = $eventLogService;
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription(
                'Validate unregistered online payment payments and send error'
                    . ' notifications'
            )
            ->addArgument(
                'expire_hours',
                InputArgument::REQUIRED,
                'Number of hours before considering unregistered payment to be expired.'
            )
            ->addArgument(
                'from_email',
                InputArgument::REQUIRED,
                'Sender email address for notification of expired payments'
            )
            ->addArgument(
                'report_interval_hours',
                InputArgument::REQUIRED,
                'Interval when to re-send report of unresolved payments'
            )
            ->addArgument(
                'minimum_paid_age',
                InputArgument::OPTIONAL,
                "Minimum age of payments in 'paid' status until they are considered failed (seconds, default 120)",
                120
            )
            ->addOption(
                'no-email',
                null,
                InputOption::VALUE_NONE,
                'Disable sending of any email messages'
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->expireHours = $input->getArgument('expire_hours');
        $this->fromEmail = $input->getArgument('from_email');
        $reportIntervalHours = $input->getArgument('report_interval_hours');
        $minimumPaidAge = intval($input->getArgument('minimum_paid_age'));
        $disableEmail = $input->getOption('no-email') ?: false;

        // Abort if we have an invalid minimum paid age.
        if ($minimumPaidAge < 10) {
            $output->writeln('Minimum paid age must be at least 10 seconds');
            return 1;
        }

        $this->msg('Online payment monitor started');
        $failedPayments = $this->paymentService->getFailedPayments($minimumPaidAge);
        foreach ($failedPayments as $payment) {
            $this->processPayment($payment);
        }

        // Report paid and unregistered payments whose registration can not be re-tried:
        $unresolvedPayments = $this->paymentService->getUnresolvedPaymentsToReport($reportIntervalHours);

        if ($this->registeredCount) {
            $this->msg("Total registered: $this->registeredCount");
        }
        if ($this->expiredCount) {
            $this->msg("Total expired: $this->expiredCount");
        }
        if ($this->failedCount) {
            $this->msg("Total failed: $this->failedCount");
        }

        if (!$disableEmail && $unresolvedPayments) {
            $this->msg('Total to be reminded: ' . count($unresolvedPayments));
            $this->sendReports($unresolvedPayments);
        }

        $this->msg('Online payment monitor completed');

        return 0;
    }

    /**
     * Try to register a payment that wasn't previously registered successfully.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return void
     */
    protected function processPayment(PaymentEntityInterface $payment): void
    {
        $this->msg(
            "Registering payment id {$payment->getId()} / {$payment->getLocalIdentifier()}"
            . " (status: {$payment->getStatus()->value} / {$payment->getStatusMessage()}"
            . ", paid: {$payment->getPaidDate()->format('Y-m-d H:i:s')})"
        );

        // Check if the payment has remained unregistered for too long
        $now = new \DateTime();
        $diff = $now->diff($payment->getPaidDate());
        $diffHours = ($diff->days * 24) + $diff->h;
        if ($diffHours > $this->expireHours) {
            // Payment has expired
            $payment->setExpired();
            $this->paymentService->persistEntity($payment);
            $this->addPaymentEvent($payment, 'Marked as expired');
            $this->msg('Payment ' . $payment->getLocalIdentifier() . ' marked as expired.');
            return;
        }

        try {
            $user = $payment->getUser();
            if (!($patron = $this->onlinePaymentManager->getPatronForPayment($payment))) {
                if ($user) {
                    $this->warn(
                        "Catalog login failed for user {$user->getUsername()} (id {$user->getId()}),"
                        . " card {$payment->getCatUsername()}"
                    );
                    $payment->setRegistrationFailed('patron login error');
                    $this->paymentService->persistEntity($payment);
                    $this->addPaymentEvent($payment, 'Patron login failed');
                } else {
                    $this->warn(
                        "Library card not found for user {$payment->getUserId()}, card {$payment->getCatUsername()}"
                    );
                    $payment->setRegistrationFailed('card not found');
                    $this->paymentService->persistEntity($payment);
                    $this->addPaymentEvent(
                        $payment,
                        "Library card not found for user id {$payment->getUserId()}",
                        [
                            'user_id' => $payment->getUserId(),
                            'card' => $payment->getCatUsername(),
                        ]
                    );
                }
                ++$this->failedCount;
                return;
            }

            if (!$this->onlinePaymentManager->registerPaymentForPatron($payment, $patron)) {
                ++$this->failedCount;
                return;
            }
            ++$this->registeredCount;
            return;
        } catch (\Exception $e) {
            $this->warn(
                "Exception while processing transaction {$payment->getId()} for user id {$payment->getUserId()}"
                . ", card {$payment->getCatUsername()}: "
                . (string)$e
            );
            $this->addPaymentEvent(
                $payment,
                'Exception while processing transaction',
                [
                    'exception' => (string)$e,
                ]
            );
            ++$this->failedCount;
            return;
        }
    }

    /**
     * Send email reports of unresolved payments that need to be resolved manually.
     *
     * @param array $payments Payments to be reported.
     *
     * @return void
     */
    protected function sendReports($payments)
    {
        foreach ($payments as $source => $sourcePayments) {
            $errorCount = count($sourcePayments);
            if ($errorCount) {
                if (!($recipient = $this->getErrorEmail($source))) {
                    $this->err(
                        "  No error email for expired payments defined for $source ($errorCount errors)",
                        '='
                    );
                    continue;
                }
                $this->msg("[$source] Inform $errorCount expired payments to $recipient");

                $adminUrl = ($this->viewRenderer->plugin('url'))('admin-payments');
                $params = compact('source', 'errorCount', 'adminUrl');
                $message = $this->viewRenderer->render('Email/online-payment-alert.phtml', $params);

                try {
                    $this->mailer->setMaxRecipients(0);
                    $this->mailer->send(
                        $recipient,
                        $this->fromEmail,
                        '',
                        $message
                    );
                    foreach ($sourcePayments as $payment) {
                        $payment->setReported();
                        $this->paymentService->persistEntity($payment);
                    }
                } catch (\Exception $e) {
                    $this->err(
                        "    Failed to send error email to staff at $recipient (source: $source)",
                        'Failed to send error email to staff'
                    );
                    $this->logException($e);
                    continue;
                }
            }
        }
    }

    /**
     * Get error email recipient address for a source ILS
     *
     * @param string $source Source ILS
     *
     * @return string
     */
    protected function getErrorEmail(string $source): string
    {
        $paymentConfig = $this->ils->getConfig('OnlinePayment', ['__source' => $source]);
        return $paymentConfig['errorEmail'] ?? '';
    }

    /**
     * Log a payment debug message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function debug(string $msg): void
    {
        $this->msg($msg);
    }
}
