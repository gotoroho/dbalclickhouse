<?php

declare(strict_types = 1);

namespace DBALClickHouse;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class Driver implements \Doctrine\DBAL\Driver
{
    public function connect(array $params, $user = null, $password = null, array $driverOptions = []): ClickHouseConnection
    {
        if (null === $user) {
            if (!isset($params['user'])) {
                throw new ClickHouseException('Connection parameter `user` is required');
            }

            $user = $params['user'];
        }

        if (null === $password) {
            if (!isset($params['password'])) {
                throw new ClickHouseException('Connection parameter `password` is required');
            }

            $password = $params['password'];
        }

        if (!isset($params['host'])) {
            throw new ClickHouseException('Connection parameter `host` is required');
        }

        if (!isset($params['port'])) {
            throw new ClickHouseException('Connection parameter `port` is required');
        }

        if (!isset($params['dbname'])) {
            throw new ClickHouseException('Connection parameter `dbname` is required');
        }

        return new ClickHouseConnection($params, (string) $user, (string) $password, $this->getDatabasePlatform());
    }

    public function getDatabasePlatform(): ClickHousePlatform
    {
        return new ClickHousePlatform();
    }

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): ClickHouseSchemaManager
    {
        return new ClickHouseSchemaManager($conn, $platform);
    }

    public function getName(): string
    {
        return 'clickhouse';
    }

    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();
        if (isset($params['dbname'])) {
            return $params['dbname'];
        }

        return $conn->fetchOne('SELECT currentDatabase() as dbname');
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new \Doctrine\DBAL\Driver\API\IBMDB2\ExceptionConverter();
    }
}
