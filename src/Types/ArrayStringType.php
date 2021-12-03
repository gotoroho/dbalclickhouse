<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use function array_map;
use function implode;

class ArrayStringType extends ArrayType implements StringClickHouseType
{
    public function getBaseClickHouseType(): string
    {
        return StringClickHouseType::TYPE_STRING;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return '[' . implode(
            ', ',
            array_map(
                function (string $value) use ($platform) {
                    return $platform->quoteStringLiteral($value);
                },
                (array) $value
            )
        ) . ']';
    }

    public function getBindingType(): int
    {
        return ParameterType::INTEGER;
    }
}
