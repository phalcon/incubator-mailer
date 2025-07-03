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

use LogicException;
use Phalcon\Di\Di;
use Phalcon\Mvc\View;

abstract class AbstractFunctionalCest
{
    /**
     * Config passed to the __construct() of Phalcon\Incubator\Mailer\Manager
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Di created before each test and reseted after each test
     */
    protected Di $di;

    /**
     * Base Api URL of Mailpit
     */
    protected string $baseUrl;

    /**
     * Method called before each test, set the URL of MailHog and services for the Di
     */
    public function _before(): void
    {
        $this->baseUrl = sprintf(
            '%s%s:%s/api/v1/',
            $_ENV['DATA_MAILPIT_HOST_PROTOCOL'],
            $_ENV['DATA_MAILPIT_HOST_URI'],
            $_ENV['DATA_MAILPIT_API_PORT']
        );

        $dirSeparator = new \Phalcon\Support\Helper\Str\DirSeparator();

        $this->di = new \Phalcon\Di\FactoryDefault();

        $this->di->setShared(
            'simple',
            function () use ($dirSeparator) {
                $view = new \Phalcon\Mvc\View\Simple();
                $view->setViewsDir($dirSeparator(codecept_data_dir() . 'fixtures/views'));

                return $view;
            }
        );

        $this->di->setShared('view', function () use ($dirSeparator) {
            $view = new View();
            $view->setViewsDir($dirSeparator(codecept_data_dir() . 'fixtures/views'));

            $view->registerEngines([
                '.volt' => function (View $view) {
                    $voltEngine = new \Phalcon\Mvc\View\Engine\Volt($view, $this->di);
                    $voltEngine->setOptions([
                        'data'      => codecept_output_dir(),
                        'separator' => '_'
                    ]);

                    return $voltEngine;
                },
                '.phtml' => \Phalcon\Mvc\View\Engine\Php::class
            ]);

            return $view;
        });
    }

    /**
     * Method called after each test resetting the Di and cleaning MailHog messages
     */
    public function _after(): void
    {
        Di::reset();

        $this->cleanMailhog();
    }

    /**
     * Clean messages from MailHog
     */
    protected function cleanMailhog(): void
    {
        $ch = curl_init($this->baseUrl . 'messages');

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_exec($ch);

        curl_close($ch);
    }

    /**
     * Get mails sent from Mailpit
     *
     * @see https://github.com/axllent/mailpit/blob/develop/docs/apiv1/Messages.md
     *
     * @throws LogicException If the API occured an error and the json hasn't been decoded
     */
    protected function getMessages(): \stdClass
    {
        $mails = json_decode(file_get_contents($this->baseUrl . 'messages') ?: '');

        if (!is_object($mails)) {
            throw new LogicException('Failed to json_decode() messages from Mailpit');
        }

        return $mails;
    }

    /**
     * Get one sent mail from its ID
     *
     * @see https://github.com/axllent/mailpit/blob/develop/docs/apiv1/Message.md
     *
     * @throws LogicException If the id was not found
     */
    protected function getMessage(string $id): \stdClass
    {
        $message = json_decode(file_get_contents("{$this->baseUrl}message/$id") ?: '');

        if (!is_object($message)) {
            throw new LogicException('Failed to json_decode() a single message from Mailpit');
        }

        return $message;
    }
}
