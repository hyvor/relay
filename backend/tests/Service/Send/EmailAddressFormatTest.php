<?php

namespace App\Tests\Service\Send;

use App\Service\Send\EmailAddressFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddressFormat::class)]
class EmailAddressFormatTest extends TestCase
{

    public function test_domain(): void
    {
        $this->assertSame('example.com', EmailAddressFormat::getDomainFromEmail('supun@example.com'));
        $this->assertSame('test.example.com', EmailAddressFormat::getDomainFromEmail('supun@test.example.com'));
    }

    public function test_address_from_input(): void
    {
        // string
        $address = EmailAddressFormat::createAddressFromInput('supun@hyvor.com');
        $this->assertSame('supun@hyvor.com', $address->getAddress());
        $this->assertSame('', $address->getName());
        // array
        $address = EmailAddressFormat::createAddressFromInput(['email' => 'supun@hyvor.com', 'name' => 'Supun']);
        $this->assertSame('supun@hyvor.com', $address->getAddress());
        $this->assertSame('Supun', $address->getName());
        // array without name
        $address = EmailAddressFormat::createAddressFromInput(['email' => 'test@hyvor.com']);
        $this->assertSame('test@hyvor.com', $address->getAddress());
        $this->assertSame('', $address->getName());
    }

    public function test_addresses_from_input(): void
    {
        // string
        $addresses = EmailAddressFormat::createAddressesFromInput('supun@hyvor.com');
        $this->assertCount(1, $addresses);
        $this->assertSame('supun@hyvor.com', $addresses[0]->getAddress());
        $this->assertSame('', $addresses[0]->getName());

        // array directly
        $addresses = EmailAddressFormat::createAddressesFromInput(['email' => 'ishini@hyvor.com', 'name' => 'Ishini']);
        $this->assertCount(1, $addresses);
        $this->assertSame('ishini@hyvor.com', $addresses[0]->getAddress());
        $this->assertSame('Ishini', $addresses[0]->getName());

        // array multiple
        $addresses = EmailAddressFormat::createAddressesFromInput([
            ['email' => 'supun@hyvor.com', 'name' => 'Supun'],
            ['email' => 'nadil@hyvor.com', 'name' => 'Nadil'],
        ]);
        $this->assertCount(2, $addresses);
        $this->assertSame('supun@hyvor.com', $addresses[0]->getAddress());
        $this->assertSame('Supun', $addresses[0]->getName());

        $this->assertSame('nadil@hyvor.com', $addresses[1]->getAddress());
        $this->assertSame('Nadil', $addresses[1]->getName());
    }

}