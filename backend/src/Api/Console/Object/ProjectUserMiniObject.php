<?php

namespace App\Api\Console\Object;

use Hyvor\Internal\Auth\AuthUser;

class ProjectUserMiniObject
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $username;
    public ?string $picture_url;

    public function __construct(
        AuthUser $hyvorUser
    ) {
        $this->id = $hyvorUser->id;
        $this->name = $hyvorUser->name;
        $this->email = $hyvorUser->email;
        $this->username = $hyvorUser->username;
        $this->picture_url = $hyvorUser->picture_url;
    }
}
