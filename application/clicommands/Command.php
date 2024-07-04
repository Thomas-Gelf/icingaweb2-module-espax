<?php

namespace Icinga\Module\Espax\Clicommands;

use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\IcingaWeb\IcingaLogger;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JournaldLogger;
use gipfl\Log\Writer\SystemdStdoutWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\SystemD\systemd;
use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Espax\Daemon\BackgroundDaemon;
use Icinga\Module\Espax\Daemon\Application;
use React\EventLoop\Loop;
use React\Stream\WritableResourceStream;

/**
 * @api
 */
abstract class Command extends CliCommand
{
    /** @var Logger */
    protected $logger;

    public function init()
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $this->initializeLogger();
    }

    protected function assertRequiredExtensionsAreLoaded(): void
    {
        $missing = [];
        foreach (BackgroundDaemon::REQUIRED_PHP_EXTENSIONS as $extension) {
            if (! extension_loaded($extension)) {
                $missing[] = "php-$extension";
            }
        }

        if (! empty($missing)) {
            $this->fail('Cannot run because of missing dependencies: ' . implode(', ', $missing));
        }
    }

    protected function initializeLogger(): void
    {
        $this->logger = $logger = new Logger();
        $loop = Loop::get();
        $this->applyLogFilter($this->logger);
        IcingaLogger::replace($logger);
        if (systemd::startedThisProcess()) {
            if (@file_exists(JournaldLogger::JOURNALD_SOCKET)) {
                $logger->addWriter((new JournaldLogger())->setIdentifier(Application::LOG_NAME));
            } else {
                $logger->addWriter(new SystemdStdoutWriter($loop));
            }
        } else {
            $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR, $loop)));
        }
    }

    protected function applyLogFilter(Logger $logger): void
    {
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        if ($this->isDebugging) {
            // Hint: no need to filter
            // $this->logger->addFilter(new LogLevelFilter('debug'));
        } elseif ($this->isVerbose) {
            $logger->addFilter(new LogLevelFilter('info'));
        } else {
            $logger->addFilter(new LogLevelFilter('notice'));
        }
    }
}
