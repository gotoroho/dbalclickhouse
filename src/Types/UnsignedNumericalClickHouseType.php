<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

interface UnsignedNumericalClickHouseType extends NumericalClickHouseType
{
    public const UNSIGNED_CHAR = 'U';
}
