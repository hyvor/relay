<?php

namespace App\Api\Sudo\Authorization;

use App\Service\SudoUser\SudoUserService;
use Hyvor\Internal\Auth\Auth;
use Hyvor\Internal\Auth\AuthInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 200)]
class SudoAuthorizationListener
{
    public const string RESOLVED_SUDO_USER_ATTRIBUTE_KEY = 'sudo_api_resolved_user';

    public function __construct(
        private AuthInterface $auth,
        private SudoUserService $sudoUserService,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // only sudo API requests
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/sudo')) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->cookies->has(Auth::HYVOR_SESSION_COOKIE_NAME)) {
            throw new AccessDeniedHttpException('Session authentication required for sudo API access.');
        }

        $sessionCookie = $request->cookies->get(Auth::HYVOR_SESSION_COOKIE_NAME);
        assert($sessionCookie !== null);

        $user = $this->auth->check((string) $sessionCookie);

        if ($user === false) {
            throw new AccessDeniedHttpException('Invalid session.');
        }

        $sudoUser = $this->sudoUserService->findByHyvorUserId($user->id);

        if ($sudoUser === null) {
            throw new AccessDeniedHttpException('You do not have sudo access.');
        }

        // Store the authenticated user in request attributes
        $request->attributes->set(self::RESOLVED_SUDO_USER_ATTRIBUTE_KEY, $user);
    }
} 
