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

class ManagerSendmailCest extends AbstractFunctionalCest
{
    public function _before(): void
    {
        parent::_before();

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

    /**
     * @test Test sending a mail by creating a message from the manager
     */
    public function mailerManagerCreateMessage(FunctionalTester $I): void
    {
        $to      = 'example_to@gmail.com';
        $subject = 'Hello Sendmail';
        $body    = 'Lorem Ipsum';

        $mailer = new Manager($this->config);

        $message = $mailer->createMessage()
            ->to($to, 'John Doe')
            ->subject($subject)
            ->content($body);

        $I->assertSame(1, $message->send(), $message->getLastError());
        $I->assertSame([], $message->getFailedRecipients());

        // Get sent mails with the messages API
        $mails = $this->getMessages();

        // Asserting
        $I->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $I->assertCount(1, $mailTo);
        $I->assertSame($to, $mailTo[0]->Address);
        $I->assertSame('John Doe', $mailTo[0]->Name);

        $mailFrom = $mail->From;
        $I->assertSame($this->config['from']['email'], $mailFrom->Address);
        $I->assertSame($this->config['from']['name'], $mailFrom->Name);

        $mailMessage = $this->getMessage($mail->ID);
        $I->assertSame($body, $mailMessage->Text);
        $I->assertSame($subject, $mailMessage->Subject);
    }

    /**
     * @test Test sending a mail by creating a message with params from the manager
     */
    public function mailerManagerCreateMessageFromView(FunctionalTester $I)
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

        $I->assertSame(1, $message->send(), $message->getLastError());

        // Get sent mails with the messages API
        $mails = $this->getMessages();

        // Asserting
        $I->assertSame(1, $mails->total);
        $mail = $mails->messages[0];

        $mailTo = $mail->To;
        $I->assertCount(1, $mailTo);
        $I->assertSame($to, $mailTo[0]->Address);

        $mailBcc = $mail->Bcc;
        $I->assertCount(1, $mailBcc);
        $I->assertSame($bcc, $mailBcc[0]->Address);

        $mailCc = $mail->Cc;
        $I->assertCount(1, $mailCc);
        $I->assertSame($cc, $mailCc[0]->Address);

        $mailFrom = $mail->From;
        $I->assertSame($this->config['from']['email'], $mailFrom->Address);
        $I->assertSame($this->config['from']['name'], $mailFrom->Name);

        $mailMessage = $this->getMessage($mail->ID);
        $expectedBody = "<b>VAR VALUE 1</b><b>VAR VALUE 2</b>";
        $I->assertSame($expectedBody . "\r\n", $mailMessage->HTML);
        $I->assertSame($subject, $mailMessage->Subject);
        $I->assertSame($expectedBody, $message->getContent());
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
     * @test Test sending mail with sendmail errored to connect -> 0 sent mail and a message from PHPMailer
     */
    public function mailerManagerCreateMessageAuthError(FunctionalTester $I): void
    {
        $mailer  = new Manager(array_merge($this->config, ['sendmail' => 'sendmail --smtp-addr unknown-host:1025']));
        $message = $mailer->createMessage()
            ->to('example_to@gmail.com')
            ->to('example_to2@gmail.com')
            ->subject('Test subject')
            ->content('content');

        $I->assertSame(0, $message->send());
        $I->assertNotSame('', $message->getLastError());
    }
}
