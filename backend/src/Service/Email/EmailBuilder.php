<?php

namespace App\Service\Email;

use App\Entity\Send;
use Symfony\Component\Mime\Email;

class EmailBuilder
{

    public function build(
        string $fromAddress,
        string $toAddress,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText
    ): string
    {

        $email = new Email()
            ->from($fromAddress)
            ->to($toAddress);

        if ($subject !== null) {
            $email->subject($subject);
        }

        if ($bodyHtml !== null) {
            $email->html($bodyHtml);
        }

        if ($bodyText !== null) {
            $email->text($bodyText);
        }

        return $email->toString();
    }

}