<?php

namespace gipfl\Protocol\EspaX\Enum;

// TODO: Enum with PHP 8.1
final class ProcessMask
{
    public const PREPARED    = 'Prepared';
    public const QUEUED    = 'Queued';
    public const ACTIVE    = 'Active';
    public const CONVERSATION    = 'Conversation';
    public const POST_PROCESSING    = 'Postprocessing';
    public const ALL    = 'All';
}
