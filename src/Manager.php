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
use PHPMailer\PHPMailer\PHPMailer;

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

    protected PHPMailer $mailer;

    protected ?Simple $view = null;

    /**
     * @var array<string, string>
     */
    protected ?array $viewEngines = null;

    protected ?ManagerInterface $eventsManager = null;

    /**
     * Create a new MailerManager component using $config for configuring
     *
     * Supported driver-mail:
     * - smtp
     * - sendmail
     *
     * @param array<string, string|array<string|int, string>> $config
     *
     * @throws \Phalcon\Di\Exception If a DI has not been created
     * @throws \InvalidArgumentException If the driver has not been set or not available by the manager
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->mailer = new PHPMailer();

        $driver = $this->getConfig('driver');

        if (!is_string($driver)) {
            throw new \InvalidArgumentException('Driver must be a string value set from the config');
        }

        switch ($driver) {
            case 'smtp':
                $this->registerSmtp();
                break;

            case 'sendmail':
                $this->registerSendmail();
                break;

            default:
                throw new \InvalidArgumentException("Driver-mail '$driver' is not supported");
        }
    }

    /**
     * Set the config for mailer to use SMTP
     */
    protected function registerSmtp(): void
    {
        $config = $this->getConfig();

        $this->mailer->isSMTP();

        if (isset($config['host'])) {
            $this->mailer->Host = $config['host'];
        }

        if (isset($config['port'])) {
            $this->mailer->Port = $config['port'];
        }

        if (isset($config['encryption'])) {
            $this->mailer->SMTPSecure = $config['encryption'];
        }

        if (isset($config['username'])) {
            $this->mailer->Username = $config['username'];

            if (isset($config['password'])) {
                $this->mailer->Password = $config['password'];
            }
        }
    }

    /**
     * Set the config for mailer to use sendmail
     */
    protected function registerSendmail(): void
    {
        $this->mailer->isSendmail();

        if ($sendMailPath = $this->getConfig('sendmail')) {
            $this->mailer->Sendmail = $sendMailPath;
        }
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
            [$this]
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

    /**
     * Returns the PHPMailer instance used to send mails
     */
    public function getMailer(): PHPMailer
    {
        return $this->mailer;
    }
}
