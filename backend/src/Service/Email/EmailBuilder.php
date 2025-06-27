<?php

namespace App\Service\Email;

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
        private InstanceService $instanceService
    )
    {
    }

    public function build(
        Domain $domain,
        Address $from,
        Address $to,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText
    ): string
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

        return $email->toString();
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