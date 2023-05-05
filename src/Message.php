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

use Phalcon\Incubator\Mailer\Manager;

/**
 * Class Message
 *
 * @package Phalcon\Mailer
 */
class Message
{
    /**
     * content type of PLAIN text.
     */
    public const CONTENT_TYPE_PLAIN = 'text/plain';

    /**
     * content type HTML text.
     */
    public const CONTENT_TYPE_HTML = 'text/html';

    protected Manager $manager;

    protected ?\Swift_Message $message = null;

    /**
     * An array of email which failed send to recipients.
     *
     * @var array<int, string>
     */
    protected array $failedRecipients = [];

    /**
     * Create a new Message using $mailer for sending from SwiftMailer
     *
     * @param Manager $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Set the from address of this message.
     *
     * You may pass an array of addresses if this message is from multiple people.
     * Example: ['receiver@domain.org', 'other@domain.org' => 'A name']
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param string|array<int|string, string> $email
     * @param string|null $name optional
     *
     * @see \Swift_Message::setFrom()
     */
    public function from($email, $name = null): self
    {
        $email = $this->normalizeEmail($email);

        $this->getMessage()->setFrom($email, $name);

        return $this;
    }

    /**
     * Get the from address of this message.
     *
     * @see \Swift_Message::getFrom()
     *
     * @return array<string, ?string>
     */
    public function getFrom(): array
    {
        return $this->getMessage()->getFrom();
    }

    /**
     * Set the reply-to address of this message.
     *
     * You may pass an array of addresses if replies will go to multiple people.
     * Example: ['receiver@domain.org', 'other@domain.org' => 'A name']
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param string|array<int|string, string> $email
     * @param string|null $name optional
     *
     * @see \Swift_Message::setReplyTo()
     */
    public function replyTo($email, ?string $name = null): self
    {
        $email = $this->normalizeEmail($email);

        $this->getMessage()->setReplyTo($email, $name);

        return $this;
    }

    /**
     * Get the reply-to address of this message (null or array).
     *
     * @see \Swift_Message::getReplyTo()
     *
     * @return string
     */
    public function getReplyTo()
    {
        return $this->getMessage()->getReplyTo();
    }

    /**
     * Set the to addresses of this message.
     *
     * If multiple recipients will receive the message an array should be used.
     * Example: ['receiver@domain.org', 'other@domain.org' => 'A name']
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param string|array<int|string, string> $email
     * @param string|null $name optional
     *
     * @see \Swift_Message::setTo()
     */
    public function to($email, ?string $name = null): self
    {
        $email = $this->normalizeEmail($email);

        $this->getMessage()->setTo($email, $name);

        return $this;
    }

    /**
     * Get the To addresses of this message.
     *
     * @return array<int|string, string>
     *
     * @see \Swift_Message::getTo()
     */
    public function getTo(): ?array
    {
        return $this->getMessage()->getTo();
    }

    /**
     * Set the Cc addresses of this message.
     *
     * If multiple recipients will receive the message an array should be used.
     * Example: ['receiver@domain.org', 'other@domain.org' => 'A name']
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param string|array<int|string, string> $email
     * @param string|null $name optional
     *
     * @return $this
     *
     * @see \Swift_Message::setCc()
     */
    public function cc($email, ?string $name = null): self
    {
        $email = $this->normalizeEmail($email);

        $this->getMessage()->setCc($email, $name);

        return $this;
    }

    /**
     * Get the Cc address of this message.
     *
     * @return ?array<int|string, string>
     *
     * @see \Swift_Message::getCc()
     */
    public function getCc(): ?array
    {
        return $this->getMessage()->getCc();
    }

    /**
     * Set the Bcc addresses of this message.
     *
     * If multiple recipients will receive the message an array should be used.
     * Example: ['receiver@domain.org', 'other@domain.org' => 'A name']
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param string|array<int|string, string> $email
     * @param string|null $name optional
     *
     * @see \Swift_Message::setBcc()
     */
    public function bcc($email, ?string $name = null): self
    {
        $email = $this->normalizeEmail($email);

        $this->getMessage()->setBcc($email, $name);

        return $this;
    }

