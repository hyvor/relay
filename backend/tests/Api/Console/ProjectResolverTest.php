<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Resolver\ProjectResolver;
use App\Entity\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[CoversClass(ProjectResolver::class)]
class ProjectResolverTest extends TestCase
{

    public function test_returns_empty_on_other_controllers(): void
    {
        $request = Request::create('/');
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getControllerName')->willReturn('App\Controller\SomeOtherController');
        $argument->method('getType')->willReturn('App\Entity\Project');

        $resolver = new ProjectResolver();
        $result = $resolver->resolve($request, $argument);

        $this->assertSame([], iterator_to_array($result));
    }

    public function test_does_not_resolve_non_project(): void
    {
        $request = Request::create('/');
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('App\Entity\Send');
        $argument->method('getControllerName')->willReturn('App\Api\Console\Controller\SendController');

        $resolver = new ProjectResolver();
        $result = $resolver->resolve($request, $argument);

        $this->assertSame([], iterator_to_array($result));
    }

    public function test_gets_project_from_attributes(): void
    {
        $project = $this->createMock(Project::class);
        $request = Request::create('/');
        $request->attributes->set('console_api_resolved_project', $project);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->method('getType')->willReturn('App\Entity\Project');
        $argument->method('getControllerName')->willReturn('App\Api\Console\Controller\SomeController');

        $resolver = new ProjectResolver();
        $result = $resolver->resolve($request, $argument);

        $this->assertSame([$project], iterator_to_array($result));
    }

}