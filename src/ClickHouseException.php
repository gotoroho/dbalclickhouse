<?php

declare(strict_types = 1);

namespace DBALClickHouse;

use Exception;
use function sprintf;

class ClickHouseException extends Exception
{
    public static function notSupported(string $method): self
    {
        return new self(sprintf("Operation '%s' is not supported by platform.", $method));
    }
}
