<?php

namespace App\Command;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\EnsureMembers;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\InitOrg;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\InitOrgResponse;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Clock\ClockInterface;
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
	public function __construct(
		private EntityManagerInterface $em,
		private CommsInterface $comms,
		private KernelInterface $kernel,
        private ClockInterface $clock
	)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
	{
		while (true) {
			/** @var int[] $ownerIds */
            $ownerIds = $this->em->createQueryBuilder()
                ->select('DISTINCT p.user_id')
                ->from(Project::class, 'p')
                ->where('p.organization_id = 0')
                ->andWhere('p.user_id != 0')
                ->setMaxResults(50)
                ->getQuery()
                ->getSingleColumnResult();

            if ($this->kernel->getEnvironment() === 'test' && count($ownerIds) === 0) {
                $output->writeln("{$this->clock->now()->format('Y-m-d H:i:s')}: No more projects to migrate.");
                break;
            }

            $count = 0;

            foreach ($ownerIds as $ownerId) {
				try {
                    $output->writeln("{$this->clock->now()->format('Y-m-d H:i:s')}: Migrating owner ID: $ownerId");

                    $this->em->wrapInTransaction(function () use ($ownerId, &$count) {
						/** @var InitOrgResponse $org */
						$org = $this->comms->send(new InitOrg($ownerId));

						$this->em->createQueryBuilder()
							->update(Project::class, 'p')
							->set('p.organization_id', ':orgId')
							->where('p.user_id = :ownerId')
							->andWhere('p.organization_id = 0')
							->setParameter('orgId', $org->orgId)
							->setParameter('ownerId', $ownerId)
							->getQuery()
							->execute();

						$conn = $this->em->getConnection();
                        /** @var string[] $rawMemberIds */
                        $rawMemberIds = $conn->fetchFirstColumn(
							'SELECT DISTINCT pu.user_id FROM project_users pu
							 JOIN projects p ON p.id = pu.project_id
							 WHERE p.organization_id = :orgId',
							['orgId' => $org->orgId]
						);

						if (count($rawMemberIds) > 0) {
							$memberIds = array_map('intval', $rawMemberIds);

							$this->comms->send(new EnsureMembers(
								$org->orgId,
								$memberIds
							));
						}

						$count++;
					});
				} catch (\Exception $e) {
					if ($this->kernel->getEnvironment() === 'test') {
						throw $e;
					}

					$output->writeln("{$this->clock->now()->format('Y-m-d H:i:s')}: Error updating owner $ownerId: " . $e->getMessage());
				}
            }

            $output->writeln("{$this->clock->now()->format('Y-m-d H:i:s')}: Updated $count owners");
            $this->clock->sleep(2);
        }

        return Command::SUCCESS;
    }
}
