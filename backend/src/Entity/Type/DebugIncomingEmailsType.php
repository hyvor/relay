<?php

namespace App\Entity\Type;

enum DebugIncomingEmailsType: string
{
    case BOUNCE = 'bounce';
    case FBL = 'fbl';
}
