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

    public function __construct()
    {
        $di = new DI();

        $config = [
            'driver'     => 'smtp',
            'host'       => '127.0.0.1',
            'port'       => 25,
            'username'   => 'example@gmail.com',
            'password'   => 'your_password',
            'from'       => [
                'email' => 'example@gmail.com',
                'name'  => 'YOUR FROM NAME',
            ],
        ];

        $this->mailer = new Manager($config);

        $message = $this->mailer->createMessage()
            ->to('example_to@gmail.com', 'OPTIONAL NAME')
            ->subject('Hello world!')
            ->content('Hello world!');
    }
}
