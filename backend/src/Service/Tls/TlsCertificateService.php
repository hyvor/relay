<?php

namespace App\Service\Tls;

use App\Entity\TlsCertificate;
use App\Entity\Type\TlsCertificateStatus;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Util\Crypt\Encryption;

class TlsCertificateService
{

    public function __construct(private EntityManagerInterface $em, private Encryption $encryption)
    {
    }

    public function getCertificateById(int $id): ?TlsCertificate
    {
        return $this->em->getRepository(TlsCertificate::class)->find($id);
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

    public function setCertificate(TlsCertificate $cert, string $certPem): void
    {
        $cert->setStatus(TlsCertificateStatus::ACTIVE);
        $cert->setCertificate($certPem);

        $this->em->persist($cert);
        $this->em->flush();
    }

}