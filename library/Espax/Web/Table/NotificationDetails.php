<?php

namespace Icinga\Module\Espax\Web\Table;

use DateTimeImmutable;
use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Module\Espax\Web\DbDataHelper;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use Ramsey\Uuid\Uuid;

class NotificationDetails extends NameValueTable
{
    use TranslationHelper;

    protected const NO_VALUE = '-';

    /** @var LocalDateFormat */
    protected $dateFormatter;
    /** @var LocalTimeFormat */
    protected $timeFormatter;

    public function __construct($notification)
    {
        $this->dateFormatter = new LocalDateFormat();
        $this->timeFormatter = new LocalTimeFormat();
        $this->addNameValuePairs([
            $this->translate('Created') => $this->showDateTime($notification->ts),
            $this->translate('Sent to Gateway') => $this->showDateTime($notification->ts_sent),
            $this->translate('Confirmed by Gateway') => $this->showDateTime($notification->ts_confirmed),
            $this->translate('Accepted') => $this->showDateTime($notification->ts_accepted),
            $this->translate('Accepted by') => $notification->accepted_by ?: self::NO_VALUE,
            $this->translate('Failed') => $this->showDateTime($notification->ts_failed),
            $this->translate('Error Message') => $notification->error_message ?: self::NO_VALUE,
            $this->translate('Destination') => $notification->destination,
            $this->translate('Message') => $notification->message,
            $this->translate('Problem reference') => DbDataHelper::describeProblemReference($notification),
            $this->translate('Node UUID') => Uuid::fromBytes($notification->node_uuid)->toString(),
            $this->translate('ESPA-X Server TAN') => $notification->problem_tan ?: self::NO_VALUE,
            $this->translate('ESPA-X Problem Reference (Client)') => $notification->problem_reference,
        ]);
    }

    /**
     * @return HtmlElement|string
     */
    protected function showDateTime(?int $ts)
    {
        if ($ts === null) {
            return self::NO_VALUE;
        }
        $datetime = DateTimeImmutable::createFromFormat('U.u', $ts / 1000);
        $ms = $ts % 1000;
        $unixTs = (int) floor($ts / 1000);
        return Html::tag('time', [
            'datetime' => $datetime->format('c')
        ], sprintf(
            '%s (%s)',
            self::addMs($this->timeFormatter->getTime($unixTs), $ms),
            $this->dateFormatter->getFullDay($unixTs)
        ));
    }

    protected static function addMs(string $time, int $ms): string
    {
        if (preg_match('/^(.+?)(\s[AP]M)$/', $time, $m)) {
            return sprintf('%s.%03d%s', $m[1], $ms, $m[2]);
        }

        return sprintf('%s.%03d', $time, $ms);
    }
}
