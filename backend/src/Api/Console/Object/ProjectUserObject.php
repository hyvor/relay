<?php

namespace App\Api\Console\Object;

use App\Api\Console\Authorization\Scope;
use App\Entity\ProjectUser;
use Hyvor\Internal\Auth\AuthUser;

class ProjectUserObject
{

    public int $id;
    /**
     * @var string[]
     */
    public array $scopes;
    public ProjectUserMiniObject $user;

    public function __construct(ProjectUser $pu, AuthUser $authUser)
    {
        $this->id = $pu->getId();
        $this->scopes = $pu->getScopes();
        $this->user = new ProjectUserMiniObject($authUser);

        // use the following to test custom scopes
        // in the frontend
//        $this->scopes = [
//            Scope::PROJECT_READ->value,
//        ];
    }

}
