<?php

namespace App\Service\Tls;

use App\Entity\TlsCertificate;
use App\Entity\Type\TlsCertificateStatus;
use App\Entity\Type\TlsCertificateType;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Clock\ClockAwareTrait;

class TlsCertificateService
{

    use ClockAwareTrait;

    public function __construct(private EntityManagerInterface $em, private Encryption $encryption)
    {
    }

    public function getCertificateById(int $id): ?TlsCertificate
    {
        return $this->em->getRepository(TlsCertificate::class)->find($id);
    }

    public function getLatestCertificateByType(TlsCertificateType $type): ?TlsCertificate
    {
        return $this->em->getRepository(TlsCertificate::class)->findOneBy(
            ['type' => $type],
            ['id' => 'DESC']
        );
    }

    public function getDecryptedPrivateKey(TlsCertificate $cert): \OpenSSLAsymmetricKey
    {
        $privateKeyPem = $this->encryption->decryptString($cert->getPrivateKeyEncrypted());

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new \RuntimeException('Failed to load private key');
        }

        return $privateKey;
    }

    public function createCertificate(
        TlsCertificateType $type,
        string $domain
    ): TlsCertificate {
        $privateKeyResource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        assert($privateKeyResource !== false);
        openssl_pkey_export($privateKeyResource, $privateKeyPem);
        assert(is_string($privateKeyPem));
        $encryptedPrivateKey = $this->encryption->encryptString($privateKeyPem);

        $cert = new TlsCertificate();
        $cert->setCreatedAt($this->clock->now());
        $cert->setUpdatedAt($this->clock->now());
        $cert->setType($type);
        $cert->setDomain($domain);
        $cert->setStatus(TlsCertificateStatus::PENDING);
        $cert->setPrivateKeyEncrypted($encryptedPrivateKey);

        $this->em->persist($cert);
        $this->em->flush();

        return $cert;
    }

    public function setCertificate(TlsCertificate $cert, string $certPem): void
    {
        $cert->setStatus(TlsCertificateStatus::ACTIVE);
        $cert->setCertificate($certPem);

        $this->em->persist($cert);
        $this->em->flush();
    }

}