<?php

namespace App\Api\Console\Object;

use App\Api\Console\Authorization\Scope;
use App\Entity\ProjectUser;
use Hyvor\Internal\Auth\AuthUser;

class ProjectUserObject
{

    public int $id;
    public int $created_at;
    /**
     * @var string[]
     */
    public array $scopes;
    public ProjectUserMiniObject $user;
    public ?string $oidc_sub;
    public ProjectObject $project;


    public function __construct(ProjectUser $pu, AuthUser $authUser)
    {
        $this->id = $pu->getId();
        $this->created_at = $pu->getCreatedAt()->getTimestamp();
        $this->scopes = $pu->getScopes();
        $this->user = new ProjectUserMiniObject($authUser);
        $this->oidc_sub = $authUser->oidc_sub;
        $this->project = new ProjectObject($pu->getProject());
    }

}
