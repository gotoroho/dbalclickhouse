<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

interface ClickHouseType
{
    public function getBaseClickHouseType(): string;
}
