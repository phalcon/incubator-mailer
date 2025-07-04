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

namespace Phalcon\Incubator\Mailer\Tests;

use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Incubator\Mailer\Manager;
use Phalcon\Incubator\Mailer\Message;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Message::class)]
class MessageTest extends TestCase
{
    protected const FIXTURES_DIR = __DIR__ . '/fixtures';

    #[Test]
    #[TestDox('Test instantiating the message -> try to access getters with default values')]
    public function construct(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $this->assertSame([], $message->getBcc());
        $this->assertSame([], $message->getCc());
        $this->assertSame(PHPMailer::CHARSET_ISO88591, $message->getCharset());
        $this->assertSame('', $message->getContent());
        $this->assertSame('text/plain', $message->getContentType());
        $this->assertSame([], $message->getFailedRecipients());
        $this->assertSame('', $message->getFormat());
        $this->assertSame('', $message->getFrom());
        $this->assertNull($message->getPriority());
        $this->assertSame($manager, $message->getManager());
        $this->assertSame('', $message->getReadReceiptTo());
        $this->assertSame([], $message->getReplyTo());
        $this->assertSame('', $message->getReturnPath());
        $this->assertSame('', $message->getSender());
        $this->assertSame('', $message->getSubject());
        $this->assertSame([], $message->getTo());
        $this->assertSame([], $message->getHeaders());
        $this->assertSame('', $message->getLastError());
    }

    #[Test]
    #[TestDox('Test instantiating the message setting the headers to the message')]
    public function settersAndGettersMailHeaders(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $this->assertInstanceOf(Message::class, $message->setFormat('flowed'));

        $message->setReadReceiptTo('test-receipt@test.com');
        $this->assertSame('test-receipt@test.com', $message->getReadReceiptTo());

        $message->bcc('johndoe@test.com', 'John Doe');
        $this->assertSame(['johndoe@test.com' => 'John Doe'], $message->getBcc());

        // multiple BCC
        $message->bcc(['johndoe2@test.com', 'johndoe3@test.com' => 'John Doe 3']);
        $this->assertSame([
            'johndoe@test.com'  => 'John Doe',
            'johndoe2@test.com' => '',
            'johndoe3@test.com' => 'John Doe 3'
        ], $message->getBcc());

        $message->cc('johndoe4@test.com', 'John Doe');
        $this->assertSame(['johndoe4@test.com' => 'John Doe'], $message->getCc());

        // multiple CC
        $message->cc(['johndoe5@test.com' => 'John Doe 2', 'johndoe6@test.com']);
        $this->assertSame([
            'johndoe4@test.com'  => 'John Doe',
            'johndoe5@test.com' => 'John Doe 2',
            'johndoe6@test.com' => ''
        ], $message->getCc());

        $message->replyTo(['johndoe7@test.com' => 'John Doe 7']);
        $this->assertSame(['johndoe7@test.com' => 'John Doe 7'], $message->getReplyTo());

        // multiple Reply-To
        $message->replyTo(['johndoe8@test.com' => 'John Doe 8', 'johndoe9@test.com']);
        $this->assertSame([
            'johndoe7@test.com' => 'John Doe 7',
            'johndoe8@test.com' => 'John Doe 8',
            'johndoe9@test.com' => ''
        ], $message->getReplyTo());

        $message->from('johndoe-from@test.com', 'John Doe');
        $this->assertSame('johndoe-from@test.com', $message->getFrom());
        $this->assertSame('John Doe', $message->getFromName());

        $message->priority(5);
        $this->assertSame(5, $message->getPriority());

        $message->sender('johndoe@test5.com');
        $this->assertSame('johndoe@test5.com', $message->getSender());

        $message->setReturnPath('test-return@test.com');
        $this->assertSame('test-return@test.com', $message->getReturnPath());

        $message->subject('Test Subject');
        $this->assertSame('Test Subject', $message->getSubject());

        $message->to('test@to.com');
        $this->assertSame(['test@to.com' => ''], $message->getTo());

        // multiple To
        $message->to(['test2@to.com' => 'Test 2', 'test3@to.com'], 'Name of To');
        $this->assertSame([
            'test@to.com'  => '',
            'test2@to.com' => 'Test 2',
            'test3@to.com' => 'Name of To'
        ], $message->getTo());

        // Headers
        $message->addHeader('isTransactional', 'True');
        $this->assertSame(['isTransactional' => 'True'], $message->getHeaders());

        $message->addHeader('anotherHeader', 'valueHeader');
        $this->assertSame(['isTransactional' => 'True', 'anotherHeader' => 'valueHeader'], $message->getHeaders());
    }

