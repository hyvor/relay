<?php

namespace App\Service\Send;

class SendLimits
{

    public const MAX_RECIPIENTS_PER_SEND = 20;

    /**
     * A sensible limit of 998 characters, based on Google's header limits:
     * https://support.google.com/a/answer/14016360?hl=en&src=supportwidget
     */
    public const MAX_SUBJECT_LENGTH = 998;

    public const MAX_BODY_LENGTH = 2 * 1024 * 1024; // 2MB

    public const MAX_EMAIL_SIZE = 10 * 1024 * 1024; // 10MB

}
