<?php

namespace Icinga\Module\Espax\Web\Table;

use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\ZfDb\Select;
use Icinga\Module\Espax\Web\DbDataHelper;
use ipl\Html\Html;

class NotificationsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'n.message',
        'n.problem_reference_details',
        'n.accepted_by',
    ];

    public function renderRow($row)
    {
        $time = (int) floor($row->ts / 1000);
        $this->renderDayIfNew($time);
        $formatter = new LocalTimeFormat();
        $overDue = (time() - $time) > 3600;
        $state = 'received';
        $stateIcon = 'spinner';
        $stateColor = 'pending';
        $stateMsg = sprintf(
            $this->translate('Notification for %s received, scheduled for delivery'),
            $row->destination
        );
        $trace = [$stateMsg];
        if ($row->ts_sent !== null) {
            $stateMsg = sprintf(
                $this->translate('Notification for %s received, has been forwarded (unconfirmed)'),
                $row->destination
            );
            $trace[] = $stateMsg; // TODO: add time?
            $stateIcon = 'forward';
            $stateColor = $overDue ? 'warning' : 'ok';
            $state = 'sent';
        }
        if ($row->ts_confirmed !== null) {
            $stateMsg = sprintf(
                $this->translate('Notification for %s received, has been forwarded and confirmed'),
                $row->destination
            );
            $trace[] = $stateMsg; // TODO: add time?
            $stateIcon = 'clock';
            $stateColor = $overDue ? 'warning' : 'ok';
            $state = 'delivered';
        }
        if ($row->ts_accepted !== null) {
            $stateMsg = sprintf(
                $this->translate('Notification received, has been accepted by %s'),
                $row->accepted_by
            );
            $trace[] = $stateMsg; // TODO: add time?
            $stateIcon = 'ok';
            $stateColor = 'ok';
            $state = 'accepted';
        }
        if ($row->ts_failed !== null) {
            $stateMsg = $this->translate('Notification submission failed: ' . $row->error_message);
            $trace[] = $stateMsg; // TODO: add time?
            $stateIcon = 'cancel';
            $stateColor = 'critical';
            $state = 'failed';
        }

        return static::row([
            [
                Link::create(
                    Html::tag('strong', DbDataHelper::describeProblemReference($row)),
                    'espax/notification',
                    ['problem_reference' => $row->problem_reference]
                ),
                ': ',
                $row->message,
                Html::tag('br'),
                Icon::create($stateIcon, [
                    'class' => "state-$stateColor",
                ]),
                Html::tag('i', $stateMsg)
            ],
            $formatter->getShortTime($time),
        ]);
    }

    public function prepareQuery(): Select
    {
        return $this->db()->select()
            ->from(['n' => 'espax_notification'], [
                'ts'           => 'n.ts',
                'ts_sent'      => 'n.ts_sent',
                'ts_confirmed' => 'n.ts_confirmed',
                'ts_accepted'  => 'n.ts_accepted',
                'ts_failed'    => 'n.ts_failed',
                'node_uuid'    => 'n.node_uuid',
                'destination'  => 'n.destination',
                'message'      => 'n.message',
                'problem_tan'  => 'n.problem_tan',
                'problem_reference'                => 'n.problem_reference',
                'problem_reference_implementation' => 'n.problem_reference_implementation',
                'accepted_by'                      => 'n.accepted_by',
                'problem_reference_details'        => 'n.problem_reference_details',
                // state?
                'error_message' => 'n.error_message',
            ])
            ->limit(20)
            ->order('n.ts DESC');
    }
}
