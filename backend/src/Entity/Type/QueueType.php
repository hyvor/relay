<?php

namespace App\Entity\Type;

enum QueueType: string
{

    case TRANSACTIONAL = 'transactional';
    case DISTRIBUTIONAL = 'distributional';
    case DEDICATED = 'dedicated'; // NOT YET IMPLEMENTED (#83)

    public static function fromProjectSendType(ProjectSendType $sendType): self
    {
        return match ($sendType) {
            ProjectSendType::TRANSACTIONAL => self::TRANSACTIONAL,
            ProjectSendType::DISTRIBUTIONAL => self::DISTRIBUTIONAL,
        };
    }

}
