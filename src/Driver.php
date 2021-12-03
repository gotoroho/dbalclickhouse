<?php

declare(strict_types = 1);

namespace DBALClickHouse;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class Driver implements \Doctrine\DBAL\Driver
{
    public function connect(array $params)
    {
        if (!isset($params['user'])) {
            throw new ClickHouseException('Connection parameter `user` is required');
        }

        $user = $params['user'];

        if (!isset($params['password'])) {
            throw new ClickHouseException('Connection parameter `password` is required');
        }

        $password = $params['password'];

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

    public function getDatabasePlatform()
    {
        return new ClickHousePlatform();
    }

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
    {
        return new ClickHouseSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new \Doctrine\DBAL\Driver\API\IBMDB2\ExceptionConverter();
    }
}