    /**
     * Get the Bcc addresses of this message.
     *
     * @return ?array<int|string, string>
     *
     * @see \Swift_Message::getBcc()
     */
    public function getBcc(): ?array
    {
        return $this->getMessage()->getBcc();
    }

    /**
     * Set one sender of this message.
     *
     * This does not override the From field, but it has a higher significance.
     *
     * @param string $email
     * @param string|null $name optional
     *
     * @see \Swift_Message::setSender()
     */
    public function sender(string $email, ?string $name = null): self
    {
        $emails = $this->normalizeEmail($email);
        $emails = is_array($email) ? $email : [$emails];

        if (is_string($email)) {
            $this->getMessage()->setSender($email, $name);
        }

        return $this;
    }

    /**
     * Get the sender of this message (null or array).
     *
     * @see \Swift_Message::getSender()
     *
     * @return string
     */
    public function getSender()
    {
        return $this->getMessage()->getSender();
    }

    /**
     * Set the subject of this message.
     *
     * @param string $subject
     *
     * @see \Swift_Message::setSubject()
     */
    public function subject(string $subject): self
    {
        $this->getMessage()->setSubject($subject);

        return $this;
    }

    /**
     * Get the subject of this message.
     *
     * @see \Swift_Message::getSubject()
     */
    public function getSubject(): ?string
    {
        return $this->getMessage()->getSubject();
    }

    /**
     * Set the body of this message, either as a string, or as an instance of
     * {@link \Swift_OutputByteStream}.
     *
     * @param string|\Swift_OutputByteStream $content
     * @param string $contentType optional
     * @param string|null $charset     optional
     *
     * @see \Swift_Message::setBody()
     */
    public function content($content, string $contentType = self::CONTENT_TYPE_HTML, ?string $charset = null): self
    {
        $this->getMessage()->setBody($content, $contentType, $charset);

        return $this;
    }

    /**
     * Get the body of this message as a string.
     *
     * @see \Swift_Message::getBody()
     */
    public function getContent(): ?string
    {
        return $this->getMessage()->getBody();
    }

    /**
     * Add optionally an alternative body
     *
     * @param string $content
     * @param string|null $contentType optional
     * @param string|null $charset optional
     */
    public function contentAlternative(string $content, ?string $contentType = null, ?string $charset = null): self
    {
        $this->getMessage()->addPart($content, $contentType, $charset);

        return $this;
    }

    /**
     * Set the Content-type of this message.
     *
     * @param string $contentType
     *
     * @see \Swift_Message::setContentType()
     */
    public function contentType(string $contentType): self
    {
        $this->getMessage()->setContentType($contentType);

        return $this;
    }

    /**
     * Get the Content-type of this message.
     *
     * @return string
     *
     * @see \Swift_Message::getContentType()
     */
    public function getContentType(): string
    {
        return $this->getMessage()->getContentType();
    }

    /**
     * Set the character set of this message.
     *
     * @param string $charset
     *
     * @see \Swift_Message::setCharset()
     */
    public function charset(string $charset): self
    {
        $this->getMessage()->setCharset($charset);

        return $this;
    }

    /**
     * Get the character set of this message.
     *
     * @return string
     *
     * @see \Swift_Message::getCharset()
     */
    public function getCharset(): string
    {
        return $this->getMessage()->getCharset();
    }

    /**
     * Set the priority of this message.
     *
     * The value is an integer where 1 is the highest priority and 5 is the lowest.
     *
     * @param int $priority
     *
     * @return $this
     *
     * @see \Swift_Message::setPriority()
     */
    public function priority(int $priority): self
    {
        $this->getMessage()->setPriority($priority);

        return $this;
    }

    /**
     * Get the priority of this message.
     *
     * The returned value is an integer where 1 is the highest priority and 5
     * is the lowest.
     *
     * @return int
     *
     * @see \Swift_Message::getPriority()
     */
    public function getPriority(): int
    {
        return $this->getMessage()->getPriority();
    }

    /**
     * Ask for a delivery receipt from the recipient to be sent to $addresses
     *
     * @param array<int|string, string> $email
     *
     * @see \Swift_Message::setReadReceiptTo()
     */
    public function setReadReceiptTo(array $email): self
    {
        $email = $this->normalizeEmail($email);

        if (is_array($email)) {
            $this->getMessage()->setReadReceiptTo($email);
        }

        return $this;
    }

