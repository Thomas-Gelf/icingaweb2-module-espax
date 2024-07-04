<?php

namespace gipfl\Protocol\EspaX\Enum;

// TODO: Enum with PHP 8.1
final class EventProcessStatus
{
    public const PREPARED = 'Prepared';
    public const QUEUED   = 'Queued';
    public const ACTIVE   = 'Active';
    public const CONVERSATION_SETUP = 'Conversation setup';
    public const CONVERSATION       = 'Conversation';
    public const POSTPROCESSING     = 'Postprocessing';
}
