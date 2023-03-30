<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Incubator\Mailer\Tests\Functional;

use FunctionalTester;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Incubator\Mailer\Manager;
use PHPMailer\PHPMailer\Exception;

class ManagerSMTPCest extends AbstractFunctionalCest
{
    public function _before(): void
    {
        parent::_before();

        $this->config = [
            'driver'   => 'smtp',
            'host'     => 'mailhog',
            'port'     => '1025',
            'username' => 'example@gmail.com',
            'password' => 'your_password',
            'from'     => [
                'email' => 'example_smtp@gmail.com',
                'name'  => 'EXAMPLE SMTP'
            ]
        ];
    }

    /**
     * @test Test sending a mail by creating a message from the manager
     */
    public function mailerManagerCreateMessage(FunctionalTester $I)
    {
        $to      = 'example_to@gmail.com';
        $subject = 'Hello SMTP';
        $body    = 'Lorem Ipsum';

        $mailer = new Manager($this->config);

        $message = $mailer->createMessage()
            ->to($to)
            ->subject($subject)
            ->content($body);

        $I->assertSame(1, $message->send());
        $I->assertSame([], $message->getFailedRecipients());

        // Get mails sent with the messages from MailHog
        $mails = $this->getMailsFromMailHog();

        // Check that there is one mail sent
        $I->assertCount(1, $mails);
        $I->assertInstanceOf('\stdClass', $mails[0]);

        $mail = $mails[0];

        $mailTo = $mail->To;
        $I->assertCount(1, $mailTo);
        $I->assertInstanceOf('\stdClass', $mailTo[0]);
        $I->assertSame($to, $mailTo[0]->Mailbox . '@' . $mailTo[0]->Domain);

        $mailFrom = $mail->From;
        $I->assertInstanceOf('\stdClass', $mailFrom);
        $I->assertSame($this->config['from']['email'], $mailFrom->Mailbox . '@' . $mailFrom->Domain);

        $I->assertSame($body . "\r\n", $mail->Content->Body);
        $I->assertStringContainsString('Subject: ' . $subject, $mail->Raw->Data);
    }

    /**
     * @test Test sending a mail by creating a message from the manager with view params
     */
    public function mailerManagerCreateMessageFromView(FunctionalTester $I): void
    {
        $mailer = new Manager($this->config);

        // view relative to the folder viewsDir (REQUIRED)
        $viewPath = 'mail/signup';

        // Set variables to views (OPTIONAL)
        $params = [
            'var1' => 'VAR VALUE 1',
            'var2' => 'VAR VALUE 2'
        ];

        $to      = 'example_to@gmail.com';
        $subject = 'Hello SMTPView';

        $message = $mailer->createMessageFromView($viewPath, $params)
            ->to($to)
            ->subject($subject);

        $I->assertSame(1, $message->send());
        $I->assertSame([], $message->getFailedRecipients());

        // Get mails sent with the messages from MailHog
        $mails = $this->getMailsFromMailHog();

        // Check that there is one mail sent
        $I->assertCount(1, $mails);
        $I->assertInstanceOf('\stdClass', $mails[0]);

        $mail = $mails[0];

        $mailTo = $mail->To;
        $I->assertCount(1, $mailTo);
        $I->assertInstanceOf('\stdClass', $mailTo[0]);
        $I->assertSame($to, $mailTo[0]->Mailbox . '@' . $mailTo[0]->Domain);

        $mailFrom = $mail->From;
        $I->assertInstanceOf('\stdClass', $mailFrom);
        $I->assertSame($this->config['from']['email'], $mailFrom->Mailbox . '@' . $mailFrom->Domain);

        $body = $this->di->get('simple')->render($viewPath, $params);

        $I->assertSame($body . "\r\n", $mail->Content->Body);
        $I->assertStringContainsString('Subject: ' . $subject, $mail->Raw->Data);
    }

    /**
     * @test Test sending a mail with an event manager set -> both events from ::send() triggered
     */
    public function mailerManagerCreateMessageWithEventsOneMailSent(FunctionalTester $I): void
    {
        $eventsCount = 0;

        $mailer  = new Manager($this->config);
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeSend', function ($event, $manager, $params) use ($I, &$eventsCount) {
            $I->assertNull($params);

            $eventsCount++;
        });

        $eventsManager->attach('mailer:afterSend', function ($event, $manager, $params) use ($I, &$eventsCount) {
            $I->assertIsArray($params);
            $I->assertCount(2, $params);

            $I->assertIsInt($params[0]);
            $I->assertSame(1, $params[0]);

            $I->assertIsArray($params[1]);
            $I->assertSame([], $params[1]);

            $eventsCount++;
        });

        $mailer->setEventsManager($eventsManager);

        // Both events have been triggered and asserted
        $I->assertSame(1, $message->send());
        $I->assertSame(2, $eventsCount);
    }

    /**
     * @test Test sending 3 mails with an event manager set -> afterSend has 3 counts and no failedRecipients
     */
    public function mailerManagerCreateMessageWithEventsThreeMailsSent(FunctionalTester $I): void
    {
        $eventsCount = 0;

        $mailer  = new Manager($this->config);
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->to('example_to3@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $eventsManager = new EventsManager();

        $eventsManager->attach('mailer:afterSend', function ($event, $manager, $params) use ($I, &$eventsCount) {
            $I->assertIsArray($params);
            $I->assertCount(2, $params);

            $I->assertIsInt($params[0]);
            $I->assertSame(3, $params[0]);

            $I->assertIsArray($params[1]);
            $I->assertSame([], $params[1]);

            $eventsCount++;
        });

        $mailer->setEventsManager($eventsManager);

        // Event has been triggered and asserted
        $I->assertSame(3, $message->send());
        $I->assertSame(1, $eventsCount);
    }

    /**
     * @test Test sending 2 mails which both failed to send -> they are present in the failedRecipients array
     */
    public function mailerManagerCreateMessageFailedRecipients(FunctionalTester $I): void
    {
        $eventsCount = 0;

        $mailer  = new Manager(array_merge($this->config, ['host' => 'mailhog-fail-recipients']));
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $eventsManager = new EventsManager();

        $eventsManager->attach('mailer:afterSend', function ($event, $manager, $params) use ($I, &$eventsCount) {
            $I->assertIsArray($params);
            $I->assertCount(2, $params);

            $I->assertIsInt($params[0]);
            $I->assertSame(0, $params[0]);

            $I->assertIsArray($params[1]);
            $I->assertSame(['example_to@gmail.com', 'example_to2@gmail.com'], $params[1]);

            $eventsCount++;
        });

        $mailer->setEventsManager($eventsManager);

        $I->assertSame(0, $message->send());
        $I->assertSame(1, $eventsCount);
        $I->assertSame(['example_to@gmail.com', 'example_to2@gmail.com'], $message->getFailedRecipients());
    }

    /**
     * @test Test sending mail with SMTP errored to connect -> exception from PHPMailer
     */
    public function mailerManagerCreateMessageSmtpAuthError(FunctionalTester $I): void
    {
        $mailer  = new Manager(array_merge($this->config, ['host' => 'unknown-host']));
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->subject('Test subject')
            ->content('content');

        // Exception thrown by PHPMailer
        $I->expectThrowable(Exception::class, fn () => $message->send());
    }
}
