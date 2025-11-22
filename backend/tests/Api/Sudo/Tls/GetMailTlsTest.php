<?php

namespace App\Tests\Api\Sudo\Tls;

use App\Api\Sudo\Controller\TlsController;
use App\Entity\Type\TlsCertificateType;
use App\Service\Tls\TlsCertificateService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\TlsCertificateFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TlsController::class)]
#[CoversClass(TlsCertificateService::class)]
class GetMailTlsTest extends WebTestCase
{

    public function test_gets_mail_tls(): void
    {
        $current = TlsCertificateFactory::createOne(['type' => TlsCertificateType::MAIL]);
        $instance = InstanceFactory::createOne([
            'mail_tls_certificate_id' => $current->getId(),
        ]);
        $latest = TlsCertificateFactory::createOne(['type' => TlsCertificateType::MAIL]);

        $this->sudoApi(
            'GET',
            '/tls/mail',
        );

        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertIsArray($json['current']);
        $this->assertIsArray($json['latest']);
        $this->assertSame($current->getId(), $json['current']['id']);
        $this->assertSame($latest->getId(), $json['latest']['id']);
    }

    public function test_when_no_latest(): void
    {
        $current = TlsCertificateFactory::createOne(['type' => TlsCertificateType::MAIL]);
        $instance = InstanceFactory::createOne([
            'mail_tls_certificate_id' => $current->getId(),
        ]);

        $this->sudoApi(
            'GET',
            '/tls/mail',
        );

        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertIsArray($json['current']);
        $this->assertNull($json['latest']);
        $this->assertSame($current->getId(), $json['current']['id']);
    }

}