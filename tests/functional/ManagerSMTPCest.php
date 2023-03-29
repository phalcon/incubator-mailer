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
use Phalcon\Incubator\Mailer\Manager;

class ManagerSMTPCest extends AbstractFunctionalCest
{
    public function _before(): void
    {
        parent::_before();

        $this->config = [
            'driver'   => 'smtp',
            'host'     => $_ENV['DATA_MAILHOG_HOST_URI'],
            'port'     => $_ENV['DATA_MAILHOG_SMTP_PORT'],
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

        $I->assertTrue($message->send());

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

        $I->assertTrue($message->send());

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
}
