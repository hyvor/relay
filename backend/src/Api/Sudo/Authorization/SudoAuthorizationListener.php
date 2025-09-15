<?php

namespace App\Api\Sudo\Authorization;

use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\Auth\AuthUser;
use Hyvor\Internal\Bundle\Api\DataCarryingHttpException;
use Hyvor\Internal\Sudo\SudoUserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 200)]
class SudoAuthorizationListener
{
    private const string RESOLVED_USER_ATTRIBUTE_KEY = 'sudo_api_resolved_user';
    private const string RESOLVED_SUDO_USER_ATTRIBUTE_KEY = 'sudo_api_resolved_sudo_user';

    public function __construct(
        private AuthInterface $auth,
        private SudoUserService $sudoUserService
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // only sudo API requests
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/sudo')) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->auth->check($request);

        if ($user === false) {
            throw new AccessDeniedHttpException('Invalid session.');
        }

        $sudoUser = $this->sudoUserService->get($user->id);

        if ($sudoUser === null) {
            throw new AccessDeniedHttpException('You do not have sudo access.');
        }

        $request->attributes->set(self::RESOLVED_USER_ATTRIBUTE_KEY, $user);
        $request->attributes->set(self::RESOLVED_SUDO_USER_ATTRIBUTE_KEY, $sudoUser);
    }

    public static function getUser(Request $request): AuthUser
    {
        $user = $request->attributes->get(self::RESOLVED_USER_ATTRIBUTE_KEY);
        assert($user instanceof AuthUser, 'User must be an instance of AuthUser');
        return $user;
    }
} 
