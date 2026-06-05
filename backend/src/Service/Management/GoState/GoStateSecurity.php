<?php

namespace App\Service\Management\GoState;

class GoStateSecurity
{

    public function __construct(
        /**
         * Allowed source IPs for incoming SMTP (empty = no restriction).
         * @var string[]
         */
        public array $allowedSourceIps = [],

        /**
         * Allowed sender domains for incoming SMTP (empty = no restriction).
         * @var string[]
         */
        public array $allowedSenderDomains = [],

        /**
         * Whether to delegate SMTP AUTH to Symfony backend.
         */
        public bool $smtpAuthViaSymfony = false,

        /**
         * Whether to allow sending via incoming SMTP without authentication.
         * When true, emails for non-instance domains are accepted and forwarded
         * using the API key configured in the UNAUTHENTICATED_SEND_API_KEY env var.
         */
        public bool $allowUnauthenticatedSending = false,
    ) {
    }

}
