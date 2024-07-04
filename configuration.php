<?php declare(strict_types=1);

use Icinga\Application\Modules\Module;

/** @var Module $this */
$this->menuSection(N_('History'))->add('espax', [
    'label'    => $this->translate('Notifications (ESPA-X)'),
    'url'      => 'espax/notifications',
    'priority' => 33,
]);
$this->providePermission('espax/showTrace', $this->translate('Show Packet Traces (might contain sensitive data)'));
$this->providePermission('espax/deleteNotification', $this->translate('Allow to delete sent notifications'));
