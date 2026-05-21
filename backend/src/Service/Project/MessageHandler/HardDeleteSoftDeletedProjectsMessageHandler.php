<?php

namespace App\Service\Project\MessageHandler;

use App\Service\Project\Message\HardDeleteSoftDeletedProjectsMessage;
use App\Service\Project\ProjectService;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HardDeleteSoftDeletedProjectsMessageHandler
{
    use ClockAwareTrait;

    public const int GRACE_PERIOD_DAYS = 30;

    public function __construct(
        private ProjectService $projectService,
    ) {
    }

    public function __invoke(HardDeleteSoftDeletedProjectsMessage $message): void
    {
        $this->projectService->hardDeleteSoftDeletedBefore(
            $this->now()->modify('-' . self::GRACE_PERIOD_DAYS . ' days')
        );
    }
}
