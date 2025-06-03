<?php

/**
 * Mink online payment actions test class.
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
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\Element;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\PaymentEventEntityInterface;
use VuFind\Db\Service\PaymentEventServiceInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Type\PaymentStatus;
use VuFindTest\Feature\DemoDriverTestTrait;
use VuFindTest\Feature\EmailTrait;
use VuFindTest\Feature\LiveDatabaseTrait;
use VuFindTest\Feature\UserCreationTrait;

use function assert;

/**
 * Mink online payment actions test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class PaymentTest extends \VuFindTest\Integration\MinkTestCase
{
    use DemoDriverTestTrait;
    use EmailTrait;
    use LiveDatabaseTrait;
    use UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Test disabled payment.
     *
     * @return void
     */
    public function testPaymentDisabled(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        $page = $this->goToFines(true);

        $this->unFindCss($page, '.online-payment');
    }

    /**
     * Data provider for testPayment
     *
     * @return array
     */
    public static function paymentProvider(): array
    {
        return [
            [
                [],
                true,
            ],
            [
                ['receipt' => false],
                false,
            ],
        ];
    }

    /**
     * Test payment.
     *
     * @param array $paymentSettings Additional online payment settings
     * @param bool  $receiptEnabled  Receipt enabled?
     *
     * @return void
     *
     * @dataProvider paymentProvider
     * @depends      testPaymentDisabled
     */
    public function testPayment(array $paymentSettings, bool $receiptEnabled): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides() + $this->getDemoIniOverridesForPayment($paymentSettings),
            ]
        );
        $this->resetEmailLog();

        $page = $this->goToFines(false);

        $this->findCss($page, '.online-payment');
        $this->clickCss($page, '.checkbox-select-all');
        $this->assertEquals(
            'Pay Online $15.00',
            $this->findCss($page, '.js-pay-selected')->getValue()
        );
        // Test cancel from dialog:
        $this->clickCss($page, '.js-pay-selected');
        $this->assertLightboxTitle($page, 'Accept Terms to Continue Payment');
        $this->clickCss($page, '#modal .btn.btn-primary');
        $this->assertEquals(
            'Pay Online',
            trim($this->findCss($page, '.js-pay-selected')->getValue())
        );

        // Test cancel from payment service:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $localIdentifier = $this->getLocalIdentifierFromReturnUrl($page);
        $this->clickCss($page, '.button-cancel');
        $this->assertEquals(
            'Payment canceled',
            $this->findCssAndGetText($page, '.alert.alert-success')
        );
        $this->assertEquals(
            PaymentStatus::Canceled,
            $this->getPaymentByLocalIdentifier($localIdentifier)->getStatus()
        );

        // Test failure from payment service:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $localIdentifier = $this->getLocalIdentifierFromReturnUrl($page);
        $this->clickCss($page, '.button-failure');
        $this->assertEquals(
            'Payment request failed',
            $this->findCssAndGetText($page, '.alert.alert-danger')
        );
        $this->assertEquals(
            PaymentStatus::PaymentFailed,
            $this->getPaymentByLocalIdentifier($localIdentifier)->getStatus()
        );

        // Test success from payment service:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $localIdentifier = $this->getLocalIdentifierFromReturnUrl($page);
        $this->clickCss($page, '.button-success');
        $this->assertEquals(
            'Payment successful',
            $this->findCssAndGetText($page, '.alert.alert-success')
        );
        // Wait for the "Processing Payment" info alert to disappear:
        $this->unFindCss($page, '.alert.alert-info');
        $this->waitForPageLoad($page);
        $this->assertCount(
            2,
            $page->findAll('css', '.fines-table tbody tr')
        );

        if ($receiptEnabled) {
            $this->assertStringStartsWith(
                'Last Paid: $15.00',
                $this->findCssAndGetText($page, '.last-payment-information')
            );
        } else {
            $this->unFindCss($page, '.last-payment-information');
        }
        $payment = $this->getPaymentByLocalIdentifier($localIdentifier);
        $this->assertEquals(
            PaymentStatus::Completed,
            $payment->getStatus()
        );

        // Check receipt email:
        if ($receiptEnabled) {
            $email = $this->getLoggedEmail();
            $this->assertStringContainsString(
                'A receipt for your payment is attached as a PDF file',
                $email->getBody()->getParts()[0]->getBody()
            );
        }

        // Verify database contents:
        $this->assertEquals(
            1500,
            $payment->getAmount()
        );
        $paymentFeeService = $this->getDbService(PaymentFeeServiceInterface::class);
        assert($paymentFeeService instanceof PaymentFeeServiceInterface);
        $this->assertEquals(
            [
                'demo1',
                'demo2',
            ],
            $paymentFeeService->getFineIdsForPayment($payment)
        );
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        assert($paymentService instanceof PaymentServiceInterface);
        $this->assertSame(
            $payment,
            $paymentService->getLastPaidPaymentForPatron('catuser')
        );
        $paymentEventService = $this->getDbService(PaymentEventServiceInterface::class);
        assert($paymentEventService instanceof PaymentEventServiceInterface);
        $events = array_map(
            function (PaymentEventEntityInterface $event) {
                return $event->getMessage();
            },
            $paymentEventService->getEventsForPayment($payment)
        );
        $expectedEvents = [
            'Successfully registered with the ILS',
            'Started registration with the ILS',
            'Registration requested',
            'Response handler called',
            'Receipt sent',
            'Payment marked as paid',
            'Notify handler called',
            'Redirected to payment service',
            'Payment created',
        ];
        if (!$receiptEnabled) {
            $receiptKey = array_search('Receipt sent', $expectedEvents);
            unset($expectedEvents[$receiptKey]);
            $expectedEvents = array_values($expectedEvents);
        }

        $this->assertEquals($expectedEvents, $events);
    }

    /**
     * Test payment without returning to VuFind.
     *
     * @return void
     *
     * @depends testPaymentDisabled
     */
    public function testNotify(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides() + $this->getDemoIniOverridesForPayment(),
            ]
        );

        $page = $this->goToFines(false);

        $this->findCss($page, '.online-payment');
        $this->clickCss($page, '.checkbox-select-all');
        $this->assertEquals(
            'Pay Online $15.00',
            $this->findCss($page, '.js-pay-selected')->getValue()
        );

        // Test success from payment service:
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $this->waitForPageLoad($page);

        // Check payment status:
        $payment = $this->getPaymentFromReturnUrl($page);
        $this->assertEquals(
            $payment->getStatus(),
            PaymentStatus::InProgress
        );

        // Send notify event:
        $this->clickCss($page, '.button-notify');
        $this->assertEqualsWithTimeout(
            'Notify done',
            function () use ($page) {
                return $this->findCssAndGetText($page, 'body');
            }
        );

        // Check payment status again:
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        assert($paymentService instanceof PaymentServiceInterface);
        $paymentService->refreshEntity($payment);
        $this->assertEquals(
            $payment->getStatus(),
            PaymentStatus::Paid
        );

        // Resolve the payment so that it doesn't block further tests:
        $payment->setRegistrationResolved();
        $paymentService->persistEntity($payment);
    }

    /**
     * Test last payment info when there are no fines.
     *
     * @return void
     *
     * @depends testPaymentDisabled
     */
    public function testLastPaymentInfo(): void
    {
        $demoConfig = $this->getDemoIniOverrides() + $this->getDemoIniOverridesForPayment();
        $demoConfig['Records']['fines'] = json_encode([]);
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $demoConfig,
            ]
        );

        $page = $this->goToFines(false);

        $this->assertStringStartsWith(
            'Last Paid: $15.00',
            $this->findCssAndGetText($page, '.last-payment-information')
        );
    }

    /**
     * Data provider for testBlockedPayment
     *
     * @return array
     */
    public static function blockedPaymentProvider(): array
    {
        $blockMsg = 'You have fees that cannot be paid online. Please contact the library customer service.';
        return [
            [
                [
                    'blockingNonPayableTypes' => ['Overdue'],
                ],
                $blockMsg,
            ],
            [
                [
                    'blockingNonPayableDescriptions' => ['Lost card replacement'],
                ],
                $blockMsg,
            ],
            [
                [
                    'blockingNonPayableDescriptions' => ['/Lost.*replacement/'],
                ],
                $blockMsg,
            ],
            [
                [
                    'minimumFee' => '5000',
                ],
                'Minimum Payable Amount: $50.00',
            ],
        ];
    }

    /**
     * Test rules that block payment.
     *
     * @param array  $paymentSettings Additional online payment settings
     * @param string $expectedMsg     Expected block message
     *
     * @return void
     *
     * @dataProvider blockedPaymentProvider
     * @depends      testPaymentDisabled
     */
    public function testBlockedPayment(array $paymentSettings, string $expectedMsg): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides() + $this->getDemoIniOverridesForPayment($paymentSettings),
            ]
        );

        $page = $this->goToFines(false);
        $this->assertEquals(
            $expectedMsg,
            $this->findCssAndGetText($page, '.fines-info-area__blocked')
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1']);
    }

    /**
     * Log in and display fines
     *
     * @param bool $createAccount Do we need a new user account?
     *
     * @return DocumentElement
     */
    protected function goToFines(bool $createAccount): DocumentElement
    {
        // Go to user profile screen:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/MyResearch/Fines');
        $page = $session->getPage();

        // Set up user account if necessary:
        if ($createAccount) {
            $this->clickCss($page, '.createAccountLink');
            $this->fillInAccountForm($page);
            $this->clickCss($page, 'input.btn.btn-primary');

            // Link ILS profile:
            $this->submitCatalogLoginForm($page, 'catuser', 'catpass');
        } else {
            $this->fillInLoginForm($page, 'username1', 'test', false);
            $this->clickCss($page, 'input.btn.btn-primary');
        }

        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Get config.ini override settings for testing ILS functions.
     *
     * @param array $demoOverrides Additional Demo driver overrides
     *
     * @return array
     */
    protected function getConfigIniOverrides(array $demoOverrides = []): array
    {
        return [
            'Catalog' => [
                'driver' => 'Demo',
                'holds_mode' => 'driver',   // needed to display login link
            ],
            'Mail' => [
                'testOnly' => true,
                'message_log' => $this->getEmailLogPath(),
                'message_log_format' => $this->getEmailLogFormat(),
            ],
            'Demo' => $demoOverrides,
        ];
    }

    /**
     * Get Demo.ini override settings for enabling payment.
     *
     * @param array $additional Additional settings
     *
     * @return array
     */
    protected function getDemoIniOverridesForPayment(array $additional = []): array
    {
        return [
            'OnlinePayment' => array_merge(
                [
                    'enabled' => true,
                    'currency' => 'USD',
                    'selectFines' => true,
                    'productCodeMappings' => 'Overdue=demo_003:Long Overdue=demo_004',
                    'handler' => 'Test',
                    'url' => $this->getVuFindUrl('/devtools/payment'),
                ],
                $additional
            ),
        ];
    }

    /**
     * Get fine JSON for Demo.ini.
     *
     * @param string $bibId Bibliographic record ID to create fake item info for.
     *
     * @return array
     */
    protected function getFakeFines(string $bibId)
    {
        return json_encode([
            // Minimal record:
            [
                'amount' => 123,
                'balance' => 123,
                'checkout' => date('Y-m-d', strtotime('now -30 days')),
                'createdate' => date('Y-m-d', strtotime('now -2 days')),
                'duedate' => date('Y-m-d', strtotime('now -5 days')),
                'description' => 'Overdue fee',
                'id' => $bibId,
            ],
            // Payable:
            [
                'fine_id' => 'demo1',
                'amount' => 150,
                'balance' => 150,
                'checkout' => date('Y-m-d', strtotime('now -30 days')),
                'createdate' => date('Y-m-d', strtotime('now -2 days')),
                'fine' => 'Overdue',
                'description' => 'Overdue description',
                'payable_online' => true,
            ],
            [
                'fine_id' => 'demo2',
                'amount' => 1350,
                'balance' => 1350,
                'checkout' => date('Y-m-d', strtotime('now -60 days')),
                'createdate' => date('Y-m-d', strtotime('now -4 days')),
                'fine' => 'Overdue',
                'description' => 'Overdue description',
                'payable_online' => true,
            ],
            // Potentially unpayable:
            [
                'fine_id' => 'demo3',
                'amount' => 350,
                'balance' => 350,
                'createdate' => date('Y-m-d', strtotime('now -2 days')),
                'fine' => 'Manual',
                'description' => 'Lost card replacement',
                'payable_online' => false,
            ],
        ]);
    }

    /**
     * Get the local identifier from the returl URL of the payment service
     *
     * @param Element $page Page
     *
     * @return string
     */
    protected function getLocalIdentifierFromReturnUrl(Element $page): string
    {
        $returnUrl = $this->findCss($page, 'input[name="returnUrl"]')->getValue();
        parse_str(parse_url($returnUrl, PHP_URL_QUERY), $queryParams);
        $this->assertArrayHasKey('local_payment_id', $queryParams);
        return $queryParams['local_payment_id'];
    }

    /**
     * Get a payment entity by the local identifier in the returl URL of the payment service
     *
     * @param string $localIdentifier Local identifier
     *
     * @return PaymentEntityInterface
     */
    protected function getPaymentByLocalIdentifier(string $localIdentifier): PaymentEntityInterface
    {
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        assert($paymentService instanceof PaymentServiceInterface);
        return $paymentService->getPaymentByLocalIdentifier($localIdentifier);
    }

    /**
     * Get a payment entity by the local identifier in the returl URL of the payment service
     *
     * @param Element $page Page
     *
     * @return PaymentEntityInterface
     */
    protected function getPaymentFromReturnUrl(Element $page): PaymentEntityInterface
    {
        return $this->getPaymentByLocalIdentifier($this->getLocalIdentifierFromReturnUrl($page));
    }
}
