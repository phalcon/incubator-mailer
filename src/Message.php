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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

use function end;
use function is_string;
use function is_int;

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

    /**
     * PHPMailer instance representing an unique message
     */
    protected PHPMailer $message;

    /**
     * An array of email which failed send to recipients.
     *
     * @var array<int, string>
     */
    protected array $failedRecipients = [];

    /**
     * Create a new Message using $mailer for sending from PHPMailer
     */
    public function __construct(protected Manager $manager)
    {
        // we get a cloned PHPMailer to only get the config set from the manager and returned an unique 'message'
        $this->message = clone $manager->getMailer();
    }

    /**
     * Set the unique from address of this message.
     *
     * If `$name` is passed, this name will be associated with the address.
     *
     * @see PHPMailer::setFrom()
     */
    public function from(string $email, string $name = ''): self
    {
        $this->message->setFrom($email, $name, false);

        return $this;
    }

    /**
     * Get the from address of this message.
     *
     * @see PHPMailer::From
     */
    public function getFrom(): string
    {
        return $this->message->From;
    }

    /**
     * Get the name of the from adress of this message.
     *
     * @see PHPMailer::FromName
     */
    public function getFromName(): string
    {
        return $this->message->FromName;
    }

    /**
     * Add reply-to addresses of this message.
     *
     * You may pass an array of addresses if replies will go to multiple people.
     * Example: ['receiver@domain.org', 'other@domain.org' => 'A name']
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param string|array<int|string, string> $email
     * @param string $name optional
     *
     * @see \Swift_Message::setReplyTo()
     */
    public function replyTo(string|array $email, string $name = ''): self
    {
        foreach ($this->handleEmails($email) as $email => $emailName) {
            $this->message->addReplyTo($email, $emailName ?: $name);
        }

        return $this;
    }

    /**
     * Get the reply-to addresses (email => name) of this message.
     *
     * @return array<string, string>
     * @see PHPMailer::getReplyToAddresses()
     *
     */
    public function getReplyTo(): array
    {
        return $this->flattenArray($this->message->getReplyToAddresses());
    }

    /**
     * Add to addresses of this message.
     *
     * If multiple recipients will receive the message an array should be used.
     * Example: ['receiver@domain.org', 'other@domain.org' => 'A name']
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param string|array<int|string, string> $email
     * @param string $name optional
     *
     * @see PHPMailer:addAddress()
     */
    public function to(string|array $email, string $name = ''): self
    {
        foreach ($this->handleEmails($email) as $email => $emailName) {
            $this->message->addAddress($email, $emailName ?: $name);
        }

        return $this;
    }

    /**
     * Get the To addresses (email => name) of this message.
     *
     * @return array<string, string>
     *
     * @see \PHPMailer::getToAddresses()
     */
    public function getTo(): array
    {
        return $this->flattenArray($this->message->getToAddresses());
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
     * @param string $name optional
     *
     * @see PHPMailer::addCC()
     */
    public function cc(string|array $email, string $name = ''): self
    {
        foreach ($this->handleEmails($email) as $email => $emailName) {
            $this->message->addCC($email, $emailName ?: $name);
        }

        return $this;
    }

    /**
     * Get the Cc address (email -> name) of this message.
     *
     * @return array<string, string>
     *
     * @see PHPMailer::getCc()
     */
    public function getCc(): array
    {
        return $this->flattenArray($this->message->getCcAddresses());
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
     * @param string $name optional
     *
     * @see PHPMailer::addBCC()
     */
    public function bcc(string|array $email, string $name = ''): self
    {
        foreach ($this->handleEmails($email) as $email => $emailName) {
            $this->message->addBCC($email, $emailName ?: $name);
        }

        return $this;
    }

    /**
     * Get the Bcc addresses (email => name) of this message.
     *
     * @return array<string, string>
     *
     * @see PHPMailer::getBccAddresses()
     */
    public function getBcc(): array
    {
        return $this->flattenArray($this->message->getBccAddresses());
    }

    /**
     * Set one sender of this message.
     *
     * This does not override the From field, but it has a higher significance.
     *
     * @param string $email
     * @param string $name optional
     *
     * @see PHPMailer::setFrom()
     */
    public function sender(string $email, string $name = ''): self
    {
        $this->message->setFrom($email, $name);

        return $this;
    }

    /**
     * Get the sender of this message
     *
     * @see PHPMailer::Sender
     */
    public function getSender(): string
    {
        return $this->message->Sender;
    }

    /**
     * Set the subject of this message.
     *
     * @see PHPMailer::Subject
     */
    public function subject(string $subject): self
    {
        $this->message->Subject = $subject;

        return $this;
    }

    /**
     * Get the subject of this message.
     *
     * @see PHPMailer::Subject
     */
    public function getSubject(): ?string
    {
        return $this->message->Subject;
    }

    /**
     * Set the body of this message, must be a string (in plain text or HTML)
     *
     * @param string $content
     * @param string $contentType optional
     * @param string|null $charset optional
     * @return Message
     */
    public function content(
        string $content,
        string $contentType = self::CONTENT_TYPE_HTML,
        ?string $charset = null
    ): self {
        $this->message->ContentType = $contentType;
        $this->message->Body = $content;

        if ($charset) {
            $this->message->CharSet = $charset;
        }

        return $this;
    }

    /**
     * Get the body of this message as a string.
     *
     * @see PHPMailer::Body
     */
    public function getContent(): string
    {
        return $this->message->Body;
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
        $this->message->AltBody = $content;

        if ($contentType) {
            $this->message->ContentType = $contentType;
        }

        if ($charset) {
            $this->message->CharSet = $charset;
        }

        return $this;
    }

    /**
     * Set the Content-type of this message.
     *
     * @see PHPMailer::ContentType
     */
    public function contentType(string $contentType): self
    {
        $this->message->ContentType = $contentType;

        return $this;
    }

    /**
     * Get the Content-type of this message.
     *
     * @see PHPMailer::ContentType
     */
    public function getContentType(): string
    {
        return $this->message->ContentType;
    }

    /**
     * Set the character set of this message.
     *
     * @param string $charset
     *
     * @see PHPMailer::CharSet
     */
    public function charset(string $charset): self
    {
        $this->message->CharSet = $charset;

        return $this;
    }

    /**
     * Get the character set of this message.
     *
     * @see PHPMailer::CharSet
     */
    public function getCharset(): string
    {
        return $this->message->CharSet;
    }

    /**
     * Set the priority of this message.
     *
     * The value is an integer where 1 is the highest priority and 5 is the lowest.
     *
     * @see PHPMailer::Priority
     */
    public function priority(int $priority): self
    {
        $this->message->Priority = $priority;

        return $this;
    }

    /**
     * Get the priority of this message.
     *
     * The returned value is an integer where 1 is the highest priority and 5
     * is the lowest.
     *
     * @see PHPMailer::Priority
     */
    public function getPriority(): ?int
    {
        return $this->message->Priority;
    }

    /**
     * Ask for a delivery receipt from the recipient to be sent to $email
     *
     * @see PHPMailer::ConfirmReadingTo
     */
    public function setReadReceiptTo(string $email): self
    {
        $this->message->ConfirmReadingTo = $email;

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
     * Get the adresse to which a read-receipt will be sent
     *
     * @see PHPMailer::ConfirmReadingTo
     */
    public function getReadReceiptTo(): string
    {
        return $this->message->ConfirmReadingTo;
    }

    /**
     * Set the return-path (the bounce address) of this message.
     *
     * @see PHPMailer::Sender
     */
    public function setReturnPath(string $email): self
    {
        $this->message->Sender = $email;

        return $this;
    }

    /**
     * Get the return-path (bounce address) of this message.
     *
     * @see PHPMailer::Sender
     */
    public function getReturnPath(): string
    {
        return $this->message->Sender;
    }

    /**
     * Set the format of this message (flowed or fixed).
     *
     * @todo PHPMailer doesn't support flowed or fixed format
     * @deprecated
     */
    public function setFormat(string $format): self
    {
        return $this;
    }

    /**
     * Get the format of this message (i.e. flowed or fixed).
     *
     * @todo PHPMailer doesn't support flowed or fixed format
     * @deprecated
     */
    public function getFormat(): string
    {
        return '';
    }

    /**
     * Attach a file to the message.
     *
     * Events:
     *
     * - mailer:beforeAttachFile
     * - mailer:afterAttachFile
     *
     * @param string $file File path
     * @param array{encoding?: string, mime?: string, as?: string} $options
     *
     * @throws PHPMailerException If the attachment has not been added from PHPMailer
     *
     * @see self::prepareAttachment()
     */
    public function attachment(string $file, array $options = []): self
    {
        return $this->prepareAttachment($file, '', $options);
    }

    /**
     * Attach in-memory data as an attachment.
     *
     * Events:
     *
     * - mailer:beforeAttachFile
     * - mailer:afterAttachFile
     *
     * @param string $data Source of the attachment
     * @param string $name Name of the attachment
     * @param array{encoding?: string, mime?: string} $options
     *
     * @throws PHPMailerException If the attachment has not been added from PHPMailer
     *
     * @see self::prepareAttachment()
     */
    public function attachmentData(string $data, string $name, array $options = []): self
    {
        return $this->prepareAttachment($data, $name, $options);
    }

    /**
     * Prepares an attachment by triggering beforeAttachFile and afterAttachFile events and adding it to the message
     *
     * @throws PHPMailerException If the attachment has not been added from PHPMailer
     */
    protected function prepareAttachment(string $source, string $name = '', array $options = []): self
    {
        $eventsManager = $this->manager->getEventsManager();

        // Trigger beforeAttachFile event, doesn't attach the file if it returned false
        if ($eventsManager && $eventsManager->fire('mailer:beforeAttachFile', $this) === false) {
            return $this;
        }

        $encoding = $options['encoding'] ?? PHPMailer::ENCODING_BASE64;
        $type = $options['mime'] ?? '';

        // Add the attachment by file or data with encoding and type options
        if (!$name) {
            $this->message->addAttachment($source, $options['as'] ?? '', $encoding, $type);
        } else {
            $this->message->addStringAttachment($source, $name, $encoding, $type);
        }

        $attachments = $this->message->getAttachments();

        // Trigger afterAttachFile event with the informations of the attachment from PHPMailer
        if ($eventsManager) {
            $eventsManager->fire('mailer:afterAttachFile', $this, end($attachments));
        }

        return $this;
    }

    /**
     * Embed a file in the message with the cid that you choose
     *
     * @param string $file File path
     * @param string $cid Content ID of the attachment
     *
     * @see PHPMailer::addEmbeddedImage()
     */
    public function embed(string $file, string $cid, string $filename = ''): void
    {
        $this->message->addEmbeddedImage($file, $cid, $filename);
    }

    /**
     * Embed in-memory in the message with the cid that you choose
     *
     * @param string $filename If you want to override the file name
     * @param string $cid Content ID of the attachment
     *
     * @see PHPMailer::addStringEmbeddedImage()
     */
    public function embedData(string $data, string $cid, string $filename = ''): void
    {
        $this->message->addStringEmbeddedImage($data, $cid, $filename);
    }

    /**
     * Add a custom header to the message
     *
     * @param string $name Header name
     * @param string|null $value Header value
     *
     * @throws PHPMailerException If the header is incorrect
     * @see PHPMailer::addCustomHeader()
     */
    public function addHeader(string $name, ?string $value = null): void
    {
        $this->message->addCustomHeader($name, $value);
    }

    /**
     * Return all custom headers of the message (name of header -> its value)
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->flattenArray($this->message->getCustomHeaders());
    }

    /**
     * Send the given Message like it would be sent in a mail client.
     *
     * All recipients (with the exception of Bcc) will be able to see the other
     * recipients this message was sent to.
     *
     * Recipient/sender data will be retrieved from the Message object.
     *
     * The return value is the number of recipients who were accepted for delivery.
     *
     * Events:
     *
     * - mailer:beforeSend
     * - mailer:afterSend, parameters: bool $count (number of sent mails), array<int, string> $failedRecipients
     *
     * @see PHPMailer::send()
     * @see self::getLastError() if the return value equals to 0
     */
    public function send(): int
    {
        $eventManager = $this->getManager()->getEventsManager();

        // Trigger beforeSend event and doesn't send if it returned false
        if ($eventManager && $eventManager->fire('mailer:beforeSend', $this) === false) {
            return 0;
        }

        $this->failedRecipients = [];
        $count = 0;

        /**
         * We tell PHPMailer to give us the failed recipients and number of sent mails
         *
         * PHPMailer asks for a string, but any callable can be set
         * @phpstan-ignore-next-line
         */
        $this->message->action_function = function (bool $result, array $to) use (&$count): void {
            foreach ($to as $recipient) {
                if (!$result) {
                    $this->failedRecipients[] = $recipient[0];
                } else {
                    $count++;
                }
            }
        };

        // We don't throw an exception from PHPMailer but $count will equal to 0 (e.g. failed recipients for SMTP)
        try {
            $this->message->send();
        } catch (PHPMailerException) {
        }

        // Trigger afterSend with number of sent mails and failed recipients
        if ($eventManager) {
            $eventManager->fire('mailer:afterSend', $this, [$count, $this->failedRecipients]);
        }

        return $count;
    }

    /**
     * Handle one or multiple emails to always return an associative array which each key
     * is an email and its value a name or empty string if not set
     *
     * @param string|array<int|string, string>|iterable $email
     *
     * @return array<string, string>
     */
    protected function handleEmails(string|iterable $email): array
    {
        $emails = [];

        if (is_string($email)) {
            return [$email => ''];
        }

        foreach ($email as $k => $v) {
            if (is_int($k)) {
                $emails[$v] = '';
            } else {
                $emails[$k] = $v;
            }
        }

        return $emails;
    }

    /**
     * Flattens an array from PHPMailer to return an associative array
     *
     * @param array<int, array{0: string, 1: string}> $mailerArray
     *
     * @return array<string, string>
     */
    protected function flattenArray(array $mailerArray): array
    {
        if (!$mailerArray) {
            return [];
        }

        $flattenedArray = [];
        foreach ($mailerArray as $array) {
            $flattenedArray[$array[0]] = $array[1];
        }

        return $flattenedArray;
    }

    /**
     * Return the PHPMailer instance representing the message
     */
    public function getMessage(): PHPMailer
    {
        return $this->message;
    }

    /**
     * Return a {@link \Phalcon\Incubator\Mailer\Manager} instance
     */
    public function getManager(): Manager
    {
        return $this->manager;
    }

    /**
     * Return the most recent error message from PHPMailer
     */
    public function getLastError(): string
    {
        return $this->message->ErrorInfo;
    }
}
