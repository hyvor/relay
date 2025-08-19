<?php

namespace App\Api\Console\Object;

use App\Api\Console\Authorization\Scope;
use App\Entity\ProjectUser;

class ProjectUserObject
{

    /**
     * @var string[]
     */
    public array $scopes;
    public ProjectObject $project;

    public function __construct(ProjectUser $pu)
    {
        $this->scopes = $pu->getScopes();
        $this->project = new ProjectObject($pu->getProject());

        // use the following to test custom scopes
        // in the frontend
//        $this->scopes = [
//            Scope::PROJECT_READ->value,
//        ];
    }

}