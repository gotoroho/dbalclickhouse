<?php

declare(strict_types = 1);

namespace DBALClickHouse;

use ClickHouseDB\Client;
use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_replace;
use function array_walk;
use function current;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function preg_replace;
use function stripos;
use function trim;

class ClickHouseStatement implements Statement
{
    private Client $smi2CHClient;

    private AbstractPlatform $platform;

    private array $values = [];

    private array $types = [];

    private string $sql;

    public function __construct(Client $client, string $sql, AbstractPlatform $platform)
    {
        $this->smi2CHClient = $client;
        $this->sql          = $sql;
        $this->platform     = $platform;
    }

    public function bindValue($param, $value, $type = null): bool
    {
        $this->values[$param] = $value;
        $this->types[$param]  = $type;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null): bool
    {
        $this->values[$column] = &$variable;
        $this->types[$column]  = $type;

        return true;
    }

    public function execute($params = null): Result
    {
        $hasZeroIndex = false;
        if (is_array($params)) {
            $this->values = array_replace($this->values, $params); //TODO array keys must be all strings or all integers?
            $hasZeroIndex = array_key_exists(0, $params);
        }

        $sql = $this->sql;

        if ($hasZeroIndex) {
            $statementParts = explode('?', $sql);
            array_walk($statementParts, function (&$part, $key): void {
                if (!array_key_exists($key, $this->values)) {
                    return;
                }

                $part .= $this->getTypedParam($key);
            });
            $sql = implode('', $statementParts);
        } else {
            foreach (array_keys($this->values) as $key) {
                $sql = preg_replace(
                    '/(' . (is_int($key) ? '\?' : ':' . $key) . ')/i',
                    $this->getTypedParam($key),
                    $sql,
                    1
                );
            }
        }

        return new ArrayResult($this->processViaSMI2($sql));
    }

    /**
     * Specific SMI2 ClickHouse lib statement execution
     * If you want to use any other lib for working with CH -- just update this method.
     */
    protected function processViaSMI2(string $sql): array
    {
        $sql = trim($sql);

        return
            0 === stripos($sql, 'select') ||
            0 === stripos($sql, 'show') ||
            0 === stripos($sql, 'describe') ?
                $this->smi2CHClient->select($sql)->rows() :
                $this->smi2CHClient->write($sql)->rows();
    }

    /**
     * @param string|int $key
     *
     * @throws ClickHouseException
     */
    protected function getTypedParam($key): string
    {
        if (null === $this->values[$key]) {
            return 'NULL';
        }

        $type = $this->types[$key] ?? null;

        // if param type was not setted - trying to get db-type by php-var-type
        if (null === $type) {
            if (is_bool($this->values[$key])) {
                $type = ParameterType::BOOLEAN;
            } elseif (is_int($this->values[$key]) || is_float($this->values[$key])) {
                $type = ParameterType::INTEGER;
            } elseif (is_array($this->values[$key])) {
                /*
                 * ClickHouse Arrays
                 */
                $values = $this->values[$key];
                if (is_int(current($values)) || is_float(current($values))) {
                    array_map(
                        function ($value): void {
                            if (!is_int($value) && !is_float($value)) {
                                throw new ClickHouseException('Array values must all be int/float or string, mixes not allowed');
                            }
                        },
                        $values
                    );
                } else {
                    $values = array_map(function ($value) {
                        return null === $value ? 'NULL' : $this->platform->quoteStringLiteral($value);
                    }, $values);
                }

                return '[' . implode(', ', $values) . ']';
            }
        }

        if (ParameterType::INTEGER === $type) {
            return (string) $this->values[$key];
        }

        if (ParameterType::BOOLEAN === $type) {
            return (string) (int) (bool) $this->values[$key];
        }

        return $this->platform->quoteStringLiteral((string) $this->values[$key]);
    }
}
