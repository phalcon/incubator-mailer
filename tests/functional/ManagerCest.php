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
use Phalcon\Incubator\Mailer\Manager;
use Phalcon\Di\FactoryDefault as DI;
use Phalcon\Incubator\Mailer\Message;

final class ManagerCest
{
    private $mailer;
    private $config;

    public function __construct()
    {
        $di = new DI();

        $this->config = [
            'driver'     => 'smtp',
            'host'       => '127.0.0.1',
            'port'       => getenv('DATA_MAILHOG_PORT'),
            'username'   => 'example@gmail.com',
            'password'   => 'your_password',
            'from'       => [
                'email' => 'example@gmail.com',
                'name'  => 'YOUR FROM NAME',
            ]
        ];

        $this->mailer = new Manager($this->config);
    }

    public function mailerManagerSendMessage(FunctionalTester $I)
    {
        $to = 'example_to@gmail.com';
        $message = $this->mailer->createMessage()
            ->to($to)
            ->subject('Hello world!')
            ->content('Hello world!');

        $message->send();


        $opts = array(
            'http'=>array(
              'method'=>"GET",
              'header'=>"Accept-language: en\r\n" .
                        "Cookie: foo=bar\r\n"
            )
          );
          
          $context = stream_context_create($opts);
          
          // Get all mail send in the MailHog SMTP
          $baseUrl = 'http://127.0.0.1:'.getenv('DATA_MAILHOG_CHECK_PORT').'/api/v1/';
          $dataMail = file_get_contents($baseUrl . 'messages', false, $context);
          $dataMail = \json_decode($dataMail, true);
          
          //Check that there are one mail send
          $I->assertCount(1, $dataMail);

          $mail = end($dataMail);

          print_r($mail);
        
          $mailFromData = end($mail->From);
          $mailToData   = end($mail->To);
          
          $mailFrom = $mailFromData->Mailbox . '@' . $mailFromData->Domain;
          $mailTo   = $mailToData->Mailbox . '@' . $mailToData->Domain;

          $I->assertEquals($mailFrom, $this->config['from']['email']);
          $I->assertEquals($mailTo, $to);
    }
}
