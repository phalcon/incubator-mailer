<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Framework                                                      |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-2016 Phalcon Team (https://www.phalconphp.com)      |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file LICENSE.txt.                             |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Stanislav Kiryukhin <korsar.zn@gmail.com>                     |
  +------------------------------------------------------------------------+
*/

namespace Phalcon\Incubator\Mailer;

use Phalcon\Di\Injectable;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\View\Simple;

/**
 * Class Manager
 *
 *  *<code>
 * $mailer = \Phalcon\Mailer\Manager($config);
 *
 * if need to set view engines
 * $mailer->setViewEngines([
 *      '.phtml' => 'Phalcon\Mvc\View\Engine\Php'
 * ]);
 *</code>
 *
 * @package Phalcon\Manager
 */
class Manager extends Injectable implements EventsAwareInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected \Swift_Transport $transport;

    protected \Swift_Mailer $mailer;

    protected ?Simple $view = null;

    /**
     * @var array<string, string>
     */
    protected ?array $viewEngines = null;

    protected ?ManagerInterface $eventsManager = null;

    /**
     * Create a new MailerManager component using $config for configuring
     *
     * @param array<string, string|array<string|int, string>> $config
     *
     * @throws \Phalcon\Di\Exception If a DI has not been created
     * @throws \InvalidArgumentException If the driver has been set or not available by the manager
     */
    public function __construct(array $config)
    {
        $this->configure($config);
    }

    public function getEventsManager(): ?ManagerInterface
    {
        return $this->eventsManager;
    }

    public function setEventsManager(ManagerInterface $eventsManager): void
    {
        $this->eventsManager = $eventsManager;
    }

    /**
     * Create a new Message instance.
     *
     * Events:
     * - mailer:beforeCreateMessage
     * - mailer:afterCreateMessage
     */
    public function createMessage(): Message
    {
        $eventsManager = $this->getEventsManager();

        if ($eventsManager) {
            $eventsManager->fire('mailer:beforeCreateMessage', $this);
        }

        /** @var Message $message */
        $message = $this->getDI()->get(
            '\Phalcon\Incubator\Mailer\Message',
            [
                $this
            ]
        );

        $from = $this->getConfig('from');
        if (is_array($from)) {
            $message->from(
                $from['email'],
                isset($from['name']) ? $from['name'] : null
            );
        } elseif (is_string($from)) {
            $message->from($from);
        }

        if ($eventsManager) {
            $eventsManager->fire('mailer:afterCreateMessage', $this, $message);
        }

        return $message;
    }

    /**
     * Create a new Message instance.
     * For the body of the message uses the result of render of view
     *
     * Events:
     * - mailer:beforeCreateMessage
     * - mailer:afterCreateMessage
     *
     * @param string $view
     * @param array<string, mixed> $params optional
     * @param null|string $viewsDir optional
     *
     * @see \Phalcon\Mailer\Manager::createMessage()
     */
    public function createMessageFromView(string $view, array $params = [], ?string $viewsDir = null): Message
    {
        $message = $this->createMessage();

        $message->content(
            $this->renderView($view, $params, $viewsDir),
            $message::CONTENT_TYPE_HTML
        );

        return $message;
    }

    /**
     * Return a {@link \Swift_Mailer} instance
     */
    public function getSwift(): \Swift_Mailer
    {
        return $this->mailer;
    }

    /**
     * Return a {@link \Swift_Transport} instance, either SMTP or Sendmail
     */
    public function getTransport(): \Swift_Transport
    {
        return $this->transport;
    }

    /**
     * Normalize IDN domains.
     *
     * @param string $email
     *
     * @see \Phalcon\Mailer\Manager::punycode()
     */
    public function normalizeEmail(string $email): string
    {
        if (preg_match('#[^(\x20-\x7F)]+#', $email)) {
            list($user, $domain) = explode('@', $email);

            return $user . '@' . $this->punycode($domain);
        } else {
            return $email;
        }
    }

    /**
     * Add view engines to the manager
     *
     * @param array<string, string> $engines
     */
    public function setViewEngines(array $engines): void
    {
        $this->viewEngines = $engines;
    }

    /**
     * Configure MailerManager class
     *
     * @param array<string, mixed> $config
     *
     * @see \Phalcon\Mailer\Manager::registerSwiftTransport()
     * @see \Phalcon\Mailer\Manager::registerSwiftMailer()
     */
    protected function configure(array $config): void
    {
        $this->config = $config;

        $this->registerSwiftTransport();
        $this->registerSwiftMailer();
    }

    /**
     * Create a new Driver-mail of SwiftTransport instance.
     *
     * Supported driver-mail:
     * - smtp
     * - sendmail
     *
     * @throws \InvalidArgumentException If the driver is not a string value or not supported by the manager
     */
    protected function registerSwiftTransport(): void
    {
        $driver = $this->getConfig('driver');

        if (!is_string($driver)) {
            throw new \InvalidArgumentException('Driver must be a string value set from the config');
        }

        switch ($driver) {
            case 'smtp':
                $this->transport = $this->registerTransportSmtp();
                break;

            case 'sendmail':
                $this->transport = $this->registerTransportSendmail();
                break;

            default:
                throw new \InvalidArgumentException("Driver-mail '$driver' is not supported");
        }
    }

    /**
     * Create a new SmtpTransport instance.
     *
     * @return \Swift_SmtpTransport
     *
     * @see \Swift_SmtpTransport
     */
    protected function registerTransportSmtp(): \Swift_SmtpTransport
    {
        $config = $this->getConfig();

        /** @var \Swift_SmtpTransport $transport */
        $transport = $this->getDI()->get('\Swift_SmtpTransport');

        if (isset($config['host'])) {
            $transport->setHost($config['host']);
        }

        if (isset($config['port'])) {
            $transport->setPort($config['port']);
        }

        if (isset($config['encryption'])) {
            $transport->setEncryption(
                $config['encryption']
            );
        }

        if (isset($config['username'])) {
            $transport->setUsername(
                $this->normalizeEmail(
                    $config['username']
                )
            );

            $transport->setPassword(
                $config['password']
            );
        }

        return $transport;
    }

    /**
     * Get option config or the entire array of config, if the parameter $key is not specified.
     *
     * @param string $key
     * @param string $default
     *
     * @return string|array<string, mixed>|null
     */
    protected function getConfig(?string $key = null, ?string $default = null)
    {
        if ($key !== null) {
            if (isset($this->config[$key])) {
                return $this->config[$key];
            } else {
                return $default;
            }
        }

        return $this->config;
    }

    /**
     * Convert UTF-8 encoded domain name to ASCII
     *
     * @param string $str
     *
     * @return string
     */
    protected function punycode(string $str): string
    {
        if (function_exists('idn_to_ascii')) {
            return idn_to_ascii($str);
        } else {
            // @codeCoverageIgnoreStart
            return $str;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Create a new SendmailTransport instance.
     *
     * @return \Swift_SendmailTransport
     *
     * @see \Swift_SendmailTransport
     */
    protected function registerTransportSendmail(): \Swift_SendmailTransport
    {
        /** @var \Swift_SendmailTransport $transport */
        $transport = $this->getDI()->get('\Swift_SendmailTransport')
            ->setCommand($this->getConfig('sendmail', '/usr/sbin/sendmail -bs'));

        return $transport;
    }

    /**
     * Register SwiftMailer
     *
     * @see \Swift_Mailer
     */
    protected function registerSwiftMailer()
    {
        $this->mailer = $this->getDI()->get(
            '\Swift_Mailer',
            [
                $this->transport
            ]
        );
    }

    /**
     * Renders a view
     *
     * @param string $viewPath
     * @param array $params
     * @param string $viewsDir
     *
     * @return string
     */
    protected function renderView(string $viewPath, array $params, ?string $viewsDir = null): string
    {
        $view = $this->getView();

        if ($viewsDir !== null) {
            $viewsDirOld = $view->getViewsDir();
            $view->setViewsDir($viewsDir);

            $content = $view->render($viewPath, $params);
            $view->setViewsDir($viewsDirOld);

            return $content;
        }

        return $view->render($viewPath, $params);
    }

    /**
     * Return a {@link \Phalcon\Mvc\View\Simple} instance
     */
    protected function getView(): Simple
    {
        if ($this->view) {
            return $this->view;
        }

        /** @var \Phalcon\Mvc\View $viewApp */
        $viewApp = $this->getDI()->get('view');

        if (!($viewsDir = $this->getConfig('viewsDir'))) {
            $viewsDir = $viewApp->getViewsDir();
        }

        /** @var \Phalcon\Mvc\View\Simple $view */
        $view = $this->getDI()->get('\Phalcon\Mvc\View\Simple');

        if (is_string($viewsDir)) {
            $view->setViewsDir($viewsDir);
        }

        if ($this->viewEngines) {
            $view->registerEngines($this->viewEngines);
        }

        $this->view = $view;

        return $this->view;
    }
}
