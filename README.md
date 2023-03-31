# Phalcon\Incubator\Mailer

[![Discord](https://img.shields.io/discord/310910488152375297?label=Discord)](http://phalcon.io/discord)
[![Packagist Version](https://img.shields.io/packagist/v/phalcon/incubator-mailer)](https://packagist.org/packages/phalcon/incubator-mailer)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/phalcon/incubator-mailer)](https://packagist.org/packages/phalcon/incubator-mailer)
[![codecov](https://codecov.io/gh/phalcon/incubator-mailer/branch/master/graph/badge.svg)](https://codecov.io/gh/phalcon/incubator-mailer)
[![Packagist](https://img.shields.io/packagist/dd/phalcon/incubator-mailer)](https://packagist.org/packages/phalcon/incubator-mailer/stats)

Usage examples of the mailer wrapper over [PHPMailer](https://github.com/PHPMailer/PHPMailer) for Phalcon:

## Configure
### SMTP

```php
$config = [
    'driver'     => 'smtp',
    'host'       => 'smtp.gmail.com',
    'port'       => 465,
    'encryption' => 'ssl',
    'username'   => 'example@gmail.com',
    'password'   => 'your_password',
    'from'       => [
        'email' => 'example@gmail.com',
        'name'  => 'YOUR FROM NAME'
    ]
];
```

### Sendmail

```php
$config = [
    'driver'   => 'sendmail',
    'sendmail' => '/usr/sbin/sendmail -bs',
    'from'     => [
        'email' => 'example@gmail.com',
        'name'  => 'YOUR FROM NAME'
    ]
];
```

## Send message

### createMessage()

```php
$mailer = new \Phalcon\Incubator\Mailer\Manager($config);

$message = $mailer->createMessage()
        ->to('example_to@gmail.com', 'OPTIONAL NAME')
        ->subject('Hello world!')
        ->content('Hello world!');

// Set the Cc addresses of this message.
$message->cc('example_cc@gmail.com');

// Set the Bcc addresses of this message.
$message->bcc('example_bcc@gmail.com');

// Send message
$message->send();
```

### createMessageFromView()
```php
// To create message with View, you need to define in the DI the component simple View. 
$this->di->set(
    'simple',
    function () use ($config) {
        $view = new Phalcon\Mvc\View\Simple();
        $view->setViewsDir($config->application->viewsDir);

        return $view;
    },
    true
);

$this->di->setShared('view', function () use ($config) {
    $view = new Phalcon\Mvc\View($this->di);
    $view->setViewsDir($config->application->viewsDir);

    $view->registerEngines([
        '.volt'  => function ($view) {
            $volt = new Phalcon\Mvc\View\Engine\Volt($view, $this->di);
            $volt->setOptions([
                'path'      => $config->application->cacheDir,
                'separator' => '_'
            ]);

            return $volt;
        },
        '.phtml' => Phalcon\Mvc\View\Engine\Php::class
    ]);

    return $view;
});

$mailer = new \Phalcon\Incubator\Mailer\Manager($config);

// view relative to the folder viewsDir (REQUIRED)
$viewPath = 'email/example_message';

// Set variables to views (OPTIONAL)
$params = [ 
    'var1' => 'VAR VALUE 1',
    'var2' => 'VAR VALUE 2',
    // ...
    'varN' => 'VAR VALUE N'
];

$message = $mailer->createMessageFromView($viewPath, $params)
        ->to('example_to@gmail.com', 'OPTIONAL NAME')
        ->subject('Hello world!');

// Set the Cc addresses of this message.
$message->cc('example_cc@gmail.com');

// Set the Bcc addresses of this message.
$message->bcc('example_bcc@gmail.com');

// Send message
$message->send();
```

## Events
https://docs.phalcon.io/5.0/en/events

All events are callables with at least 2 arguments

Look for each event down below for more informations of the third argument

```php
- mailer:beforeCreateMessage
    function (Phalcon\Events\Event $event, Phalcon\Incubator\Mailer\Manager $manager, null) {};

- mailer:afterCreateMessage
    function (Phalcon\Events\Event $event, Phalcon\Incubator\Mailer\Manager $manager, Phalcon\Incubator\Mailer\Message $message) {};

- mailer:beforeSend
    function (Phalcon\Events\Event $event, Phalcon\Incubator\Mailer\Message $message, null) {};

- mailer:afterSend
    2 arguments, the number of sent mails and an array of emails representing the failed recipients

    function (Phalcon\Events\Event $event, Phalcon\Incubator\Mailer\Message $message, [int $count, array $failedRecipients]) {};

- mailer:beforeAttachFile
    function (Phalcon\Events\Event $event, Phalcon\Incubator\Mailer\Message $message, null) {};

- mailer:afterAttachFile
    1 argument, an array with the attachment informations

    function (Phalcon\Events\Event $event, Phalcon\Incubator\Mailer\Message $message, array $attachment) {};

    0: string (path of the file or encoded data)
    1: string (name of the attachment)
    2: string (basename of the attachment)
    3: string (encoding)
    4: string (MIME type)
    5: bool (false -> encoded data, true -> from a file)
    6: string (disposition of the mail)
    7: string|0 (if from a file, name of the file)
```