    #[Test]
    #[TestDox('Test adding a content')]
    public function content(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $message->content('this is the content', Message::CONTENT_TYPE_PLAIN, 'ascii');
        $this->assertSame('this is the content', $message->getContent());
        $this->assertSame(Message::CONTENT_TYPE_PLAIN, $message->getContentType());
        $this->assertSame('ascii', $message->getCharset());

        $message = new Message($manager);
        $message->content('this is the content 2');
        $message->contentType(Message::CONTENT_TYPE_HTML);
        $message->charset('ascii');

        $this->assertSame('this is the content 2', $message->getContent());
        $this->assertSame(Message::CONTENT_TYPE_HTML, $message->getContentType());
        $this->assertSame('ascii', $message->getCharset());
    }

    #[Test]
    #[TestDox('Test adding an alternative content')]
    public function contentAlternative(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $message->contentAlternative('test content alternative', Message::CONTENT_TYPE_HTML, 'utf-8');
        $this->assertSame('test content alternative', $message->getMessage()->AltBody);
        $this->assertSame('utf-8', $message->getCharset());
        $this->assertSame('text/html', $message->getContentType());
    }

    #[Test]
    #[TestDox('Test adding an attachment with beforeAttachFile returning false -> afterAttachFile not fired')]
    public function attachmentWithEventFalse(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager
            $this->assertNull(func_get_arg(2));

            return false;
        });

        $eventsManager->attach('mailer:afterAttachFile', function () {
            $this->fail('mailer:afterAttachFile should not be fired');
        });

        $manager->setEventsManager($eventsManager);
        $message->attachment(self::FIXTURES_DIR . '/attachments/file.txt');

        $this->assertSame(4, $this->getCount());
        $this->assertEmpty($message->getMessage()->getAttachments());
    }

    #[Test]
    #[TestDox('Test adding attachment from files')]
    public function attachmentWithNoEventSuccess(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $filePath = self::FIXTURES_DIR . '/attachments/file.txt';

        // we add an attachment by a file
        $message->attachment($filePath);

        $this->assertCount(1, $attachments = $message->getMessage()->getAttachments());
        $this->assertArrayHasKey(0, $attachments);
        $this->assertIsArray($attachments[0]);

        $this->assertSame([
            $filePath,
            'file.txt',
            'file.txt',
            'base64',
            'text/plain',
            false,
            'attachment',
            'file.txt'
        ], $attachments[0]);

        // we add another one with options set
        $message->attachment($filePath, [
            'encoding' => '7bit',
            'mime'     => 'application/pdf',
            'as'       => 'new-name.pdf'
        ]);

        $this->assertCount(2, $attachments = $message->getMessage()->getAttachments());
        $this->assertArrayHasKey(1, $attachments);
        $this->assertIsArray($attachments[1]);

        $this->assertSame([
            $filePath,
            'file.txt',
            'new-name.pdf',
            '7bit',
            'application/pdf',
            false,
            'attachment',
            'new-name.pdf'
        ], $attachments[1]);
    }

    #[Test]
    #[TestDox('Test adding an attachment with beforeAttachFile not returning false -> afterAttachFile fired')]
    public function attachmentWithEventsSuccess(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager
            $this->assertNull(func_get_arg(2));
        });

        $eventsManager->attach('mailer:afterAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager
            $this->assertIsArray($params = func_get_arg(2));

            $this->assertSame([
                self::FIXTURES_DIR . '/attachments/file.txt',
                'file.txt',
                'file.txt',
                'base64',
                'text/plain',
                false,
                'attachment',
                'file.txt'
            ], $params);
        });

        $manager->setEventsManager($eventsManager);
        $message->attachment(self::FIXTURES_DIR . '/attachments/file.txt');

        $this->assertSame(9, $this->getCount(), 'the events for attachments have not been fired');
        $this->assertCount(1, $message->getMessage()->getAttachments());
    }

    #[Test]
    #[TestDox('Test adding an attachment with data')]
    public function attachmentWithData(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $expectedAttachment = [
            'data of the attachment',
            'name-of-file.txt',
            'name-of-file.txt',
            'base64',
            'text/plain',
            true,
            'attachment',
            0
        ];

        // attachmentData with no option
        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function () {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager
            $this->assertNull(func_get_arg(2));
        });

        $eventsManager->attach('mailer:afterAttachFile', function () use ($expectedAttachment) {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager

            $this->assertIsArray($params = func_get_arg(2));
            $this->assertSame($expectedAttachment, $params);
        });

        $manager->setEventsManager($eventsManager);
        $message->attachmentData('data of the attachment', 'name-of-file.txt');

        $this->assertCount(1, $attachments = $message->getMessage()->getAttachments());
        $this->assertSame($expectedAttachment, $attachments[0]);

        $expectedAttachment = [
            'new data of the attachment',
            'name-of-file-2.txt',
            'name-of-file-2.txt',
            '8bit',
            'mime-test',
            true,
            'attachment',
            0
        ];

        // attachmentData with options mime and encoding
        $eventsManager = new EventsManager();
        $eventsManager->attach('mailer:beforeAttachFile', function ($event, $manager, $params) {
            $this->assertInstanceOf(Event::class, $event); // the event
            $this->assertInstanceOf(Message::class, $manager); // the mailer manager
            $this->assertNull($params);
        });

        $eventsManager->attach('mailer:afterAttachFile', function () use ($expectedAttachment) {
            $this->assertSame(3, func_num_args());

            $this->assertInstanceOf(Event::class, func_get_arg(0)); // the event
            $this->assertInstanceOf(Message::class, func_get_arg(1)); // the mailer manager
            $this->assertIsArray($params = func_get_arg(2));

            $this->assertSame($expectedAttachment, $params);
        });

        $manager->setEventsManager($eventsManager);
        $message->attachmentData('new data of the attachment', 'name-of-file-2.txt', [
            'encoding' => '8bit',
            'mime'     => 'mime-test'
        ]);

        $this->assertCount(2, $attachments = $message->getMessage()->getAttachments());
        $this->assertSame($expectedAttachment, $attachments[1]);

        $this->assertSame(21, $this->getCount(), 'the events for attachmentData have not been fired');
    }

    #[Test]
    #[TestDox('Test adding a not found embed file -> exception from PHPMailer')]
    public function embedFileNotFound(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $this->expectException(PHPMailerException::class);

        $message->embed('', 'cid-file');
    }

    #[Test]
    #[TestDox('Test adding an embed file')]
    public function embedFileExistsNotRename(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $message->embed(self::FIXTURES_DIR . '/attachments/file.txt', 'file-cid');

        $this->assertCount(1, $attachments = $message->getMessage()->getAttachments());
        $this->assertSame('file.txt', $attachments[0][1]);
        $this->assertSame('file.txt', $attachments[0][2]);

        $message->embed(self::FIXTURES_DIR . '/attachments/file.txt', 'file-cid', 'rename.txt');

        $this->assertCount(2, $attachments = $message->getMessage()->getAttachments());
        $this->assertSame('file.txt', $attachments[1][1]);
        $this->assertSame('rename.txt', $attachments[1][2]);
    }

    #[Test]
    #[TestDox('Test adding an embed data')]
    public function embedFileData(): void
    {
        $manager = new Manager(['driver' => 'smtp']);
        $message = new Message($manager);

        $message->embedData('data of the embed', 'file-cid', 'file.txt');

        $this->assertCount(1, $attachments = $message->getMessage()->getAttachments());
        $this->assertSame('data of the embed', $attachments[0][0]);
        $this->assertSame('file.txt', $attachments[0][1]);
    }

    #[Test]
    #[TestDox('Test ::send() with beforeSend returning false -> mail not sent')]
    public function sendBeforeSendEventFalse(): void
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
}
