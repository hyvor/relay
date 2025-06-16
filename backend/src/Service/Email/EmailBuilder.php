<?php

namespace App\Service\Email;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailBuilder
{

    /**
     * @param Address[] $toAddress
     */
    public function build(
        Address $from,
        array $toAddress,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText
    ): string
    {
        $email = new Email()
            ->from($from)
            ->to(...$toAddress);

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

    /**
     * @param string|array{email: string, name?: string} $inputAddress
     */
    public static function createAddressFromInput(string|array $inputAddress): Address
    {
        if (is_string($inputAddress)) {
            return new Address($inputAddress);
        } else {
            return new Address($inputAddress['email'], $inputAddress['name'] ?? '');
        }
    }

}