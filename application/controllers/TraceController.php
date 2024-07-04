<?php

namespace Icinga\Module\Espax\Controllers;

use Icinga\Module\Espax\Web\Table\PacketTraceTable;

/**
 * @api
 */
class TraceController extends Controller
{
    use LazyDb;

    /**
     * @api
     */
    public function indexAction()
    {
        $this->assertPermission('espax/showTrace');
        $this->historyTabs()->activate('trace');
        $this->addTitle($this->translate('Full Packet Trace'));
        $db = $this->db();
        $table = new PacketTraceTable($db);
        $this->setAutorefreshInterval(10);
        $table->renderTo($this);
    }

    /**
     * @api
     */
    public function unreferencedAction()
    {
        $this->historyTabs()->activate('unreferenced');
        $this->assertPermission('espax/showTrace');
        $this->addTitle($this->translate('Unreferenced Packets'));
        $db = $this->db();
        $table = new PacketTraceTable($db);
        $table->getQuery()->where('problem_reference IS NULL');
        $this->setAutorefreshInterval(10);
        $table->renderTo($this);
    }
}
