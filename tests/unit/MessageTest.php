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

namespace Phalcon\Incubator\Mailer\Tests\Unit;

use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Incubator\Mailer\Manager;
use Phalcon\Incubator\Mailer\Message;

class MessageTest extends AbstractUnit
{
    /**
     * @test Test instantiating the message -> try to access getters with default values
     */
    public function testConstruct(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $this->assertNull($message->getBcc());
        $this->assertNull($message->getCc());
        $this->assertSame('utf-8', $message->getCharset());
        $this->assertNull($message->getContent());
        $this->assertSame('text/plain', $message->getContentType());
        $this->assertSame([], $message->getFailedRecipients());
        $this->assertSame('', $message->getFormat());
        $this->assertSame([], $message->getFrom());
        $this->assertSame(3, $message->getPriority());
        $this->assertSame($manager, $message->getManager());
        $this->assertNull($message->getReadReceiptTo());
        $this->assertNull($message->getReplyTo());
        $this->assertNull($message->getReturnPath());
        $this->assertNull($message->getSender());
        $this->assertNull($message->getSubject());
        $this->assertNull($message->getTo());
    }

    /**
     * @test Test instantiating the message setting the headers to the message
     */
    public function testSettersAndGettersMailHeaders(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $message->setFormat('flowed');
        $this->assertSame('flowed', $message->getFormat());

        $message->setReadReceiptTo(['test@test.com']);
        $this->assertSame(['test@test.com' => null], $message->getReadReceiptTo());

        $message->setReturnPath('test@test.com');
        $this->assertSame('test@test.com', $message->getReturnPath());

        $message->bcc('johndoe@test.com', 'John Doe');
        $this->assertSame(['johndoe@test.com' => 'John Doe'], $message->getBcc());

        $message->cc('johndoe@test2.com', 'John Doe');
        $this->assertSame(['johndoe@test2.com' => 'John Doe'], $message->getCc());

        $message->from(['johndoe@test3.com' => 'John Doe']);
        $this->assertSame(['johndoe@test3.com' => 'John Doe'], $message->getFrom());

        $message->priority(5);
        $this->assertSame(5, $message->getPriority());

        $message->replyTo(['johndoe@test4.com' => 'John Doe']);
        $this->assertSame(['johndoe@test4.com' => 'John Doe'], $message->getReplyTo());

        $message->sender('johndoe@test5.com');
        $this->assertSame(['johndoe@test5.com' => null], $message->getSender());

        $message->subject('Test Subject');
        $this->assertSame('Test Subject', $message->getSubject());

        $message->to('test@to.com');
        $this->assertSame(['test@to.com' => null], $message->getTo());
    }

    /**
     * @test Test adding a content
     */
    public function testContent(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $message->content('this is the content', Message::CONTENT_TYPE_PLAIN, 'ascii');
        $this->assertSame('this is the content', $message->getContent());
        $this->assertSame(Message::CONTENT_TYPE_PLAIN, $message->getContentType());
        $this->assertSame('ascii', $message->getCharset());

        $message->contentAlternative('test content alternative');
        $message->contentType(Message::CONTENT_TYPE_HTML);
        $message->charset('utf-8');
        $this->assertSame('utf-8', $message->getCharset());
        $this->assertSame('text/html', $message->getContentType());
    }

