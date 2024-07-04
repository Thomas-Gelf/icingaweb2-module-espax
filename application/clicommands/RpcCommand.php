<?php

namespace Icinga\Module\Espax\Clicommands;

use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Espax\Daemon\BackgroundDaemon;
use Icinga\Module\Espax\Rpc\RemoteClient;

use function Clue\React\Block\await;

abstract class RpcCommand extends CliCommand
{
    protected static $rpcClient;

    protected static function rpc($method, $params = [])
    {
        if (self::$rpcClient === null) {
            self::$rpcClient = new RemoteClient(BackgroundDaemon::SOCKET);
        }

        return await(self::$rpcClient->request($method, $params), null, 5);
    }
}
