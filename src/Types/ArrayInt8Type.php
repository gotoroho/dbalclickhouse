<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

class ArrayInt8Type extends ArrayType implements BitNumericalClickHouseType
{
    public function getBits(): int
    {
        return BitNumericalClickHouseType::EIGHT_BIT;
    }

    public function getBaseClickHouseType(): string
    {
        return NumericalClickHouseType::TYPE_INT;
    }
}
