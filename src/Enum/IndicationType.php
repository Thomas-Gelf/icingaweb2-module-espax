<?php

namespace gipfl\Protocol\EspaX\Enum;

final class IndicationType
{
    public const ERROR           = 'ERROR';
    public const STATUS_SYSTEM   = 'S-SYSTEM';
    public const PROCESS_STARTED = 'P-STARTED';
    public const PROCESS_ENDED   = 'P-ENDED';
    public const PROCESS_SYNC    = 'P-SYNC';
    public const PROCESS_STATUS  = 'P-STATUS';
    public const PROCESS_EVENT   = 'P-EVENT';
}
