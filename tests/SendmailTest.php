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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Incubator\Mailer\Manager;
use Phalcon\Incubator\Mailer\Message;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Manager::class)]
#[CoversClass(Message::class)]
class SendmailTest extends TestCase
{
    protected array $config;

    /** Setup the config for the Sendmail tests */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'driver'    => 'sendmail',
            'sendmail'  => sprintf(
                'sendmail --smtp-addr %s:%s',
                $_ENV['DATA_MAILPIT_HOST_URI'],
                $_ENV['DATA_MAILPIT_SMTP_PORT']
            ),
            'from'      => [
                'email' => 'example_sendmail@gmail.com',
                'name'  => 'EXAMPLE SENDMAIL'
            ],
            'encryption' => null
        ];
    }

    #[Test]
    #[TestDox('Test sending a mail by creating a message from the manager')]
    public function mailerManagerCreateMessage(): void
    {
        $to      = 'example_to@gmail.com';
        $subject = 'Hello Sendmail';
        $body    = 'Lorem Ipsum';

        $mailer = new Manager($this->config);

        $message = $mailer->createMessage()
            ->to($to, 'John Doe')
            ->subject($subject)
            ->content($body);

        $this->assertSame(1, $message->send(), $message->getLastError());
        $this->assertSame([], $message->getFailedRecipients());

        // Get sent mails with the messages API
        $mails = $this->getMessages();

        // Asserting
        $this->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $this->assertCount(1, $mailTo);
        $this->assertSame($to, $mailTo[0]->Address);
        $this->assertSame('John Doe', $mailTo[0]->Name);

        $mailFrom = $mail->From;
        $this->assertSame($this->config['from']['email'], $mailFrom->Address);
        $this->assertSame($this->config['from']['name'], $mailFrom->Name);

        $mailMessage = $this->getMessage($mail->ID);
        $this->assertSame($body, $mailMessage->Text);
        $this->assertSame($subject, $mailMessage->Subject);
    }

    #[Test]
    #[TestDox('Test sending a mail by creating a message with params from the manager')]
    public function mailerManagerCreateMessageFromView(): void
    {
        $mailer = new Manager($this->config);

        // view relative to the folder viewsDir (REQUIRED)
        $viewPath = 'mail/signup';
        $params   = ['var1' => 'VAR VALUE 1', 'var2' => 'VAR VALUE 2'];

        $to      = 'example_to@gmail.com';
        $bcc     = 'example_bcc@gmail.com';
        $cc      = 'example_cc@gmail.com';
        $subject = 'Hello SendmailView';

        $message = $mailer->createMessageFromView($viewPath, $params)
            ->to($to)
            ->bcc($bcc)
            ->cc($cc)
            ->subject($subject);

        $this->assertSame(1, $message->send(), $message->getLastError());

        // Get sent mails with the messages API
        $mails = $this->getMessages();

        // Asserting
        $this->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $this->assertCount(1, $mailTo);
        $this->assertSame($to, $mailTo[0]->Address);

        $mailBcc = $mail->Bcc;
        $this->assertCount(1, $mailBcc);
        $this->assertSame($bcc, $mailBcc[0]->Address);

        $mailCc = $mail->Cc;
        $this->assertCount(1, $mailCc);
        $this->assertSame($cc, $mailCc[0]->Address);

        $mailFrom = $mail->From;
        $this->assertSame($this->config['from']['email'], $mailFrom->Address);
        $this->assertSame($this->config['from']['name'], $mailFrom->Name);

        $mailMessage = $this->getMessage($mail->ID);
        $expectedBody = "<b>VAR VALUE 1</b><b>VAR VALUE 2</b>";
        $this->assertSame($expectedBody . "\r\n", $mailMessage->HTML);
        $this->assertSame($subject, $mailMessage->Subject);
        $this->assertSame($expectedBody, $message->getContent());
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
    #[TestDox('Test sending mail with sendmail errored to connect -> 0 sent mail and a message from PHPMailer')]
    public function mailerManagerCreateMessageAuthError(): void
    {
        $mailer  = new Manager(array_merge($this->config, ['sendmail' => 'sendmail --smtp-addr unknown-host:1025']));
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $this->assertSame(0, $message->send());
        $this->assertNotSame('', $message->getLastError());
    }
}
