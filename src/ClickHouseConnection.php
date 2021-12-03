<?php

declare(strict_types = 1);

namespace DBALClickHouse;

use ClickHouseDB\Client as Smi2CHClient;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use LogicException;
use function array_merge;

class ClickHouseConnection implements Connection
{
    protected Smi2CHClient $smi2CHClient;

    protected AbstractPlatform $platform;

    public function __construct(array $params, string $username, string $password, AbstractPlatform $platform)
    {
        $this->smi2CHClient = new Smi2CHClient([
            'host'     => $params['host'] ?? 'localhost',
            'port'     => $params['port'] ?? 8123,
            'username' => $username,
            'password' => $password,
        ], array_merge([
            'database' => $params['dbname'] ?? 'default',
        ], $params['driverOptions'] ?? []));

        $this->platform = $platform;
    }

    public function prepare(string $sql): Statement
    {
        return new ClickHouseStatement($this->smi2CHClient, $sql, $this->platform);
    }

    public function query(string $sql): Result
    {
        $stmt = $this->prepare($sql);

        return $stmt->execute();
    }

    public function quote($value, $type = ParameterType::STRING)
    {
        if (ParameterType::INTEGER === $type) {
            return $value;
        }

        return $this->platform->quoteStringLiteral($value);
    }

    public function exec(string $sql): int
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt->execute()->rowCount();
    }

    public function lastInsertId($name = null): void
    {
        throw new LogicException('Unable to get last insert id in ClickHouse');
    }

    public function beginTransaction(): void
    {
        throw new LogicException('Transactions are not allowed in ClickHouse');
    }

    public function commit(): void
    {
        throw new LogicException('Transactions are not allowed in ClickHouse');
    }

    public function rollBack(): void
    {
        throw new LogicException('Transactions are not allowed in ClickHouse');
    }
}
