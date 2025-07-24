<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Controller\SendController;
use App\Api\Console\Resolver\EntityResolver;
use App\Api\Local\Controller\LocalController;
use App\Entity\Send;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(EntityResolver::class)]
class EntityResolverTest extends KernelTestCase
{

    private function getEntityResolver(): EntityResolver
    {
        $resolver = $this->container->get(EntityResolver::class);
        $this->assertInstanceOf(EntityResolver::class, $resolver);
        return $resolver;
    }

    public function test_does_not_concern_with_other_controllers(): void
    {
        $resolver = $this->getEntityResolver();
        $request = $this->createMock(Request::class);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getControllerName')->willReturn(LocalController::class);

        $result = $resolver->resolve($request, $argument);
        $this->assertSame([], iterator_to_array($result));
    }

    public function test_only_concerns_entites(): void
    {
        $resolver = $this->getEntityResolver();
        $request = $this->createMock(Request::class);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('App\Namespace');
        $argument->method('getControllerName')->willReturn('App\Api\Console\Controller\SomeController');

        $result = $resolver->resolve($request, $argument);
        $this->assertSame([], iterator_to_array($result));
    }

    public function test_does_not_concern_project(): void
    {
        $resolver = $this->getEntityResolver();
        $request = $this->createMock(Request::class);
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('App\Entity\Project');
        $argument->method('getControllerName')->willReturn('App\Api\Console\Controller\SomeController');

        $result = $resolver->resolve($request, $argument);
        $this->assertSame([], iterator_to_array($result));
    }

    public function test_fails_if_request_does_not_have_id_attribute(): void
    {
        $resolver = $this->getEntityResolver();
        $request = Request::create('/');
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(Send::class);
        $argument->method('getControllerName')->willReturn(SendController::class);

        $this->expectException(BadRequestException::class);
        $resolver->resolve($request, $argument);
    }

    public function test_throws_on_wrong_resource(): void
    {
        $resolver = $this->getEntityResolver();
        $request = Request::create('/api/console/invalid');
        $request->attributes->set('id', "1");
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(Send::class);
        $argument->method('getControllerName')->willReturn(SendController::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Entity for invalid not found');
        $resolver->resolve($request, $argument);
    }

    public function test_404_on_entity_not_found(): void
    {
        $resolver = $this->getEntityResolver();
        $request = Request::create('/api/console/sends');
        $request->attributes->set('id', "9999");
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(Send::class);
        $argument->method('getControllerName')->willReturn(SendController::class);

        $this->expectException(NotFoundHttpException::class);
        $resolver->resolve($request, $argument);
    }

    public function test_access_denied_when_entity_belongs_to_different_project(): void
    {
        $resolver = $this->getEntityResolver();
        $request = Request::create('/api/console/sends');
        $send = SendFactory::createOne(['project' => ProjectFactory::createOne()]);
        $request->attributes->set('id', (string)$send->getId());

        $project1 = ProjectFactory::createOne();
        $request->attributes->set('console_api_resolved_project', $project1);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(Send::class);
        $argument->method('getControllerName')->willReturn(SendController::class);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Entity does not belong to the project');
        $resolver->resolve($request, $argument);
    }

    public function test_returns_entity(): void
    {
        $resolver = $this->getEntityResolver();
        $request = Request::create('/api/console/sends');
        $send = SendFactory::createOne(['project' => ProjectFactory::createOne()]);
        $request->attributes->set('id', (string)$send->getId());

        $project = $send->getProject();
        $request->attributes->set('console_api_resolved_project', $project);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn(Send::class);
        $argument->method('getControllerName')->willReturn(SendController::class);

        $result = $resolver->resolve($request, $argument);
        $this->assertCount(1, iterator_to_array($result));

        $returnedSend = iterator_to_array($result)[0];
        $this->assertInstanceOf(Send::class, $returnedSend);
        $this->assertSame($send->getId(), $returnedSend->getId());
    }

}