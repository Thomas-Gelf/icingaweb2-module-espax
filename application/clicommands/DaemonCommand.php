<?php

namespace Icinga\Module\Espax\Clicommands;

use gipfl\SimpleDaemon\Daemon;
use Icinga\Module\Espax\Daemon\BackgroundDaemon;
use React\EventLoop\Loop;

/**
 * Run the ESPA-X Notifications Daemon for Icinga
 *
 * USAGE
 *
 * icingacli espax daemon run [--verbose|--debug] [--trace]
 *
 * OPTIONS
 *   --verbose  Raise log level
 *   --debug    Raise to debug log level
 *   --trace    Show stack traces, if something goes wrong
 */
class DaemonCommand extends Command
{
    /**
     * @api
     */
    public function runAction()
    {
        $this->assertRequiredExtensionsAreLoaded();
        $daemon = new Daemon();
        $daemon->setLogger($this->logger);
        $daemon->attachTask(new BackgroundDaemon());
        $daemon->run(Loop::get());
        Loop::run();
    }
}
