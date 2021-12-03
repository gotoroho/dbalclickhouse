<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

interface StringClickHouseType extends ClickHouseType
{
    public const TYPE_STRING       = 'String';
    public const TYPE_FIXED_STRING = 'FixedString';
}
