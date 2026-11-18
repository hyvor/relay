<?php

namespace App\Service\Cloud;

use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\VerifyMember;

class OrganizationMemberService
{
    public function __construct(
        private AuthInterface $auth,
        private CommsInterface $comms,
    ) {}

    public function isMember(int $organizationId, string $email): bool
    {
        foreach ($this->auth->fromEmail($email) as $user) {
            if ($this->comms->send(new VerifyMember($organizationId, $user->id))->isMember()) {
                return true;
            }
        }

        return false;
    }
}
