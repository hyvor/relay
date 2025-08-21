<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Object\ProjectUserSearchObject;
use Hyvor\Internal\Auth\AuthInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProjectUserController extends AbstractController
{
    public function __construct(

        private AuthInterface $auth,
    ) {
    }

    #[Route('/search-users', methods: 'GET')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    public function searchUsers(Request $request): JsonResponse
    {
        $emailSearch = $request->query->getString('email', '');
        $authUsers = $this->auth->fromEmail($emailSearch);
        $foundUsers = [];
        foreach ($authUsers as $authUser) {
            $foundUsers[] = new ProjectUserSearchObject($authUser);
        }
        return $this->json($foundUsers);
    }
}
