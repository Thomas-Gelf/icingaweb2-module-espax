<?php

namespace Icinga\Module\Espax\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Module\Espax\Web\Table\NotificationsTable;

/**
 * @api
 */
class NotificationsController extends Controller
{
    use LazyDb;

    /**
     * @api
     */
    public function indexAction()
    {
        $this->addTitle($this->translate('Notifications (ESPA-X)'));
        $this->historyTabs()->activate('notifications');
        $this->setAutorefreshInterval(10);
        $table = new NotificationsTable($this->db());
        if ($table->count() === 0) {
            $this->content()->add(Hint::info($this->translate('There is no pending or recently sent notification')));
        } else {
            $table->renderTo($this);
        }
    }
}
