<?php

namespace App\Entity\Type;

// see https://relay.hyvor.com/docs/domains#status
enum DomainStatus: string
{

    case PENDING = 'pending';
    case ACTIVE = 'active';
    case WARNING = 'warning';
    case SUSPENDED = 'suspended';

}