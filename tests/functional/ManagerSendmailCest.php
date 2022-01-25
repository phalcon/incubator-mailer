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
use Phalcon\Support\Helper\Str\DirSeparator;
use Phalcon\Incubator\Mailer\Manager;
use Phalcon\Di\FactoryDefault as DI;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Mvc\View\Engine\Php as PhpEngine;
use Phalcon\Mvc\View\Simple;

final class ManagerSendmailCest
{
    private $config;
    private $baseUrl;
    private $di;
    private $dirSeparator;

    public function __construct()
    {
        $this->di = new DI();
        $this->dirSeparator = new DirSeparator();

        $this->config = [
            'driver'    => 'sendmail',
            'sendmail'  => '/usr/sbin/sendmail -t',
            'from'      => [
                'email' => 'example_sendmail@gmail.com',
                'name'  => 'EXAMPLE SENDMAIL',
            ],
            'encryption' => null
        ];

        $this->di->set(
            'simple',
            function () {
                $view = new Simple();

                $view->setViewsDir($this->dirSeparator->__invoke(
                    codecept_data_dir() . 'fixtures/views'
                ));

                return $view;
            },
            true
        );

        $this->di->setShared('view', function () {
            $view = new View();
            $view->setDI($this);
            $view->setViewsDir($this->dirSeparator->__invoke(
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
        });

        $this->baseUrl = sprintf("%s%s:%s/api/v1/", getenv('DATA_MAILHOG_HOST_PROTOCOL'), getenv('DATA_MAILHOG_HOST_URI'), getenv('DATA_MAILHOG_API_PORT'));
    }

    public function mailerManagerCreateMessage(FunctionalTester $I)
    {
        $to      = 'example_to@gmail.com';
        $subject = 'Hello Sendmail';
        $body    = 'Lorem Ipsum';

        $mailer = new Manager($this->config);

        $message = $mailer->createMessage()
            ->to($to)
            ->subject($subject)
            ->content($body);

        try {
            $I->assertNotFalse($message->send());
        } catch (\Exception $e) {
            printf("Error: %s\n", $e->getMessage());
        }
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
        $subject = 'Hello SendmailView';

        $message = $mailer->createMessageFromView($viewPath, $params)
            ->to($to)
            ->subject($subject);

        try {
            $I->assertNotFalse($message->send());
        } catch (\Exception $e) {
            printf("Error: %s\n", $e->getMessage());
        }
    }
}
