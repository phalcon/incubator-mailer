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

namespace Phalcon\Incubator\Mailer\Tests\Functional\Manager;

use FunctionalTester;
use Phalcon\Support\HelperFactory;
use Phalcon\Incubator\Mailer\Manager;
use Phalcon\Di\FactoryDefault as DI;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Mvc\View\Engine\Php as PhpEngine;
use Phalcon\Mvc\View\Simple;

final class ManagerSMTPCest
{
    private $config, $baseUrl, $di;

    public function __construct()
    {
        $this->di = new DI();

        $this->config = [
            'driver'   => 'smtp',
            'host'     => getenv('DATA_MAILHOG_HOST_URI'),
            'port'     => getenv('DATA_MAILHOG_SMTP_PORT'),
            'username' => 'example@gmail.com',
            'password' => 'your_password',
            'from'     => [
                'email' => 'example_smtp@gmail.com',
                'name'  => 'EXAMPLE SMTP',
            ],
        ];

        $helper = new HelperFactory();

        $this->di->set(
            'simple',
            function () use ($helper) {
                $view = new Simple();

                $view->setViewsDir($helper->dirSeparator(
                    codecept_data_dir() . 'fixtures/views'
                ));

                return $view;
            },
            true
        );

        $this->di->setShared(
            'view',
            function () use ($helper) {
                $view = new View();

                $view->setDI($this);
                $view->setViewsDir($helper->dirSeparator(
                    codecept_data_dir() . 'fixtures/views'
                ));

                $view->registerEngines([
                    '.volt'  => function ($view) {

                        $volt = new VoltEngine($view, $this);

                        $volt->setOptions([
                            'path'      => codecept_output_dir(),
                            'separator' => '_'
                        ]);

                        return $volt;
                    },
                    '.phtml' => PhpEngine::class

                ]);

                return $view;
            }
        );

        $this->baseUrl = sprintf("%s%s:%s/api/v1/", getenv('DATA_MAILHOG_HOST_PROTOCOL'), getenv('DATA_MAILHOG_HOST_URI'), getenv('DATA_MAILHOG_API_PORT'));
    }

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

        $message->send();

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'Accept-language: en\r\n'
            ]
        ];

        $context = stream_context_create($opts);

        // Get all mail send in the MailHog SMTP
        $dataMail = file_get_contents($this->baseUrl . 'messages', false, $context);
        $dataMail = \json_decode($dataMail);

        //Check that there are one mail send
        $I->assertCount(1, $dataMail);

        $mail = $dataMail[0];

        $mailFromData = $mail->From;
        $mailToData   = end($mail->To);

        $mailFrom = $mailFromData->Mailbox . '@' . $mailFromData->Domain;
        $mailTo   = $mailToData->Mailbox . '@' . $mailToData->Domain;

        $I->assertEquals($this->config['from']['email'], $mailFrom);
        $I->assertEquals($to, $mailTo);

        $I->assertEquals($body, $mail->Content->Body);
        $I->assertStringContainsString('Subject: ' . $subject, $mail->Raw->Data);
    }

    public function mailerManagerCreateMessageFromView(FunctionalTester $I)
    {
        $mailer = new Manager($this->config);

        // view relative to the folder viewsDir (REQUIRED)
        $viewPath = 'mail/signup';

        // Set variables to views (OPTIONAL)
        $params = [
            'var1' => 'VAR VALUE 1',
            'var2' => 'VAR VALUE 2',
        ];

        $to      = 'example_to@gmail.com';
        $subject = 'Hello SMTPView';

        $message = $mailer->createMessageFromView($viewPath, $params)
            ->to($to)
            ->subject($subject);
        $message->send();

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'Accept-language: en\r\n'
            ]
        ];

        $context = stream_context_create($opts);

        // Get all mail send in the MailHog SMTP
        $dataMail = file_get_contents($this->baseUrl . 'messages', false, $context);
        $dataMail = \json_decode($dataMail);

        // Check that there are one mail send
        $I->assertCount(2, $dataMail);

        $mail = $dataMail[0];

        $mailFromData = $mail->From;
        $mailToData   = end($mail->To);

        $mailFrom = $mailFromData->Mailbox . '@' . $mailFromData->Domain;
        $mailTo   = $mailToData->Mailbox . '@' . $mailToData->Domain;

        $I->assertEquals($this->config['from']['email'], $mailFrom);
        $I->assertEquals($to, $mailTo);

        $body = $this->di->get('simple')->render($viewPath, $params);

        $I->assertEquals($body, $mail->Content->Body);
        $I->assertStringContainsString('Subject: ' . $subject, $mail->Raw->Data);

        // Clean emails sent from MailHog
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . 'messages');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $result     = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        $I->assertEquals(200, $httpCode);
    }

    public function mailerManagerCreateMessageWithName(FunctionalTester $I)
    {
        $sender  = 'example_sender@gmail.com';
        $name    = 'Example Name';
        $from    = 'example_from@gmail.com';
        $to      = 'example_to@gmail.com';
        $subject = 'Hello SMTP';
        $body    = 'Lorem Ipsum';

        $mailer = new Manager($this->config);

        $message = $mailer->createMessage()
            ->sender($sender, $name)
            ->from($from, $name)
            ->to($to, $name)
            ->subject($subject)
            ->content($body);

        $message->send();

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'Accept-language: en\r\n'
            ]
        ];

        $context = stream_context_create($opts);

        // Get all mail send in the MailHog SMTP
        $dataMail = file_get_contents($this->baseUrl . 'messages', false, $context);
        $dataMail = \json_decode($dataMail);

        //Check that there are one mail send
        $I->assertCount(3, $dataMail);

        $mail = $dataMail[0];

        $mailFromData = $mail->From;
        $mailToData   = end($mail->To);

        $mailFrom = $mailFromData->Mailbox . '@' . $mailFromData->Domain;
        $mailTo   = $mailToData->Mailbox . '@' . $mailToData->Domain;

        $I->assertEquals($sender, $mailFrom);
        $I->assertEquals($to, $mailTo);

        $headers = $mail->Content->Headers;

        $mailSenderWithName = end($headers->Sender);
        $senderWithName = sprintf('%s <%s>', $name, $sender);
        $I->assertEquals($senderWithName, $mailSenderWithName);

        $mailFromWithName = end($headers->From);
        $fromWithName = sprintf('%s <%s>', $name, $from);
        $I->assertEquals($fromWithName, $mailFromWithName);

        $mailToWithName = end($headers->To);
        $toWithName = sprintf('%s <%s>', $name, $to);
        $I->assertEquals($toWithName, $mailToWithName);

        $I->assertEquals($body, $mail->Content->Body);
        $I->assertStringContainsString('Subject: ' . $subject, $mail->Raw->Data);
    }
}
