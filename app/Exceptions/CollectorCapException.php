<?php

namespace App\Exceptions;

/**
 * Premium refused by the collector limit. `code` is sent to the TPE:
 * COLLECTOR_BLOCKED (limit reached, deposit required) or COLLECTOR_CAP_EXCEEDED (this amount would pass it).
 */
class CollectorCapException extends \RuntimeException
{
    public const BLOCKED = 'COLLECTOR_BLOCKED';
    public const CAP_EXCEEDED = 'COLLECTOR_CAP_EXCEEDED';

    public function __construct(public string $apiCode, public array $status, string $message)
    {
        parent::__construct($message);
    }
}
