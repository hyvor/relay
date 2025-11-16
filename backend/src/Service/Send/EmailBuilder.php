<?php

namespace App\Service\Send;

use App\Entity\Domain;
use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\Send\Dto\SendingAttachment;
use App\Service\Send\Exception\EmailTooLargeException;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Uid\Uuid;

class EmailBuilder
{

    public function __construct(
        private Encryption $encryption,
        private InstanceService $instanceService,
        private Config $config
    ) {
    }

    /**
     * @param Address[] $to
     * @param Address[] $cc
     * @param Address[] $bcc
     * @param array<string, string> $customHeaders
     * @param array<SendingAttachment> $attachments
     * @return array{raw: string, uuid: string, messageId: string}
     * @throws EmailTooLargeException
     */
    public function build(
        Domain $domain,
        Address $from,
        array $to,
        array $cc,
        array $bcc,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        array $customHeaders,
        array $attachments
    ): array {
        $email = new Email()
            ->from($from)
            ->to(...$to)
            ->cc(...$cc)
            ->bcc(...$bcc);

        if ($subject !== null) {
            $email->subject($subject);
        }

        if ($bodyHtml !== null) {
            $email->html($bodyHtml);
        }

        if ($bodyText !== null) {
            $email->text($bodyText);
        }

        // add attachments
        foreach ($attachments as $attachment) {
            $email->attach(
                $attachment->content,
                $attachment->name,
                $attachment->contentType
            );
        }

        // Add custom headers
        foreach ($customHeaders as $key => $value) {
            $email->getHeaders()->addHeader($key, $value);
        }

        // add message-id if not set
        $uuid = Uuid::v4();
        $messageId = $uuid . '@' . $domain->getDomain();
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);

        // mailer header
        $email->getHeaders()->addTextHeader('X-Mailer', 'Hyvor Relay v' . $this->config->getAppVersion());

        /**
         * Here we check the email size before signing it because
         * DKIM signing is expensive and slow. It will only add a couple of KBs at most,
         * so checking the size before signing is fine.
         */
        if (strlen($email->toString()) > SendLimits::MAX_EMAIL_SIZE) {
            throw new EmailTooLargeException();
        }

        // DKIM with the sending domain
        $email = $this->signEmail(
            $email,
            $domain->getDomain(),
            $domain->getDkimPrivateKeyEncrypted(),
            $domain->getDkimSelector()
        );

        // DKIM with the instance domain
        $instance = $this->instanceService->getInstance();
        $email = $this->signEmail(
            $email,
            $this->config->getInstanceDomain(),
            $instance->getDkimPrivateKeyEncrypted(),
            InstanceService::DEFAULT_DKIM_SELECTOR
        );

        $raw = $email->toString();

        return [
            'raw' => $raw,
            'uuid' => (string)$uuid,
            'messageId' => $messageId,
        ];
    }

    private function signEmail(
        Message $email,
        string $domain,
        string $dkimPrivateKeyEncrypted,
        string $dkimSelector
    ): Message {
        $dkimPrivateKey = $this->encryption->decryptString($dkimPrivateKeyEncrypted);

        $signer = new DkimSigner(
            $dkimPrivateKey,
            $domain,
            $dkimSelector,
            defaultOptions: [
                'headers_to_ignore' => ['DKIM-Signature'],
            ]
        );

        return $signer->sign($email);
    }

}