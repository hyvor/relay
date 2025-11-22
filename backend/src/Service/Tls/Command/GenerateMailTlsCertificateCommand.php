<?php

namespace App\Service\Tls\Command;

use App\Entity\Type\TlsCertificateType;
use App\Service\App\Config;
use App\Service\Tls\Message\GenerateCertificateMessage;
use App\Service\Tls\TlsCertificateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AsCommand('tls:generate-mail-certificate', 'Generates a TLS certificate for mail servers')]
class GenerateMailTlsCertificateCommand extends Command
{

    public function __construct(
        private TlsCertificateService $tlsCertificateService,
        private MessageBusInterface $bus,
        private Config $config,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('domain');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $input->getArgument('domain') ?? 'mx.' . $this->config->getInstanceDomain();
        assert(is_string($domain));
        $cert = $this->tlsCertificateService->createCertificate(
            TlsCertificateType::MAIL,
            $domain
        );

        $message = new GenerateCertificateMessage($cert->getId());
        $this->bus->dispatch($message, [
            new TransportNamesStamp('sync') // handle immediately
        ]);

        return Command::SUCCESS;
    }


}