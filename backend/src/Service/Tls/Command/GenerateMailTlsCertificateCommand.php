<?php

namespace App\Service\Tls\Command;

use App\Entity\Type\TlsCertificateType;
use App\Service\App\Config;
use App\Service\App\MessageTransport;
use App\Service\Tls\Exception\AnotherTlsGenerationRequestInProgressException;
use App\Service\Tls\MailTlsGenerator;
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
        private MailTlsGenerator $mailTlsGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->mailTlsGenerator->dispatchToGenerate(MessageTransport::SYNC);
        } catch (AnotherTlsGenerationRequestInProgressException) {
            $output->writeln('<error>Another TLS generation request is already in progress. Aborting.</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }


}