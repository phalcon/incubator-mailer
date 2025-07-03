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

namespace Phalcon\Incubator\Mailer\Tests;

use Phalcon\Events\Manager as EventsManager;
use Phalcon\Incubator\Mailer\Manager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

class ManagerSMTPTest extends TestCase
{
    protected array $config;

    /** Setup the config for the SMTP tests */
    protected function setUp(): void
    {
        parent::setUp();

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

    #[Test]
    #[TestDox('Test sending a mail by creating a message from the manager')]
    public function mailerManagerCreateMessage(): void
    {
        $to      = 'example_to@gmail.com';
        $subject = 'Hello SMTP';
        $body    = 'Lorem Ipsum';

        $mailer = new Manager($this->config);

        $message = $mailer->createMessage()
            ->to($to)
            ->subject($subject)
            ->content($body);

        $this->assertSame(1, $message->send(), $message->getLastError());
        $this->assertSame([], $message->getFailedRecipients());

        // Get mails sent with the messages from Mailpit
        $mails = $this->getMessages();

        // Check that there is one mail sent
        $this->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $this->assertCount(1, $mailTo);
        $this->assertSame($to, $mailTo[0]->Address);

        $mailFrom = $mail->From;
        $this->assertSame($this->config['from']['email'], $mailFrom->Address);
        $this->assertSame($this->config['from']['name'], $mailFrom->Name);

        $mailMessage = $this->getMessage($mail->ID);
        $this->assertSame($body, $mailMessage->Text);
        $this->assertSame($subject, $mailMessage->Subject);
    }

    #[Test]
    #[TestDox('Test sending a mail by creating a message from the manager with view params')]
    public function mailerManagerCreateMessageFromView(): void
    {
        $mailer = new Manager($this->config);

        $viewPath   = 'mail/signup';
        $viewParams = ['var1' => 'VAR VALUE 1', 'var2' => 'VAR VALUE 2'];
        $to         = 'example_to@gmail.com';
        $subject    = 'Hello SMTPView';

        $message = $mailer->createMessageFromView($viewPath, $viewParams)
            ->to($to)
            ->subject($subject);

        $this->assertSame(1, $message->send(), $message->getLastError());
        $this->assertSame([], $message->getFailedRecipients());

        // Get sent mails with the messages from Mailpit
        $mails = $this->getMessages();

        // Check that there is one mail sent
        $this->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $this->assertCount(1, $mailTo);
        $this->assertSame($to, $mailTo[0]->Address);

        $mailFrom = $mail->From;
        $this->assertSame($this->config['from']['email'], $mailFrom->Address);
        $this->assertSame($this->config['from']['name'], $mailFrom->Name);

        $body = $this->di->get('simple')->render($viewPath, $viewParams);

        $mailMessage = $this->getMessage($mail->ID);
        $this->assertSame($body . "\r\n\r\n", $mailMessage->HTML);
        $this->assertSame($subject, $mail->Subject);
    }

    #[Test]
    #[TestDox('Test sending a mail with an event manager set -> both events from ::send() triggered')]
    public function mailerManagerCreateMessageWithEventsOneMailSent(): void
    {
        $eventsCount = 0;

        $mailer  = new Manager($this->config);
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeSend', function ($event, $manager, $params) use (&$eventsCount) {
            $this->assertNull($params);
            $eventsCount++;
        });

        $eventsManager->attach('mailer:afterSend', function ($event, $manager, $params) use (&$eventsCount) {
            $this->assertIsArray($params);
            $this->assertCount(2, $params);
            $this->assertIsInt($params[0]);
            $this->assertSame(1, $params[0]);
            $this->assertIsArray($params[1]);
            $this->assertSame([], $params[1]);
            $eventsCount++;
        });

        $mailer->setEventsManager($eventsManager);

        // Both events have been triggered and asserted
        $this->assertSame(1, $message->send(), $message->getLastError());
        $this->assertSame(2, $eventsCount);
    }

    #[Test]
    #[TestDox('Test sending 3 mails with an event manager set -> afterSend has 3 counts and no failedRecipients')]
    public function mailerManagerCreateMessageWithEventsThreeMailsSent(): void
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

        $eventsManager->attach('mailer:afterSend', function ($event, $manager, $params) use (&$eventsCount) {
            $this->assertIsArray($params);
            $this->assertCount(2, $params);
            $this->assertIsInt($params[0]);
            $this->assertSame(3, $params[0]);
            $this->assertIsArray($params[1]);
            $this->assertSame([], $params[1]);
            $eventsCount++;
        });

        $mailer->setEventsManager($eventsManager);

        // Event has been triggered and asserted
        $this->assertSame(3, $message->send(), $message->getLastError());
        $this->assertSame(1, $eventsCount);
    }

    #[Test]
    #[TestDox('Test sending 2 mails which both failed to send -> they are present in the failedRecipients array')]
    public function mailerManagerCreateMessageFailedRecipients(): void
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

        $eventsManager->attach('mailer:afterSend', function ($event, $manager, $params) use (&$eventsCount) {
            $this->assertIsArray($params);
            $this->assertCount(2, $params);
            $this->assertIsInt($params[0]);
            $this->assertSame(0, $params[0]);
            $this->assertIsArray($params[1]);
            $this->assertSame(['example_to@gmail.com', 'example_to2@gmail.com'], $params[1]);
            $eventsCount++;
        });

        $mailer->setEventsManager($eventsManager);

        $this->assertSame(0, $message->send());
        $this->assertSame(1, $eventsCount);
        $this->assertSame(['example_to@gmail.com', 'example_to2@gmail.com'], $message->getFailedRecipients());
        $this->assertNotSame('', $message->getLastError());
    }

    #[Test]
    #[TestDox('Test sending mail with SMTP errored to connect -> 0 sent mail and a message from PHPMailer')]
    public function mailerManagerCreateMessageSmtpAuthError(): void
    {
        $mailer  = new Manager(array_merge($this->config, ['host' => 'unknown-host']));
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $this->assertSame(0, $message->send());
        $this->assertNotSame('', $message->getLastError());
    }
}
