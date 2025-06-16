<?php

namespace App\Service\Email;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailBuilder
{

    public function build(
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

        return $email->toString();
    }

}