<?php

namespace App\Tests\Api\Sudo;

use App\Api\Sudo\Authorization\SudoAuthorizationListener;
use App\Api\Sudo\Controller\SudoController;
use App\Service\Blacklist\IpBlacklist;
use App\Service\Blacklist\IpBlacklists;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SudoController::class)]
#[CoversClass(SudoAuthorizationListener::class)]
#[CoversClass(IpBlacklists::class)]
#[CoversClass(IpBlacklist::class)]
class SudoInitTest extends WebTestCase
{

    public function test_inits_sudo(): void
    {
        $this->sudoApi('POST', '/init');
        $this->assertResponseIsSuccessful();
        $json = $this->getJson();
        // add more tests if needed
    }

}