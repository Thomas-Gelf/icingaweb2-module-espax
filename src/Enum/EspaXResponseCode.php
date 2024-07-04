<?php

namespace gipfl\Protocol\EspaX\Enum;

/**
 * TODO: should become an Enum, once we drop support for PHP <= 8.1
 */
final class EspaXResponseCode
{
    const CODES = [
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        450 => 'Duplicate',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        600 => 'Invalid header',
        601 => 'Not wellformed',
        602 => 'Invalid message',
    ];

    /** @var int */
    protected $code;

    public function __construct(int $code)
    {
        $this->code = $code;
    }

    public function isSuccess(): bool
    {
        return $this->code === 200;
    }

    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    public function isServerError(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }

    public function isProtocolError(): bool
    {
        return $this->code >= 600;
    }
}
