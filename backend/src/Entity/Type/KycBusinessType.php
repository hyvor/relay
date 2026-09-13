<?php

namespace App\Entity\Type;

enum KycBusinessType: string
{

    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';

}