    /**
     * An array of email which failed send to recipients.
     *
     * @return array<int, string>
     */
    public function getFailedRecipients(): array
    {
        return $this->failedRecipients;
    }

    /**
     * Get the addresses to which a read-receipt will be sent (null or array).
     *
     * @see \Swift_Message::getReadReceiptTo()
     *
     * @return string
     */
    public function getReadReceiptTo()
    {
        return $this->getMessage()->getReadReceiptTo();
    }

    /**
     * Set the return-path (the bounce address) of this message.
     *
     * @param string $email
     *
     * @see \Swift_Message::setReturnPath()
     */
    public function setReturnPath(string $email): self
    {
        $this->getMessage()->setReturnPath($email);

        return $this;
    }

    /**
     * Get the return-path (bounce address) of this message.
     *
     * @see \Swift_Message::getReturnPath()
     */
    public function getReturnPath(): ?string
    {
        return $this->getMessage()->getReturnPath();
    }

    /**
     * Set the format of this message (flowed or fixed).
     *
     * @param string $format
     *
     * @see \Swift_Message::setFormat()
     */
    public function setFormat(string $format): self
    {
        $this->getMessage()->setFormat($format);

        return $this;
    }

    /**
     * Get the format of this message (i.e. flowed or fixed).
     *
     * @see \Swift_Message::getFormat()
     */
    public function getFormat(): string
    {
        return $this->getMessage()->getFormat() ?: '';
    }

    /**
     * Attach a file to the message.
     *
     * Events:
     * - mailer:beforeAttachFile
     * - mailer:afterAttachFile
     *
     * @param string $file
     * @param array<string, string> $options optional
     *
     * @see Phalcon\Mailer\Message::createAttachmentViaPath()
     * @see Phalcon\Mailer\Message::prepareAttachment()
     */
    public function attachment(string $file, array $options = []): self
    {
        $attachment = $this->createAttachmentViaPath($file);

        return $this->prepareAttachment($attachment, $options);
    }

    /**
     * Attach in-memory data as an attachment.
     *
     * @param string $data
     * @param string $name
     * @param array<string, string> $options optional
     *
     * @see Phalcon\Mailer\Message::createAttachmentViaData()
     * @see Phalcon\Mailer\Message::prepareAttachment()
     */
    public function attachmentData(string $data, string $name, array $options = []): self
    {
        $attachment = $this->createAttachmentViaData($data, $name);

        return $this->prepareAttachment($attachment, $options);
    }

    /**
     * Embed a file in the message and get the CID.
     *
     * @param string $file
     */
    public function embed(string $file): string
    {
        $embed = $this->createEmbedViaPath($file);

        return $this->getMessage()->embed($embed);
    }

    /**
     * Embed in-memory data in the message and get the CID.
     *
     * @param string $data
     * @param string $name
     */
    public function embedData(string $data, string $name): string
    {
        $embed = $this->createEmbedViaData($data, $name);

        return $this->getMessage()->embed($embed);
    }

    /**
     * Return a {@link \Swift_Message} instance
     */
    public function getMessage(): \Swift_Message
    {
        if (!$this->message) {
            $this->message = $this->getManager()->getSwift()->createMessage();
        }

        return $this->message;
    }

    /**
     * Return a {@link \Phalcon\Incubator\Mailer\Manager} instance
     *
     * @return \Phalcon\Incubator\Mailer\Manager
     */
    public function getManager(): Manager
    {
        return $this->manager;
    }

    /**
     * Send the given Message like it would be sent in a mail client.
     *
     * All recipients (with the exception of Bcc) will be able to see the other
     * recipients this message was sent to.
     *
     * Recipient/sender data will be retrieved from the Message object.
     *
     * The return value is the number of recipients who were accepted for
     * delivery.
     *
     * Events:
     * - mailer:beforeSend
     * - mailer:afterSend
     *
     * @see \Swift_Mailer::send()
     */
    public function send(): int
    {
        $eventManager = $this->getManager()->getEventsManager();

        if ($eventManager) {
            $result = $eventManager->fire('mailer:beforeSend', $this);
        } else {
            $result = true;
        }

        if ($result === false) {
            return 0;
        }

        $this->failedRecipients = [];

        $count = $this->getManager()->getSwift()->send(
            $this->getMessage(),
            $this->failedRecipients
        );

        if ($eventManager) {
            $eventManager->fire(
                'mailer:afterSend',
                $this,
                [
                    $count,
                    $this->failedRecipients
                ]
            );
        }

        return $count;
    }

