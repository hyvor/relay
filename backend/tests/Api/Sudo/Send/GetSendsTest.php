<?php

namespace App\Tests\Api\Sudo\Send;

use App\Api\Console\Object\SendObject;
use App\Api\Console\Object\SendRecipientObject;
use App\Api\Sudo\Controller\SendController;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Send\SendService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SendController::class)]
#[CoversClass(SendService::class)]
#[CoversClass(SendObject::class)]
#[CoversClass(SendRecipientObject::class)]
class GetSendsTest extends WebTestCase
{
    public function test_lists_sends_across_all_projects(): void
    {
        $projectA = ProjectFactory::createOne();
        $projectB = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        SendFactory::createMany(3, [
            'project' => $projectA,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendFactory::createMany(2, [
            'project' => $projectB,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $response = $this->sudoApi('GET', '/sends');

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        /** @var array<int, array<string, mixed>> $projects */
        $projects = $json['projects'];
        $this->assertCount(5, $sends);
        $this->assertCount(2, $projects);

        foreach ($sends as $send) {
            $this->assertArrayHasKey('id', $send);
            $this->assertArrayHasKey('uuid', $send);
            $this->assertArrayHasKey('project_id', $send);
            $this->assertArrayNotHasKey('project', $send);
        }

        foreach ($projects as $project) {
            $this->assertArrayHasKey('id', $project);
            $this->assertArrayHasKey('name', $project);
        }
    }

    public function test_filters_by_project_id(): void
    {
        $projectA = ProjectFactory::createOne();
        $projectB = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        SendFactory::createMany(3, [
            'project' => $projectA,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendFactory::createMany(2, [
            'project' => $projectB,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $response = $this->sudoApi('GET', '/sends?project_id=' . $projectA->getId());

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        $this->assertCount(3, $sends);

        foreach ($sends as $send) {
            $this->assertSame($projectA->getId(), $send['project_id']);
        }
    }

    public function test_returns_404_when_project_id_unknown(): void
    {
        $response = $this->sudoApi('GET', '/sends?project_id=999999');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_pagination_with_before_id(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $sends = SendFactory::createMany(7, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);

        $sends = array_reverse($sends);
        $cursor = $sends[4]->getId();

        $response = $this->sudoApi('GET', "/sends?limit=5&before_id={$cursor}");
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        $this->assertCount(2, $sends);

        foreach ($sends as $send) {
            $this->assertLessThan($cursor, $send['id']);
        }
    }

    public function test_filter_by_status(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $sendBounced = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendRecipientFactory::createOne([
            'send' => $sendBounced,
            'status' => SendRecipientStatus::BOUNCED,
        ]);

        $sendAccepted = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendRecipientFactory::createOne([
            'send' => $sendAccepted,
            'status' => SendRecipientStatus::ACCEPTED,
        ]);

        $response = $this->sudoApi('GET', '/sends?status=bounced');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        $this->assertCount(1, $sends);
        $this->assertSame($sendBounced->getId(), $sends[0]['id']);
    }

    public function test_filter_by_from_search(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        SendFactory::createMany(2, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'fromAddress' => 'thibault@hyvor.com',
        ]);
        SendFactory::createMany(3, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'fromAddress' => 'supun@hyvor.com',
        ]);

        $response = $this->sudoApi('GET', '/sends?from_search=thibault');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        $this->assertCount(2, $sends);
    }

    public function test_filter_by_subject_search(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        SendFactory::createMany(2, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'subject' => 'Hello World',
        ]);
        SendFactory::createMany(3, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'subject' => 'Goodbye World',
        ]);

        $response = $this->sudoApi('GET', '/sends?subject_search=Hello');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        $this->assertCount(2, $sends);
    }

    public function test_filter_by_to_search(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        $matchingSend = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendRecipientFactory::createOne([
            'send' => $matchingSend,
            'address' => 'thibault@hyvor.com',
        ]);

        $otherSend = SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
        ]);
        SendRecipientFactory::createOne([
            'send' => $otherSend,
            'address' => 'someone@hyvor.com',
        ]);

        $response = $this->sudoApi('GET', '/sends?to_search=thibault');
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        $this->assertCount(1, $sends);
        $this->assertSame($matchingSend->getId(), $sends[0]['id']);
    }

    public function test_filter_by_date_range(): void
    {
        $project = ProjectFactory::createOne();
        $domain = DomainFactory::createOne();
        $queue = QueueFactory::createOne();

        SendFactory::createMany(3, [
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'createdAt' => new \DateTimeImmutable('2025-01-01'),
        ]);
        SendFactory::createOne([
            'project' => $project,
            'domain' => $domain,
            'queue' => $queue,
            'createdAt' => new \DateTimeImmutable('2025-02-01'),
        ]);

        $response = $this->sudoApi(
            'GET',
            '/sends?date_from_search=2025-01-01&date_to_search=2025-01-01'
        );
        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $json */
        $json = $this->getJson();
        /** @var array<int, array<string, mixed>> $sends */
        $sends = $json['sends'];
        $this->assertCount(3, $sends);
    }

    public function test_fails_when_not_sudo(): void
    {
        $this->sudoApi('GET', '/sends', createSudoUser: false);
        $this->assertResponseStatusCodeSame(403);
    }
}
