<?php

namespace App\Service\Log;

class LogService
{
    private const LOG_FILE_PATH = '/var/log/app.log';
    private const MAX_LOG_LINES = 1000;

    /**
     * @return string[]
     */
    public function readLogs(int $numLines = self::MAX_LOG_LINES): array
    {
        $file = new \SplFileObject(self::LOG_FILE_PATH, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $lines = new \LimitIterator($file, $lastLine - 5, $lastLine);

        /** @var string[] $linesArr */
        $linesArr = iterator_to_array($lines, false);

        return array_reverse($linesArr);
    }

}