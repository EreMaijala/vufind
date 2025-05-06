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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Service\PaymentEventLogServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\OnlinePayment\OnlinePaymentEventLogTrait;
use VuFind\OnlinePayment\OnlinePaymentHandlerTrait;

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
    use OnlinePaymentHandlerTrait;
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
     * @param \VuFind\ILS\Connection             $ils              Catalog connection
     * @param ILSAuthenticator                   $ilsAuthenticator ILS Authenticator
     * @param PaymentServiceInterface            $paymentService   Payment database service
     * @param UserServiceInterface               $userService      User database service
     * @param UserCardServiceInterface           $userCardService  User card database service (for
     *                                                             OnlinePaymentHandlerTrait)
     * @param \VuFind\Config\Config              $datasourceConfig Data source config
     * @param \Laminas\View\Renderer\PhpRenderer $viewRenderer     View renderer
     * @param \VuFind\Mailer\Mailer              $mailer           Mailer
     * @param PaymentEventLogServiceInterface    $eventLogService  Payment event log database service
     */
    public function __construct(
        protected \VuFind\ILS\Connection $ils,
        protected ILSAuthenticator $ilsAuthenticator,
        protected PaymentServiceInterface $paymentService,
        protected UserServiceInterface $userService,
        protected UserCardServiceInterface $userCardService,
        protected \VuFind\Config\Config $datasourceConfig,
        protected \Laminas\View\Renderer\PhpRenderer $viewRenderer,
        protected \VuFind\Mailer\Mailer $mailer,
        PaymentEventLogServiceInterface $eventLogService
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
            "Registering payment id {$payment->getId()} / {$payment->getPaymentIdentifier()}"
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
            $this->msg('Payment ' . $payment->getPaymentIdentifier() . ' marked as expired.');
            return;
        }

        try {
            $user = $payment->getUser();
            if (!($patron = $this->getPatronForPayment($payment))) {
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

            if (!$this->markFeesAsPaidForPatron($patron, $payment)) {
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
                $settings = $this->ils->getConfig(
                    'onlinePayment',
                    ['id' => "$source.123"]
                );
                if (!$settings || empty($settings['errorEmail'])) {
                    if (!empty($this->datasourceConfig[$source]['feedbackEmail'])) {
                        $settings['errorEmail'] = $this->datasourceConfig[$source]['feedbackEmail'];
                        $this->warn(
                            "  No error email for expired payments defined for source $source, using feedback email"
                            . " ($errorCount expired payments)"
                        );
                    } else {
                        $this->err(
                            "  No error email for expired transactions defined for driver $source"
                            . " ($errorCount expired transactions)",
                            '='
                        );
                        continue;
                    }
                }

                $email = $settings['errorEmail'];
                $this->msg("[$source] Inform $errorCount expired transactions for driver $source to $email");

                $adminUrl = ($this->viewRenderer->plugin('url'))('admin-payments');
                $params = compact('source', 'errorCount', 'adminUrl');
                $message = $this->viewRenderer->render('Email/online-payment-alert.phtml', $params);

                try {
                    $this->mailer->setMaxRecipients(0);
                    $this->mailer->send(
                        $email,
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
                        "    Failed to send error email to staff: $email (source: $source)",
                        'Failed to send error email to staff'
                    );
                    $this->logException($e);
                    continue;
                }
            }
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
        $this->msg($msg);
    }
}
