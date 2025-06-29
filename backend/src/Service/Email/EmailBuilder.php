<?php

namespace App\Service\Email;

use App\Config;
use App\Entity\Domain;
use App\Service\Instance\InstanceService;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

class EmailBuilder
{

    public function __construct(
        private Encryption $encryption,
        private InstanceService $instanceService,
        private Config $config
    )
    {
    }

    /**
     * @param array<string, string> $customHeaders
     * @return array{raw: string, messageId: string}
     */
    public function build(
        Domain $domain,
        Address $from,
        Address $to,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        array $customHeaders
    ): array
    {
        $email = new Email()
            ->from($from)
            ->to($to);

        if ($subject !== null) {
            $email->subject($subject);
        }

        if ($bodyHtml !== null) {
            $email->html($bodyHtml);
        }

        if ($bodyText !== null) {
            $email->text($bodyText);
        }

        // Add custom headers
        foreach ($customHeaders as $key => $value) {
            $email->getHeaders()->addTextHeader($key, $value);
        }

        // add message-id if not set
        $messageId = $email->generateMessageId();
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);

        // mailer header
        $email->getHeaders()->addTextHeader('X-Mailer', 'Hyvor Relay v' . $this->config->getAppVersion());

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
            $instance->getDomain(),
            $instance->getDkimPrivateKeyEncrypted(),
            InstanceService::DEFAULT_DKIM_SELECTOR
        );

        return [
            'raw' => $email->toString(),
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
        );

        return $signer->sign($email);

    }

}