<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Phalcon\Incubator\Mailer;

use Phalcon\Di\Injectable;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\View\Simple;
use PHPMailer\PHPMailer\PHPMailer;

use function is_string;
use function is_array;

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
    public function __construct(protected array $config)
    {
        $this->mailer = new PHPMailer(true); // throw exceptions from PHPMailer

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
            $this->mailer->SMTPAuth = true;
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
                isset($from['name']) ? $from['name'] : ''
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
     */
    protected function getConfig(?string $key = null, ?string $default = null): mixed
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
     * Renders a view
     *
     * @param string $viewPath
     * @param array $params
     * @param string $viewsDir
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

        $view->registerEngines($this->viewEngines ?: $viewApp->getRegisteredEngines());

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
