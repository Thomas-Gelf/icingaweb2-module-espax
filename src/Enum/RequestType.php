<?php

namespace gipfl\Protocol\EspaX\Enum;

final class RequestType
{
    public const LOGIN              = 'LOGIN';
    public const LOGOUT             = 'LOGOUT';
    public const HEARTBEAT          = 'HEARTBEAT';
    public const SYSTEM_CONDITION   = 'S-CONDITION';
    public const SYSTEM_PARAMETERS  = 'S-PARAMETERS';
    public const PROCESS_START      = 'P-START';
    public const PROCESS_STOP       = 'P-STOP';
    public const PROCESS_GET_STATUS = 'P-GETSTAT';
}