    /**
     * @test Test adding an attachment with beforeAttachFile returning false -> afterAttachFile not fired
     */
    public function testAttachmentWithEventFalse(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager

            $this->assertIsArray(func_get_arg(2));
            $this->assertCount(1, func_get_arg(2));

            /** @var \Swift_Attachment $attachment */
            $attachment = func_get_arg(2)[0];
            $this->assertInstanceOf(\Swift_Attachment::class, $attachment); // the attachment

            $this->assertSame('the data of file.txt', $attachment->getBody());
            $this->assertSame('file.txt', $attachment->getFilename());

            return false;
        });

        $eventsManager->attach('mailer:afterAttachFile', function () {
            $this->fail('mailer:afterAttachFile should not be fired');
        });

        $manager->setEventsManager($eventsManager);
        $message->attachment(codecept_data_dir('fixtures/attachments/file.txt'));

        $this->assertSame(8, $this->getCount());

        // with no eventsManager
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $message->attachment(codecept_data_dir('fixtures/attachments/file.txt'));
    }

    /**
     * @test Test adding an attachment with beforeAttachFile not returning false -> afterAttachFile fired
     */
    public function testAttachmentWithEventsSuccess(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager

            $this->assertIsArray(func_get_arg(2));
            $this->assertCount(1, func_get_arg(2));

            /** @var \Swift_Attachment $attachment */
            $attachment = func_get_arg(2)[0];
            $this->assertInstanceOf(\Swift_Attachment::class, $attachment); // the attachment

            $this->assertSame('the data of file.txt', $attachment->getBody());
            $this->assertSame('file.txt', $attachment->getFilename());
        });

        $eventsManager->attach('mailer:afterAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager

            $this->assertIsArray(func_get_arg(2));
            $this->assertCount(1, func_get_arg(2));

            /** @var \Swift_Attachment $attachment */
            $attachment = func_get_arg(2)[0];
            $this->assertInstanceOf(\Swift_Attachment::class, $attachment); // the attachment

            $this->assertSame('the data of file.txt', $attachment->getBody());
            $this->assertSame('file.txt', $attachment->getFilename());
        });

        $manager->setEventsManager($eventsManager);
        $message->attachment(codecept_data_dir('fixtures/attachments/file.txt'));

        $this->assertSame(16, $this->getCount(), 'the events for attachments have not been fired');
    }

    /**
     * @test Test adding an attachment with data
     */
    public function testAttachmentWithData(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        // attachmentWithData with no option
        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager

            $this->assertIsArray(func_get_arg(2));
            $this->assertCount(1, func_get_arg(2));

            /** @var \Swift_Attachment $attachment */
            $attachment = func_get_arg(2)[0];
            $this->assertInstanceOf(\Swift_Attachment::class, $attachment); // the attachment

            $this->assertSame('data of the attachment', $attachment->getBody());
            $this->assertSame('name-of-file.txt', $attachment->getFilename());
        });

        $manager->setEventsManager($eventsManager);
        $message->attachmentData('data of the attachment', 'name-of-file.txt');

        // attachmentWithData with options mime and as
        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function ($event, $manager, $params) {
            /** @var \Swift_Attachment $attachment */
            $attachment = $params[0];

            $this->assertSame('new data of the attachment', $attachment->getBody());
            $this->assertSame('new-name-of-file.txt', $attachment->getFilename());
            $this->assertSame('mime-test', $attachment->getContentType());
        });

        $manager->setEventsManager($eventsManager);
        $message->attachmentData('new data of the attachment', 'name-of-file.txt', [
            'as'    => 'new-name-of-file.txt',
            'mime'  => 'mime-test'
        ]);

        $this->assertSame(11, $this->getCount(), 'the events for attachmentData have not been fired');
    }

    /**
     * @test Test adding some embed files
     */
    public function testEmbed(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $this->assertNotSame('', $message->embed('/path/to/file'));
        $this->assertNotSame('', $message->embedData('file data', 'name-of-file.txt'));
    }

    /**
     * @test Test ::send() with beforeSend returning false -> mail not sent
     */
    public function testSendEventBeforeSendFalse(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeSend', function ($event, $manager, $params) {
            $this->assertNull($params);

            return false;
        });
        $manager->setEventsManager($eventsManager);

        $this->assertSame(0, $message->send());
        $this->assertSame(2, $this->getCount());
    }

    /**
     * @test Test ::send() with beforeSend returning true -> mail sent
     */
    public function testSendEventBeforeSendTrue(): void
    {
        $manager = new Manager([
            'driver' => 'smtp',
            'port'   => getenv('DATA_MAILHOG_SMTP_PORT'),
            'host'   => getenv('DATA_MAILHOG_HOST_URI'),
            'from'     => [
                'email' => 'example_smtp@gmail.com',
                'name'  => 'EXAMPLE SMTP'
            ],
        ]);

        $message = new Message($manager);

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeSend', function ($event, $manager, $params) {
            $this->assertNull($params);

            return true;
        });

        $eventsManager->attach('mailer:afterSend', function ($event, $manager, $params) {
            $this->assertIsArray($params);
            $this->assertCount(2, $params);

            $this->assertSame(1, $params[0]); // number of sent mails
            $this->assertSame([], $params[1]); // failed recipients

            return true;
        });

        $manager->setEventsManager($eventsManager);

        $this->assertSame(1, $message->from('test@test.com')->to('test@test.com')->send());
        $this->assertSame(6, $this->getCount(), 'mailer events beforeSend and afterSend have not been sent');

        // Clean emails sent from MailHog
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            sprintf(
                "%s%s:%s/api/v1/",
                getenv('DATA_MAILHOG_HOST_PROTOCOL'),
                getenv('DATA_MAILHOG_HOST_URI'),
                getenv('DATA_MAILHOG_API_PORT')
            ) . 'messages'
        );

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_exec($ch);
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
    }
}
