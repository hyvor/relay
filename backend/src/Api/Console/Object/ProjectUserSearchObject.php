<?php

namespace App\Api\Console\Object;

use Hyvor\Internal\Auth\AuthUser;

class ProjectUserSearchObject
{
    public int $id;
    public string $email;
    public string $name;
    public ?string $picture_url;
    public ?string $oidc_sub;

    public function __construct(AuthUser $authUser)
    {
        $this->id = $authUser->id;
        $this->email = $authUser->email;
        $this->name = $authUser->name;
        $this->picture_url = $authUser->picture_url;
        $this->oidc_sub = $authUser->oidc_sub;
    }
}
