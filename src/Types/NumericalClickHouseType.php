<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

interface NumericalClickHouseType extends ClickHouseType
{
    public const TYPE_INT   = 'Int';
    public const TYPE_FLOAT = 'Float';
}
