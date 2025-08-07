<?php

namespace App\Api\Console\Input\Domain;

use App\Entity\Domain;
use App\Entity\Project;
use App\Service\Domain\DomainService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Validator\Constraints as Assert;

class DomainIdOrDomainInput
{

    #[Assert\When(
        'this.domain == null',
        constraints: [
            new Assert\NotBlank(message: 'Either id or domain must be provided.'),
        ]
    )]
    public ?int $id = null;

    #[Assert\When(
        'this.id == null',
        constraints: [
            new Assert\NotBlank(message: 'Either id or domain must be provided.'),
        ]
    )]
    public ?string $domain = null;


    public function validateAndGetDomain(Project $project, DomainService $domainService): Domain
    {
        if ($this->id) {
            $domain = $domainService->getDomainById($this->id);
        } else {
            assert(is_string($this->domain));
            $domain = $domainService->getDomainByProjectAndName($project, $this->domain);
        }

        if (!$domain) {
            throw new BadRequestException('Domain not found');
        }

        if ($domain->getProject() !== $project) {
            throw new BadRequestException('Domain does not belong to the project');
        }

        return $domain;
    }

}