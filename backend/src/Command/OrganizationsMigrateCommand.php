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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'organizations:migrate',
    description: 'Start migration to organizations',
)]
class OrganizationsMigrateCommand extends Command
{
	public function __construct(
		private EntityManagerInterface $em,
		private ProjectRepository $projectRepo,
		private ProjectUserRepository $puRepo,
		private CommsInterface $comms,
	)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
	{
		while (true) {
			/** @var Project[] $projects */
			$all_projects = $this->projectRepo->findBy(['organization_id' => null]);
			$project_owner_map = [];

			foreach($all_projects as $project) {
				$project_owner_map[$project->getUserId()][] = $project;
			}

			$updated_count = 0;

			foreach($project_owner_map as $owner_id => $projects) {
				try {
					/** @var InitOrgResponse $org */
					$org = $this->comms->send(new InitOrg($owner_id));

					foreach($projects as $project) {
						$project->setOrganizationId($org->orgId);
						$project_users = $this->puRepo->findBy(['project' => $project]);
						$project_user_ids = array_map(fn($p) => $p->getUserId(), $project_users);

						$this->comms->send(new EnsureMembers($org->orgId, $project_user_ids));
					}

					$this->em->flush();
					$updated_count++;
				} catch (\Exception $e) {
					$output->writeln(date('H:i:s') . ": Error updating user $owner_id: " . $e->getMessage());
					$this->em->clear();
				}
			}

			$output->writeln(date('H:i:s') . ": Updated $updated_count users");
			sleep(2);
		}

        return Command::SUCCESS;
    }
}
