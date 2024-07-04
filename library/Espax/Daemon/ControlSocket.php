<?php

namespace Icinga\Module\Espax\Daemon;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\Loop;
use React\Socket\UnixServer;
use React\Stream\Util;
use function file_exists;
use function umask;
use function unlink;

class ControlSocket implements EventEmitterInterface
{
    use EventEmitterTrait;

    protected $server = null;

    /** @var string */
    protected $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->removeOrphanedSocketFile();
    }

    public function run(): void
    {
        $this->listen();
    }

    protected function listen(): void
    {
        $old = umask(0000);
        $server = new UnixServer('unix://' . $this->path, Loop::get());
        umask($old);
        Util::forwardEvents($server, $this, ['connection' ,'error']);
        $this->server = $server;
    }

    public function shutdown(): void
    {
        if ($this->server) {
            $this->server->close();
            $this->server = null;
        }

        $this->removeOrphanedSocketFile();
    }

    protected function removeOrphanedSocketFile(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
