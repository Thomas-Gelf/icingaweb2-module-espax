<?php

namespace gipfl\Protocol\EspaX\Enum;

final class IndicationProperty
{
    // Incomplete, this just helps to link the most used ones
    public const SS_STATUS = 'SS-STATUS'; // IND.P-EVENT
    public const SP_STATUS = 'SP-STATUS'; // IND.P-STARTED, IND.P-STATUS
    public const SS_RESULT = 'SS-RESULT'; // IND.P-EVENT
    public const SS_NETWORK_NAME = 'SS-NETW-NAME'; // IND.P-EVENT
}
