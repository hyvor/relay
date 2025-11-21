<?php

namespace App\Command;

use App\Service\App\Config;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Auth\AuthMethod;
use Hyvor\Internal\Auth\Oidc\OidcApiService;
use Hyvor\Internal\InternalConfig;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('verify', 'Verifies the application setup and configuration.')]
class VerifyCommand extends Command
{

    public function __construct(
        private Config $appConfig,
        private InternalConfig $internalConfig,
        private EntityManagerInterface $em,
        private Encryption $encryption,
        private OidcApiService $oidcApiService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // config table
        $table = new Table($output);
        $table->setHeaders(['Config', 'Value']);
        $table->setRows([
            ['App Version', $this->appConfig->getAppVersion()],
            ['Environment', $this->appConfig->getEnv()],
            ['Component', $this->internalConfig->getComponent()->value],
            ['Auth Method', $this->internalConfig->getAuthMethod()->value],
        ]);
        $table->render();

        // status table
        $statusTable = new Table($output);
        $statusTable->setHeaders(['Check', 'Status']);
        $statusTable->setRows([
            ['Database Connection', $this->dbConnection()],
            ['Encryption / App Secret', $this->appSecret()],
            ['OIDC (Well Known Config)', $this->oidcWellKnown()]
        ]);
        $statusTable->render();

        return Command::SUCCESS;
    }

    private function dbConnection(): string
    {
        try {
            $this->em->getConnection()->connect();
            return 'OK';
            // @codeCoverageIgnoreStart
        } catch (\Exception $e) {
            return 'FAILED: ' . $e->getMessage();
        }
        // @codeCoverageIgnoreEnd
    }

    private function appSecret(): string
    {
        try {
            $testString = 'test_string';
            $encrypted = $this->encryption->encrypt($testString);
            $decrypted = $this->encryption->decrypt($encrypted);
            if ($decrypted === $testString) {
                return 'OK';
                // @codeCoverageIgnoreStart
            } else {
                return 'FAILED: Decrypted value does not match original.';
            }
        } catch (\Exception $e) {
            return 'FAILED: ' . $e->getMessage();
        }
        // @codeCoverageIgnoreEnd
    }

    private function oidcWellKnown(): string
    {
        if ($this->internalConfig->getAuthMethod() !== AuthMethod::OIDC) {
            return 'SKIPPED'; // @codeCoverageIgnore
        }

        try {
            $this->oidcApiService->getWellKnownConfig();
            return 'OK'; // @codeCoverageIgnore
        } catch (\Exception $e) {
            return 'FAILED: ' . $e->getMessage();
        }
    }

}