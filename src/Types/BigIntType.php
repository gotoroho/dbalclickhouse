<?php

declare(strict_types = 1);

namespace DBALClickHouse\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class BigIntType extends \Doctrine\DBAL\Types\BigIntType
{
    public function getBindingType(): int
    {
        return ParameterType::INTEGER;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return (int) $value;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return (empty($fieldDeclaration['unsigned']) ? '' : 'U') . 'Int64';
    }
}
