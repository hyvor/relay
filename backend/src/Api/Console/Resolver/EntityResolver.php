<?php

namespace App\Api\Console\Resolver;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Entity\ApiKey;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\ProjectUser;
use App\Entity\Send;
use App\Entity\Suppression;
use App\Entity\Webhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntityResolver implements ValueResolverInterface
{

    private const ENTITIES = [
        'sends' => Send::class,
        'domains' => Domain::class,
        'api-keys' => ApiKey::class,
        'webhooks' => Webhook::class,
        'suppressions' => Suppression::class,
        'project-users' => ProjectUser::class,
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return iterable<mixed>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $controllerName = $argument->getControllerName();
        if (!str_starts_with($controllerName, 'App\Api\Console\Controller\\')) {
            return [];
        }

        $argumentType = $argument->getType();
        if (!$argumentType || !str_starts_with($argumentType, 'App\Entity\\')) {
            return [];
        }

        if ($argumentType === Project::class) {
            return [];
        }

        $id = $request->attributes->get('id');
        $id = is_string($id) ? (int)$id : null;

        if (!$id) {
            throw new BadRequestException('Invalid ID');
        }

        $route = $request->getPathInfo();
        $route = str_replace('/api/console', '', $route);

        $parts = explode('/', $route);
        $path = $parts[1] ?? null;
        assert(is_string($path));

        $entityClass = self::ENTITIES[$path] ?? null;

        if (!$entityClass) {
            throw new \Exception('Entity for ' . $path . ' not found');
        }

        $repository = $this->em->getRepository($entityClass);
        $entity = $repository->find($id);

        if (!$entity) {
            throw new NotFoundHttpException('Entity not found');
        }

        $projectOfEntity = $entity->getProject();
        $currentProject = $request->attributes->get(AuthorizationListener::RESOLVED_PROJECT_ATTRIBUTE_KEY);
        assert($currentProject instanceof Project);

        if ($projectOfEntity->getId() !== $currentProject->getId()) {
            throw new AccessDeniedHttpException('Entity does not belong to the project');
        }

        return [$entity];
    }

}
