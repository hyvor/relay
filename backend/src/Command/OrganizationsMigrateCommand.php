<?php

namespace App\Command;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\ProjectUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\EnsureMembers;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\InitOrg;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\InitOrgResponse;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'organizations:migrate',
    description: 'Start migration to organizations',
)]
class OrganizationsMigrateCommand extends Command
{

	use ClockAwareTrait;

	public function __construct(
		private EntityManagerInterface $em,
		private ProjectRepository $projectRepo,
		private ProjectUserRepository $puRepo,
		private CommsInterface $comms,
		private KernelInterface $kernel
	)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/* while (true) { */
		// 	/** @var Project[] $projects */ */
		/* 	$all_projects = $this->projectRepo->findBy(['organization_id' => null]); */
		/**/
		/* 	if ($this->kernel->getEnvironment() === 'test' && count($all_projects) === 0) { */
		/* 		$output->writeln(date('H:i:s') . ": No more users to update"); */
		/*               break; */
		/*           } */
		/**/
		/* 	$project_owner_map = []; */
		/**/
		/* 	foreach($all_projects as $project) { */
		/* 		$project_owner_map[$project->getUserId()][] = $project; */
		/* 	} */
		/**/
		/* 	$updated_count = 0; */
		/**/
		/* 	foreach($project_owner_map as $owner_id => $projects) { */
		/* 		try { */
		/* 			$this->em->wrapInTransaction(function () use ($owner_id, $projects, $output) { */
		// 				/** @var InitOrgResponse $org */ */
		/* 				$org = $this->comms->send(new InitOrg($owner_id)); */
		/**/
		/* 				$all_member_ids = []; */
		/**/
		/* 				foreach($projects as $project) { */
		/* 					$project->setOrganizationId($org->orgId); */
		/* 					$project_users = $this->puRepo->findBy(['project' => $project]); */
		/* 					foreach($project_users as $pu) { */
		/* 						$all_member_ids[] = $pu->getUserId(); */
		/* 					} */
		/* 				} */
		/**/
		/* 				$this->em->flush(); */
		/**/
		/* 				$unique_member_ids = array_values(array_unique($all_member_ids)); */
		/* 				if (!empty($unique_member_ids)) { */
		/* 					$this->comms->send(new EnsureMembers($org->orgId, $unique_member_ids)); */
		/* 				} */
		/* 			}); */
		/**/
		/* 			$updated_count++; */
		/* 		} catch (\Exception $e) { */
		/* 			$output->writeln(date('H:i:s') . ": Error updating user $owner_id: " . $e->getMessage()); */
		/* 			$this->em->clear(); */
		/* 		} */
		/* 	} */
		/**/
		/* 	$output->writeln(date('H:i:s') . ": Updated $updated_count users"); */
		/* 	sleep(2); */
		/* } */

		while (true) {
            $ownerIds = $this->em->createQueryBuilder()
                ->select('DISTINCT p.user_id')
                ->from(Project::class, 'p')
                ->where('p.organization_id IS NULL')
                ->setMaxResults(50)
                ->getQuery()
                ->getSingleColumnResult();

            if ($this->kernel->getEnvironment() === 'test' && count($ownerIds) === 0) {
                $output->writeln("{$this->now()->format('H:i:s')}: No more projects to migrate.");
                break;
            }

            $count = 0;

            foreach ($ownerIds as $ownerId) {
				try {
					$this->em->wrapInTransaction(function () use ($ownerId, $output, &$count) {
						/** @var InitOrgResponse $org */
						$org = $this->comms->send(new InitOrg($ownerId));

						$this->em->createQueryBuilder()
							->update(Project::class, 'p')
							->set('p.organization_id', ':orgId')
							->where('p.user_id = :ownerId')
							->andWhere('p.organization_id IS NULL')
							->setParameter('orgId', $org->orgId)
							->setParameter('ownerId', $ownerId)
							->getQuery()
							->execute();

						$conn = $this->em->getConnection();
						$memberIds = $conn->fetchFirstColumn(
							'SELECT DISTINCT pu.user_id FROM project_users pu
							 JOIN projects p ON p.id = pu.project_id
							 WHERE p.organization_id = :orgId',
							['orgId' => $org->orgId]
						);

						if (count($memberIds) > 0) {
							$this->comms->send(new EnsureMembers(
								$org->orgId,
								array_map('intval', $memberIds)
							));
						}

						$count++;
					});
				} catch (\Exception $e) {
					if ($this->kernel->getEnvironment() === 'test') {
						throw $e;
					}

					$output->writeln("{$this->now()->format('H:i:s')}: Error updating owner $ownerId: " . $e->getMessage());
				}
            }

            $output->writeln("{$this->now()->format('H:i:s')}: Updated $count owners");
            sleep(2);
        }

        return Command::SUCCESS;
    }
}
