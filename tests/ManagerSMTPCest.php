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

class ManagerSMTPCest extends AbstractFunctionalCest
{
    public function _before(): void
    {
        parent::_before();

        $this->config = [
            'driver'   => 'smtp',
            'host'     => $_ENV['DATA_MAILPIT_HOST_URI'],
            'port'     => $_ENV['DATA_MAILPIT_SMTP_PORT'],
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

        $I->assertSame(1, $message->send(), $message->getLastError());
        $I->assertSame([], $message->getFailedRecipients());

        // Get mails sent with the messages from Mailpit
        $mails = $this->getMessages();

        // Check that there is one mail sent
        $I->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $I->assertCount(1, $mailTo);
        $I->assertSame($to, $mailTo[0]->Address);

        $mailFrom = $mail->From;
        $I->assertSame($this->config['from']['email'], $mailFrom->Address);
        $I->assertSame($this->config['from']['name'], $mailFrom->Name);

        $mailMessage = $this->getMessage($mail->ID);
        $I->assertSame($body, $mailMessage->Text);
        $I->assertSame($subject, $mailMessage->Subject);
    }

    /**
     * @test Test sending a mail by creating a message from the manager with view params
     */
    public function mailerManagerCreateMessageFromView(FunctionalTester $I): void
    {
        $mailer = new Manager($this->config);

        $viewPath   = 'mail/signup';
        $viewParams = ['var1' => 'VAR VALUE 1', 'var2' => 'VAR VALUE 2'];
        $to         = 'example_to@gmail.com';
        $subject    = 'Hello SMTPView';

        $message = $mailer->createMessageFromView($viewPath, $viewParams)
            ->to($to)
            ->subject($subject);

        $I->assertSame(1, $message->send(), $message->getLastError());
        $I->assertSame([], $message->getFailedRecipients());

        // Get sent mails with the messages from Mailpit
        $mails = $this->getMessages();

        // Check that there is one mail sent
        $I->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $I->assertCount(1, $mailTo);
        $I->assertSame($to, $mailTo[0]->Address);

        $mailFrom = $mail->From;
        $I->assertSame($this->config['from']['email'], $mailFrom->Address);
        $I->assertSame($this->config['from']['name'], $mailFrom->Name);

        $body = $this->di->get('simple')->render($viewPath, $viewParams);

        $mailMessage = $this->getMessage($mail->ID);
        $I->assertSame($body . "\r\n\r\n", $mailMessage->HTML);
        $I->assertSame($subject, $mail->Subject);
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
        $I->assertSame(1, $message->send(), $message->getLastError());
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
        $I->assertSame(3, $message->send(), $message->getLastError());
        $I->assertSame(1, $eventsCount);
    }

    /**
     * @test Test sending 2 mails which both failed to send -> they are present in the failedRecipients array
     */
    public function mailerManagerCreateMessageFailedRecipients(FunctionalTester $I): void
    {
        $eventsCount = 0;

        $mailer  = new Manager($this->config);
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->subject('Test subject')
            ->content('content');

        // Simulate the error from PHPMailer
        $message->getMessage()->Mailer = 'mail';

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
        $I->assertNotSame('', $message->getLastError());
    }

    /**
     * @test Test sending mail with SMTP errored to connect -> 0 sent mail and a message from PHPMailer
     */
    public function mailerManagerCreateMessageSmtpAuthError(FunctionalTester $I): void
    {
        $mailer  = new Manager(array_merge($this->config, ['host' => 'unknown-host']));
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $I->assertSame(0, $message->send());
        $I->assertNotSame('', $message->getLastError());
    }
}
