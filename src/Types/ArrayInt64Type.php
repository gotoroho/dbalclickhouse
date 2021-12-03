<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

class ArrayInt64Type extends ArrayType implements BitNumericalClickHouseType
{
    public function getBits(): int
    {
        return BitNumericalClickHouseType::SIXTY_FOUR_BIT;
    }

    public function getBaseClickHouseType(): string
    {
        return NumericalClickHouseType::TYPE_INT;
    }
}
