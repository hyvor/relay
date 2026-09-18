<?php

namespace App\Entity\Type;

enum KycContentOwnership: string
{
    case SELF = 'self';
    case THIRD_PARTY = 'third_party';
}
