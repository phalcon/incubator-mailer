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

use Phalcon\DI\Injectable;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\ManagerInterface;
use \Phalcon\Mvc\View\Simple;

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
     * @var array
     */
    protected array $config = [];

    /**
     * @var \Swift_Transport
     */
    protected \Swift_Transport $transport;

    /**
     * @var \Swift_Mailer
     */
    protected \Swift_Mailer $mailer;

    /**
     * @var \Phalcon\Mvc\View\Simple
     */
    protected Simple $view;

    /**
     * @var array
     */
    protected ?array $viewEngines = null;

    /**
     * @var \Phalcon\Events\ManagerInterface
     */
    protected ?ManagerInterface $eventsManager = null;

    /**
     * Create a new MailerManager component using $config for configuring
     *
     * @param array $config
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
     *
     * @return Message
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
                $this,
            ]
        );

        $from = $this->getConfig('from');
        if (is_array($from)) {
            $message->from(
                $from['email'],
                isset($from['name']) ? $from['name'] : null
            );
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
     * @param array $params         optional
     * @param null|string $viewsDir optional
     *
     * @return Message
     *
     * @see \Phalcon\Mailer\Manager::createMessage()
     */
    public function createMessageFromView(string $view, array $params = [], $viewsDir = null): Message
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
     *
     * @return \Swift_Mailer
     */
    public function getSwift(): \Swift_Mailer
    {
        return $this->mailer;
    }

    /**
     * Normalize IDN domains.
     *
     * @param string $email
     *
     * @return string
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
     * set value of $viewEngines
     *
     * @param array $engines
     */
    public function setViewEngines(array $engines): void
    {
        $this->viewEngines = $engines;
    }

    /**
     * Configure MailerManager class
     *
     * @param array $config
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
     * - mail
     *
     */
    protected function registerSwiftTransport(): void
    {
        switch ($driver = $this->getConfig('driver')) {
            case 'smtp':
                $this->transport = $this->registerTransportSmtp();
                break;

            case 'sendmail':
                $this->transport = $this->registerTransportSendmail();
                break;

            default:
                if (is_string($driver)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Driver-mail "%s" is not supported',
                            $driver
                        )
                    );
                } elseif (is_array($driver)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Driver-mail "%s" is not supported',
                            json_encode($driver)
                        )
                    );
                }
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
     * @return string|array|null
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
            return $str;
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
                $this->transport,
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
    protected function renderView(string $viewPath, array $params, $viewsDir = null): string
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
     *
     * @return \Phalcon\Mvc\View\Simple
     */
    protected function getView(): Simple
    {
        if (empty($this->view)) {
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
        }

        return $this->view;
    }
}
