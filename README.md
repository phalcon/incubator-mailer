# Phalcon\Incubator\Mailer

[![Discord](https://img.shields.io/discord/310910488152375297?label=Discord)](http://phalcon.link/discord)
[![Packagist Version](https://img.shields.io/packagist/v/phalcon/incubator-mailer)](https://packagist.org/packages/phalcon/incubator-mailer)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/phalcon/incubator-mailer)](https://packagist.org/packages/phalcon/incubator-mailer)
[![codecov](https://codecov.io/gh/phalcon/incubator-mailer/branch/master/graph/badge.svg)](https://codecov.io/gh/phalcon/incubator-mailer)
[![Packagist](https://img.shields.io/packagist/dd/phalcon/incubator-mailer)](https://packagist.org/packages/phalcon/incubator-mailer/stats)

Usage examples of the mailer wrapper over SwiftMailer for Phalcon:

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
        'name'  => 'YOUR FROM NAME',
    ],
];

```

### Sendmail

```php
$config = [
    'driver'    => 'sendmail',
    'sendmail'  => '/usr/sbin/sendmail -bs',
    'from'      => [
        'email' => 'example@gmail.com',
        'name'  => 'YOUR FROM NAME',
    ],
];
```

### PHP Mail

```php
$config = [
    'driver'    => 'mail',
    'from'      => [
        'email' => 'example@gmail.com',
        'name'  => 'YOUR FROM NAME',
    ],
];
```

## Send mail

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
/**
    To create mail with View, you need to define in the DI the component simple View. 
*/
$this->di->set(
    'simple',
    function () {
        $view = new Phalcon\Mvc\View\Simple();

        $view->setViewsDir($config->application->viewsDir);

        return $view;
    },
    true
);

$this->di->setShared('view', function () {
    $view = new Phalcon\Mvc\View();
    $view->setDI($this);
    $view->setViewsDir($config->application->viewsDir);

    $view->registerEngines([
        '.volt'  => function ($view) {

            $volt = new Phalcon\Mvc\View\Engine\Volt($view, $this);

            $volt->setOptions([
                'path' => $config->application->cacheDir,
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
    'varN' => 'VAR VALUE N',
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
- `mailer:beforeCreateMessage`
- `mailer:afterCreateMessage`
- `mailer:beforeSend`
- `mailer:afterSend`
- `mailer:beforeAttachFile`
- `mailer:afterAttachFile`