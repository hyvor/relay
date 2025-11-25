<?php

namespace App\Service\Tls\Command;

use App\Service\App\MessageTransport;
use App\Service\Tls\Exception\AnotherTlsGenerationRequestInProgressException;
use App\Service\Tls\MailTlsGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            // @codeCoverageIgnoreStart
        } catch (AnotherTlsGenerationRequestInProgressException) {
            $output->writeln('<error>Another TLS generation request is already in progress. Aborting.</error>');
            return Command::FAILURE;
        }
        // @codeCoverageIgnoreEnd

        return Command::SUCCESS;
    }


}