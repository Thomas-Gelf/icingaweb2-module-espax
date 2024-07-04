<?php

namespace Icinga\Module\Espax\Web\Table;

use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\ZfDb\Select;
use ipl\Html\Html;

class PacketTraceTable extends ZfQueryBasedTable
{
    public function renderRow($row)
    {
        $time = (int) floor($row->ts / 1000);
        $this->renderDayIfNew($time);
        $formatter = new LocalTimeFormat();

        return static::row([
            [
                Html::tag('pre', ['style' => 'background: none'], $row->packet_trace),
            ],
            $this::td($formatter->getShortTime($time), [
                'style' => 'vertical-align: top;',
            ]),
        ]);
    }

    public function prepareQuery(): Select
    {
        return $this->db()->select()->from(['pt' => 'espax_packet_trace'], [
            'ts'                => 'pt.ts',
            'direction'         => 'pt.direction',
            'node_uuid'         => 'pt.node_uuid',
            'session_id'        => 'pt.session_id',
            'server_tan'        => 'pt.server_tan',
            'problem_reference' => 'pt.problem_reference',
            'root_element'      => 'pt.root_element',
            'packet_trace'      => 'pt.packet_trace',
        ])
        ->limit(20)
        ->order('pt.ts DESC');
    }
}
