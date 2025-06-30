<?php

namespace App\Api\Console\Resolver;

use App\Entity\ApiKey;
use App\Entity\Domain;
use App\Entity\Project;
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

    public const ENTITIES = [
        'emails' => Send::class,
        'domain' => Domain::class,
        'api-keys' => ApiKey::class,
        'webhooks' => Webhook::class,
        'suppressions' => Suppression::class,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ProjectResolver $projectResolver,
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

        if (!$path) {
            throw new \Exception('Invalid resource');
        }

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

        $argumentMetadata = new ArgumentMetadata(
            'project',
            Project::class,
            false,
            false,
            null,
            controllerName: $controllerName
        );
        $currentProject = (array)$this->projectResolver->resolve($request, $argumentMetadata);
        if ($projectOfEntity->getId() !== $currentProject[0]->getId()) {
            throw new AccessDeniedHttpException('Entity does not belong to the project');
        }

        return [$entity];
    }

}
