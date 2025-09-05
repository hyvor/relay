<?php

namespace App\Entity\Type;

enum SendRecipientStatus: string
{

    // message is queued to be sent by a worker
    case QUEUED = 'queued';

    // the SMTP server has accepted the message
    case ACCEPTED = 'accepted';

    // the SMTP server has rejected the message with a 4xx code
    // or a network error occurred
    // the message will be retried later
    case DEFERRED = 'deferred';

    // the message was rejected by the SMTP server with a 5xx code
    // the recipient will be added to the suppression list
    // the message will not be retried
    case BOUNCED = 'bounced';

    // the recipient has complained about the message
    // (marked as spam)
    case COMPLAINED = 'complained';

    // tried our best to send the message, but failed
    // this happens after the exhaustion of retries
    case FAILED = 'failed';

}
