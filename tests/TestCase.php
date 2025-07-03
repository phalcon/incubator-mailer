<?php

namespace Phalcon\Incubator\Mailer\Tests;

use Dotenv\Dotenv;
use LogicException;
use Phalcon\Mvc\View;

use function dirname;
use function file_get_contents;
use function sprintf;
use function is_object;
use function json_decode;
use function curl_init;
use function curl_setopt;
use function curl_exec;
use function curl_close;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected \Phalcon\Di\Di $di;

    /** Base API url of Mailpit */
    protected string $baseUrl;

    protected const VIEWS_DIR = __DIR__ . '/fixtures/views';

    public static function setUpBeforeClass(): void
    {
        // Load environment variables from .env file
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
        $dotenv->required(['DATA_MAILPIT_HOST_PROTOCOL', 'DATA_MAILPIT_HOST_URI', 'DATA_MAILPIT_API_PORT'])->required();
    }

    /** Creates a DI and sest the URL of Mailpit and services for the Di before each test */
    protected function setUp(): void
    {
        $this->di = new \Phalcon\Di\Di();

        $this->baseUrl = sprintf(
            '%s%s:%s/api/v1/',
            $_ENV['DATA_MAILPIT_HOST_PROTOCOL'],
            $_ENV['DATA_MAILPIT_HOST_URI'],
            $_ENV['DATA_MAILPIT_API_PORT']
        );

        $this->di->setShared('view', function () {
            $view = new View();
            $view->setViewsDir(self::VIEWS_DIR);

            return $view;
        });
    }

    /** Resets DI after each test */
    protected function tearDown(): void
    {
        \Phalcon\Di\Di::reset();
    }

    /** Clean messages from MailHog */
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
