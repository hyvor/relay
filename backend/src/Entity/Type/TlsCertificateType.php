<?php

namespace App\Entity\Type;

enum TlsCertificateType: string
{

    // mail server
    case MAIL = 'mail';

    // anything else?
    case OTHER = 'other'; // NOT USED YET, FOR PHPSTAN

}