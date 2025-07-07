<?php

namespace App\Service\Log;

use App\Entity\AppLog;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class DatabaseAppLogger extends AbstractProcessingHandler
{

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function write(LogRecord $record): void
    {
        $appLogEntry = new AppLog();
        $appLogEntry->setLevel($record->level->toPsrLogLevel());
        $appLogEntry->setMessage($record->message);
        $appLogEntry->setContext($record->context);
        $appLogEntry->setCreatedAt($record->datetime);
        $appLogEntry->setUpdatedAt($record->datetime);

        $this->em->persist($appLogEntry);
        $this->em->flush();

    }
}