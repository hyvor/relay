<?php

namespace App\Service\Docker;

class DockerService
{

    // docker hostname
    // other services can use this to connect to the Docker container
    public function getDockerHostname(): string
    {
        $hostname = gethostname();
        assert(is_string($hostname));
        return $hostname;
    }

}