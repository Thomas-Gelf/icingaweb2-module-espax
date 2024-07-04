<?php

namespace gipfl\Protocol\EspaX\Enum;

final class RootTypePrefix
{
    public const REQUEST = 'REQ.';
    public const RESPONSE = 'RSP.';
    public const INDICATION = 'IND.';
    public const COMMAND = 'CMD.';
    public const PROPRIETARY = 'PROPRIETARY';
}
