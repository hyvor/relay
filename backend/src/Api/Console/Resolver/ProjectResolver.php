<?php

namespace App\Api\Console\Resolver;

use App\Entity\Project;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleApiAuthorizationListenerAbstract;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ProjectResolver implements ValueResolverInterface
{

    public function __construct()
    {
    }

    /**
     * @return iterable<Project>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $controllerName = $argument->getControllerName();
        if (!str_starts_with($controllerName, 'App\Api\Console\Controller\\')) {
            return [];
        }

        $argumentType = $argument->getType();
        if (!$argumentType || $argumentType !== Project::class) {
            return [];
        }

        $consoleAuthResults = $request->attributes->get(ConsoleApiAuthorizationListenerAbstract::ATTRIBUTE_KEY);
        if (!$consoleAuthResults instanceof ConsoleAuthResults) {
            return [];
        }

        $project = $consoleAuthResults->getResource();
        assert($project instanceof Project);

        return [$project];
    }


}
