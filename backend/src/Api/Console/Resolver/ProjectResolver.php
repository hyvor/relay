<?php

namespace App\Api\Console\Resolver;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Entity\Project;
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
        $argumentType = $argument->getType();
        if (!$argumentType || $argumentType !== Project::class) {
            return [];
        }

        $project = $request->attributes->get(AuthorizationListener::RESOLVED_PROJECT_ATTRIBUTE_KEY);

        if (!$project instanceof Project) {
            return [];
        }


        return [$project];
    }


}
