<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

class ArrayUInt8Type extends ArrayType implements BitNumericalClickHouseType, UnsignedNumericalClickHouseType
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
