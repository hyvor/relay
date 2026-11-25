<?php

namespace App\Entity\Type;

/**
 * Why a delivery attempt to a recipient was classified as it was.
 *
 * Classification is done in the Go worker, from the SMTP response
 * (worker/smtp_response_parser.go: SmtpResponseParser::BounceReason()) for direct
 * send attempts, and from the DSN status (worker/bounceparse/dsn.go) for
 * incoming async bounces.
 */
enum BounceReason: string
{
    /**
     * Permanent (5xx) failure caused by the recipient address, e.g. mailbox does not exist.
     * Identified by enhanced codes 5.1.1, 5.1.2, 5.1.3 and 5.5.0
     * (SmtpResponseParser::IsRecipientBounce()). The address is suppressed.
     */
    case RECIPIENT = 'recipient';

    /**
     * Rejected due to our sending infrastructure or reputation, e.g. spam filtering,
     * blocklists, policy restrictions. Identified by enhanced codes 4.7.x and 5.7.x
     * (SmtpResponseParser::IsInfrastructureError()). Recorded as an infrastructure bounce;
     * the address is not suppressed.
     */
    case INFRASTRUCTURE = 'infrastructure';

    /**
     * Any other permanent (5xx) bounce that matches neither of the above,
     * and the default for attempts that were not bounces.
     */
    case UNKNOWN = 'unknown';
}
