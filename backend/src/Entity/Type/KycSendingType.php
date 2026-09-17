<?php

namespace App\Entity\Type;

enum KycSendingType: string
{

    case TRANSACTIONAL = 'transactional';
    case DISTRIBUTIONAL = 'distributional';

}
