<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

use DateTime;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use function array_filter;
use function array_map;
use function implode;

class ArrayDateTimeType extends ArrayType implements DatableClickHouseType
{
    public function getBaseClickHouseType(): string
    {
        return DatableClickHouseType::TYPE_DATE_TIME;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return array_map(
            function ($stringDatetime) use ($platform) {
                return DateTime::createFromFormat($platform->getDateTimeFormatString(), $stringDatetime);
            },
            (array) $value
        );
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return '[' . implode(
            ', ',
            array_map(
                function (DateTime $datetime) use ($platform) {
                    return "'" . $datetime->format($platform->getDateTimeFormatString()) . "'";
                },
                array_filter(
                    (array) $value,
                    function ($datetime) {
                        return $datetime instanceof DateTime;
                    }
                )
            )
        ) . ']';
    }

    public function getBindingType(): int
    {
        return ParameterType::INTEGER;
    }
}
