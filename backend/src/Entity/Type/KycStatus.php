<?php

namespace App\Entity\Type;

enum KycStatus: string
{

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

}
