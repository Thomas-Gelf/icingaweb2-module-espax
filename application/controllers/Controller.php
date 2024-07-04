<?php

namespace Icinga\Module\Espax\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Espax\Daemon\BackgroundDaemon;
use Icinga\Module\Espax\Rpc\RemoteClient;

abstract class Controller extends CompatController
{
    /** @var RemoteClient */
    protected $remoteClient;

    protected function remoteClient(): RemoteClient
    {
        if ($this->remoteClient === null) {
            $this->remoteClient = new RemoteClient(BackgroundDaemon::SOCKET);
        }

        return $this->remoteClient;
    }

    protected function historyTabs()
    {
        $tabs = $this->tabs()->add('notifications', [
            'url' => 'espax/notifications',
            'label' => $this->translate('Notifications'),
        ]);
        if ($this->hasPermission('espax/showTrace')) {
            $tabs->add('trace', [
                'url'   => 'espax/trace',
                'label' => $this->translate('Trace'),
            ])->add('unreferenced', [
                'url'   => 'espax/trace/unreferenced',
                'label' => $this->translate('Unreferenced'),
            ]);
        }

        return $tabs;
    }
}
