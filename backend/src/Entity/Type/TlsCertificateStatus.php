<?php

namespace App\Entity\Type;

enum TlsCertificateStatus: string
{

    case PENDING = 'pending'; // being issued
    case FAILED = 'failed'; // issuance failed
    case ACTIVE = 'active'; // issued and valid
    case EXPIRED = 'expired'; // validity period ended
    case REVOKED = 'revoked'; // manually revoked

}