    /**
     * Prepare and attach the given attachment.
     *
     * @param \Swift_Attachment $attachment
     * @param array<string, string> $options optional
     *
     * @see \Swift_Message::attach()
     */
    protected function prepareAttachment(\Swift_Attachment $attachment, array $options = []): self
    {
        if (isset($options['mime'])) {
            $attachment->setContentType($options['mime']);
        }

        if (isset($options['as'])) {
            $attachment->setFilename($options['as']);
        }

        $eventManager = $this->getManager()->getEventsManager();

        if ($eventManager) {
            $result = $eventManager->fire(
                'mailer:beforeAttachFile',
                $this,
                [
                    $attachment,
                ]
            );
        } else {
            $result = true;
        }

        if ($result !== false) {
            $this->getMessage()->attach($attachment);

            if ($eventManager) {
                $eventManager->fire(
                    'mailer:afterAttachFile',
                    $this,
                    [
                        $attachment,
                    ]
                );
            }
        }

        return $this;
    }

    /**
     * Create a Swift new Attachment from a filesystem path.
     *
     * @param string $file
     *
     * @see \Swift_Attachment::fromPath()
     */
    protected function createAttachmentViaPath(string $file): \Swift_Attachment
    {
        /** @var \Swift_ByteStream_FileByteStream $byteStream */
        $byteStream = $this->getManager()->getDI()->get(
            '\Swift_ByteStream_FileByteStream',
            [
                $file
            ]
        );

        /** @var \Swift_Attachment $attachment */
        $attachment = $this->getManager()->getDI()->get('\Swift_Attachment')
            ->setFile($byteStream);

        return $attachment;
    }

    /**
     * Create a Swift Attachment instance from data.
     *
     * @param string $data
     * @param string $name optional
     *
     * @return \Swift_Attachment
     *
     * @see \Swift_Attachment::newInstance()
     */
    protected function createAttachmentViaData(string $data, string $name): \Swift_Attachment
    {
        return $this->getManager()->getDI()->get(
            '\Swift_Attachment',
            [
                $data,
                $name
            ]
        );
    }

    /**
     * Create a Swift new Image from a filesystem path.
     *
     * @param string $file
     *
     * @see \Swift_Image::fromPath()
     */
    protected function createEmbedViaPath(string $file): \Swift_Image
    {
        /** @var \Swift_ByteStream_FileByteStream $byteStream */
        $byteStream = $this->getManager()->getDI()->get(
            '\Swift_ByteStream_FileByteStream',
            [
                $file
            ]
        );

        /** @var \Swift_Image $image */
        $image = $this->getManager()->getDI()->get('\Swift_Image')
            ->setFile($byteStream);

        return $image;
    }

    /**
     * Create a Swift new Image.
     *
     * @param string $data
     * @param string|null $name optional
     *
     * @see \Swift_Image::newInstance()
     */
    protected function createEmbedViaData(string $data, ?string $name = null): \Swift_Image
    {
        return $this->getManager()->getDI()->get(
            '\Swift_Image',
            [
                $data,
                $name
            ]
        );
    }

    /**
     * Normalize IDN domains.
     *
     * @param string|array<int|string, string>|\Traversable $email
     *
     * @return string|array
     */
    protected function normalizeEmail($email)
    {
        $emails = [];

        if (is_array($email) || $email instanceof \Traversable) {
            foreach ($email as $k => $v) {
                if (is_int($k)) {
                    $emails[$this->getManager()->normalizeEmail($v)] = null;
                } else {
                    $emails[$this->getManager()->normalizeEmail($k)] = $v;
                }
            }

            return $emails;
        } else {
            return $this->getManager()->normalizeEmail($email);
        }
    }
}
