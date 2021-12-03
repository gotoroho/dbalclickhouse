<?php

declare(strict_types = 1);

namespace DBALClickHouse;

use Closure;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use RuntimeException;
use function sprintf;
use function strtoupper;
use function substr;
use function trim;

class Connection extends \Doctrine\DBAL\Connection
{
    public function executeUpdate(string $sql, array $params = [], array $types = []): int
    {
        // ClickHouse has no UPDATE or DELETE statements
        $command = strtoupper(substr(trim($sql), 0, 6));
        if ('UPDATE' === $command || 'DELETE' === $command) {
            throw new ClickHouseException('UPDATE and DELETE are not allowed in ClickHouse');
        }

        return parent::executeUpdate($sql, $params, $types);
    }

    public function delete($table, array $criteria, array $types = []): void
    {
        throw InvalidArgumentException::fromEmptyCriteria();
    }

    public function update($tableExpression, array $data, array $identifier, array $types = []): void
    {
        $this->throwError(__METHOD__);
    }

    public function setTransactionIsolation($level): void
    {
        $this->throwError(__METHOD__);
    }

    public function getTransactionIsolation(): void
    {
        $this->throwError(__METHOD__);
    }

    public function getTransactionNestingLevel(): void
    {
        $this->throwError(__METHOD__);
    }

    public function transactional(Closure $func): void
    {
        $this->throwError(__METHOD__);
    }

    public function setNestTransactionsWithSavepoints($nestTransactionsWithSavepoints): void
    {
        $this->throwError(__METHOD__);
    }

    public function getNestTransactionsWithSavepoints(): void
    {
        $this->throwError(__METHOD__);
    }

    public function beginTransaction(): void
    {
        $this->throwError(__METHOD__);
    }

    public function commit(): void
    {
        $this->throwError(__METHOD__);
    }

    public function rollBack(): void
    {
        $this->throwError(__METHOD__);
    }

    public function createSavepoint($savepoint): void
    {
        $this->throwError(__METHOD__);
    }

    public function releaseSavepoint($savepoint): void
    {
        $this->throwError(__METHOD__);
    }

    public function rollbackSavepoint($savepoint): void
    {
        $this->throwError(__METHOD__);
    }

    public function setRollbackOnly(): void
    {
        $this->throwError(__METHOD__);
    }

    public function isRollbackOnly(): void
    {
        $this->throwError(__METHOD__);
    }

    public function throwError(string $method): void
    {
        throw new RuntimeException(sprintf('Operation %s is not supported by platform.', $method));
    }
}
