<?php

namespace App\Entity\Type;

enum ProjectSendType: string
{

    case TRANSACTIONAL = 'transactional';
    case DISTRIBUTIONAL = 'distributional';

}