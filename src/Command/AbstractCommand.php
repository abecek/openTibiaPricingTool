<?php
declare(strict_types=1);

namespace App\Command;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractCommand extends Command
{
    /**
     * @param InputInterface $input
     * @param string $loggerName
     * @return Logger
     */
    protected function getLogger(InputInterface $input, string $loggerName): Logger
    {
        $logger = new Logger($loggerName);
        $logger->pushHandler(new StreamHandler('logs/error.log', Level::Warning));
        if ($input->getOption('debug')) {
            // Log to debug file
            $logger->pushHandler(new StreamHandler('logs/debug.log', Level::Debug));

            // Log to STDOUT with color support
            $consoleStream = fopen('php://stdout', 'w');
            $consoleHandler = new StreamHandler($consoleStream, Level::Debug);

            // Add colorized formatter
            $consoleHandler->setFormatter(new class implements FormatterInterface {
                public function format(LogRecord $record): string
                {
                    $level = $record->level->getName();
                    $time = $record->datetime->format('H:i:s');

                    $levelColor = match ($level) {
                        'DEBUG'     => "\033[0;37m",      // jasnoszary
                        'INFO'      => "\033[0;34m",      // niebieski
                        'WARNING'   => "\033[1;33m",      // żółty
                        'ERROR'     => "\033[0;31m",      // czerwony
                        'CRITICAL'  => "\033[1;37;41m",   // biały na czerwonym tle
                        default     => "\033[0m",
                    };

                    return sprintf(
                        "\033[0;90m[%s]\033[0m %s%s\033[0m: %s\n",
                        $time,
                        $levelColor,
                        $level,
                        $record->message
                    );
                }

                public function formatBatch(array $records): string
                {
                    return implode('', array_map([$this, 'format'], $records));
                }
            });

            $logger->pushHandler($consoleHandler);
        }

        return $logger;
    }
}