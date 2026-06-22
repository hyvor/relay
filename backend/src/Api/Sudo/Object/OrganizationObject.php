<?php

namespace App\Api\Sudo\Object;

use Hyvor\Internal\Auth\Dto\Organization;

class OrganizationObject
{
    public int $id;
    public string $name;
    public ?string $billing_email;
    /** @var array<string, mixed>|null */
    public ?array $billing_address;

    public function __construct(Organization $organization)
    {
        $this->id = $organization->getId();
        $this->name = $organization->getName();
        $this->billing_email = $organization->getBillingEmail();
        $this->billing_address = $organization->getBillingAddress();
    }
}
