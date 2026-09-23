<?php

namespace App\Entity\Type;

enum KycAccountType: string
{
    case INDIVIDUAL = 'individual';
    case BUSINESS = 'business';
}
