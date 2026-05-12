<?php

namespace App\Service\Project;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Queue;
use App\Repository\DomainRepository;
use App\Service\Instance\InstanceService;
use App\Service\Project\Event\ProjectsDeletedEvent;
use App\Service\Project\MessageHandler\HardDeleteSoftDeletedProjectsMessageHandler;
use App\Service\Queue\QueueService;
use App\Service\Send\SendService;
use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\Auth\AuthUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mime\Address;
use Twig\Environment;

#[AsEventListener(ProjectsDeletedEvent::class, method: 'onProjectsDeleted')]
class ProjectsDeletedMailListener
{

    public function __construct(
        private InstanceService $instanceService,
        private DomainRepository $domainRepository,
        private QueueService $queueService,
        private SendService $sendService,
        private AuthInterface $auth,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function onProjectsDeleted(ProjectsDeletedEvent $event): void
    {
        if ($event->projects === []) {
            return;
        }

        /** @var array<int, Project[]> $projectsByOwner */
        $projectsByOwner = [];
        foreach ($event->projects as $project) {
            $projectsByOwner[$project->getUserId()][] = $project;
        }

        $systemProject = $this->instanceService->getInstance()->getSystemProject();
        $systemDomain = $this->domainRepository->findOneBy(['project' => $systemProject]);
        $queue = $this->queueService->getTransactionalQueue();

        if ($systemDomain === null || $queue === null) {
            $this->logger->error(
                'Cannot send project deletion notification: system domain or transactional queue is missing.',
                ['owner_count' => count($projectsByOwner)]
            );
            return;
        }

        $authUsers = $this->auth->fromIds(array_keys($projectsByOwner));

        foreach ($projectsByOwner as $ownerId => $projects) {
            $authUser = $authUsers[$ownerId] ?? null;
            if ($authUser === null) {
                $this->logger->warning(
                    'Skipping project deletion notification: user not found.',
                    ['user_id' => $ownerId, 'project_ids' => array_map(fn(Project $p) => $p->getId(), $projects)]
                );
                continue;
            }

            $this->sendNotification($systemProject, $systemDomain, $queue, $authUser, $projects);
        }
    }

    /**
     * @param Project[] $projects
     */
    private function sendNotification(
        Project $systemProject,
        Domain $systemDomain,
        Queue $queue,
        AuthUser $authUser,
        array $projects,
    ): void {
        $bodyHtml = $this->twig->render('mail/projects_deleted.twig', [
            'auth_user' => $authUser,
            'projects' => $projects,
            'grace_period_days' => HardDeleteSoftDeletedProjectsMessageHandler::GRACE_PERIOD_DAYS,
        ]);

        $this->sendService->createSend(
            project: $systemProject,
            domain: $systemDomain,
            queue: $queue,
            from: new Address('noreply@' . $systemDomain->getDomain(), 'Hyvor Relay'),
            to: [new Address($authUser->email, $authUser->name)],
            cc: [],
            bcc: [],
            subject: 'Your projects have been deleted',
            bodyHtml: $bodyHtml,
            bodyText: null,
            customHeaders: [],
            attachments: [],
        );
    }
}
