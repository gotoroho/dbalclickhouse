<?php

declare(strict_types = 1);

namespace DBALClickHouse;

use DBALClickHouse\Types\BitNumericalClickHouseType;
use DBALClickHouse\Types\DatableClickHouseType;
use DBALClickHouse\Types\NumericalClickHouseType;
use DBALClickHouse\Types\StringClickHouseType;
use DBALClickHouse\Types\UnsignedNumericalClickHouseType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\TrimMode;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Exception;
use InvalidArgumentException;
use function addslashes;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function func_get_args;
use function get_class;
use function implode;
use function in_array;
use function sprintf;
use function stripos;
use function trim;

class ClickHousePlatform extends AbstractPlatform
{
    protected const TIME_MINUTE = 60;
    protected const TIME_HOUR   = self::TIME_MINUTE * 60;
    protected const TIME_DAY    = self::TIME_HOUR * 24;
    protected const TIME_WEEK   = self::TIME_DAY * 7;

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $columnDef): string
    {
        return $this->prepareDeclarationSQL(
            UnsignedNumericalClickHouseType::UNSIGNED_CHAR .
            NumericalClickHouseType::TYPE_INT . BitNumericalClickHouseType::EIGHT_BIT,
            $columnDef
        );
    }

    public function getIntegerTypeDeclarationSQL(array $columnDef): string
    {
        return $this->prepareDeclarationSQL(
            $this->_getCommonIntegerTypeDeclarationSQL($columnDef) .
            NumericalClickHouseType::TYPE_INT . BitNumericalClickHouseType::THIRTY_TWO_BIT,
            $columnDef
        );
    }

    public function getBigIntTypeDeclarationSQL(array $columnDef): string
    {
        return $this->prepareDeclarationSQL(StringClickHouseType::TYPE_STRING, $columnDef);
    }

    public function getSmallIntTypeDeclarationSQL(array $columnDef): string
    {
        return $this->prepareDeclarationSQL(
            $this->_getCommonIntegerTypeDeclarationSQL($columnDef) .
            NumericalClickHouseType::TYPE_INT . BitNumericalClickHouseType::SIXTEEN_BIT,
            $columnDef
        );
    }

    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef): string
    {
        if (!empty($columnDef['autoincrement'])) {
            throw new Exception('Clickhouse do not support AUTO_INCREMENT fields');
        }

        return empty($columnDef['unsigned']) ? '' : UnsignedNumericalClickHouseType::UNSIGNED_CHAR;
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'int8'    => 'smallint',
            'int16'   => 'integer',
            'int32'   => 'integer',
            'int64'   => 'bigint',
            'uint8'   => 'smallint',
            'uint16'  => 'integer',
            'uint32'  => 'integer',
            'uint64'  => 'bigint',
            'float32' => 'decimal',
            'float64' => 'float',

            'string'      => 'string',
            'fixedstring' => 'string',
            'date'        => 'date',
            'datetime'    => 'datetime',

            'array(int8)'    => 'array',
            'array(int16)'   => 'array',
            'array(int32)'   => 'array',
            'array(int64)'   => 'array',
            'array(uint8)'   => 'array',
            'array(uint16)'  => 'array',
            'array(uint32)'  => 'array',
            'array(uint64)'  => 'array',
            'array(float32)' => 'array',
            'array(float64)' => 'array',

            'array(string)'   => 'array',
            'array(date)'     => 'array',
            'array(datetime)' => 'array',

            'enum8'  => 'string',
            'enum16' => 'string',

            'nullable(int8)'    => 'smallint',
            'nullable(int16)'   => 'integer',
            'nullable(int32)'   => 'integer',
            'nullable(int64)'   => 'bigint',
            'nullable(uint8)'   => 'smallint',
            'nullable(uint16)'  => 'integer',
            'nullable(uint32)'  => 'integer',
            'nullable(uint64)'  => 'bigint',
            'nullable(float32)' => 'decimal',
            'nullable(float64)' => 'float',

            'nullable(string)'      => 'string',
            'nullable(fixedstring)' => 'string',
            'nullable(date)'        => 'date',
            'nullable(datetime)'    => 'datetime',

            'array(nullable(int8))'    => 'array',
            'array(nullable(int16))'   => 'array',
            'array(nullable(int32))'   => 'array',
            'array(nullable(int64))'   => 'array',
            'array(nullable(uint8))'   => 'array',
            'array(nullable(uint16))'  => 'array',
            'array(nullable(uint32))'  => 'array',
            'array(nullable(uint64))'  => 'array',
            'array(nullable(float32))' => 'array',
            'array(nullable(float64))' => 'array',

            'array(nullable(string))'   => 'array',
            'array(nullable(date))'     => 'array',
            'array(nullable(datetime))' => 'array',
        ];
    }

    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed): string
    {
        return $fixed
            ? (StringClickHouseType::TYPE_FIXED_STRING . '(' . $length . ')')
            : StringClickHouseType::TYPE_STRING;
    }

    public function getVarcharTypeDeclarationSQL(array $field)
    {
        if (!isset($field['length'])) {
            $field['length'] = $this->getVarcharDefaultLength();
        }

        $fixed = $field['fixed'] ?? false;

        $maxLength = $fixed
            ? $this->getCharMaxLength()
            : $this->getVarcharMaxLength();

        if ($field['length'] > $maxLength) {
            return $this->getClobTypeDeclarationSQL($field);
        }

        return $this->prepareDeclarationSQL(
            $this->getVarcharTypeDeclarationSQLSnippet($field['length'], $fixed),
            $field
        );
    }

    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed): string
    {
        return StringClickHouseType::TYPE_STRING;
    }

    public function getClobTypeDeclarationSQL(array $field): string
    {
        return $this->prepareDeclarationSQL(StringClickHouseType::TYPE_STRING, $field);
    }

    public function getBlobTypeDeclarationSQL(array $field): string
    {
        return $this->prepareDeclarationSQL(StringClickHouseType::TYPE_STRING, $field);
    }

    public function getName(): string
    {
        return 'clickhouse';
    }

    public function getIdentifierQuoteCharacter(): string
    {
        return '`';
    }

    public function getVarcharDefaultLength(): int
    {
        return 512;
    }

    public function getCountExpression($column): string
    {
        return 'COUNT()';
    }

    public function getMd5Expression($column): string
    {
        return 'MD5(CAST(' . $column . ' AS String))';
    }

    public function getLengthExpression($column): string
    {
        return 'lengthUTF8(CAST(' . $column . ' AS String))';
    }

    public function getSqrtExpression($column): string
    {
        return 'sqrt(' . $column . ')';
    }

    public function getRoundExpression($column, $decimals = 0): string
    {
        return 'round(' . $column . ', ' . $decimals . ')';
    }

    public function getModExpression($expression1, $expression2): string
    {
        return 'modulo(' . $expression1 . ', ' . $expression2 . ')';
    }

    public function getTrimExpression($str, $pos = TrimMode::UNSPECIFIED, $char = false): string
    {
        if (!$char) {
            switch ($pos) {
                case TrimMode::LEADING:
                    return $this->getLtrimExpression($str);
                case TrimMode::TRAILING:
                    return $this->getRtrimExpression($str);
                default:
                    return sprintf("replaceRegexpAll(%s, '(^\\\s+|\\\s+$)', '')", $str);
            }
        }

        return sprintf("replaceRegexpAll(%s, '(^%s+|%s+$)', '')", $str, $char, $char);
    }

    public function getRtrimExpression($str): string
    {
        return sprintf("replaceRegexpAll(%s, '(\\\s+$)', '')", $str);
    }

    public function getLtrimExpression($str): string
    {
        return sprintf("replaceRegexpAll(%s, '(^\\\s+)', '')", $str);
    }

    public function getUpperExpression($str): string
    {
        return 'upperUTF8(' . $str . ')';
    }

    public function getLowerExpression($str): string
    {
        return 'lowerUTF8(' . $str . ')';
    }

    public function getLocateExpression($str, $substr, $startPos = false): string
    {
        return 'positionUTF8(' . $str . ', ' . $substr . ')';
    }

    public function getNowExpression(): string
    {
        return 'now()';
    }

    public function getSubstringExpression($value, $from, $length = null): string
    {
        if (null === $length) {
            throw new InvalidArgumentException("'length' argument must be a constant");
        }

        return 'substringUTF8(' . $value . ', ' . $from . ', ' . $length . ')';
    }

    public function getConcatExpression(): string
    {
        return 'concat(' . implode(', ', func_get_args()) . ')';
    }

    public function getIsNullExpression($expression)
    {
        return 'isNull(' . $expression . ')';
    }

    public function getIsNotNullExpression($expression)
    {
        return 'isNotNull(' . $expression . ')';
    }

    public function getAcosExpression($value): string
    {
        return 'acos(' . $value . ')';
    }

    public function getSinExpression($value): string
    {
        return 'sin(' . $value . ')';
    }

    public function getPiExpression(): string
    {
        return 'pi()';
    }

    public function getCosExpression($value): string
    {
        return 'cos(' . $value . ')';
    }

    public function getDateDiffExpression($date1, $date2): string
    {
        return 'CAST(' . $date1 . ' AS Date) - CAST(' . $date2 . ' AS Date)';
    }

    public function getDateAddSecondsExpression($date, $seconds): string
    {
        return $date . ' + ' . $seconds;
    }

    public function getDateSubSecondsExpression($date, $seconds): string
    {
        return $date . ' - ' . $seconds;
    }

    public function getDateAddMinutesExpression($date, $minutes): string
    {
        return $date . ' + ' . $minutes * self::TIME_MINUTE;
    }

    public function getDateSubMinutesExpression($date, $minutes): string
    {
        return $date . ' - ' . $minutes * self::TIME_MINUTE;
    }

    public function getDateAddHourExpression($date, $hours): string
    {
        return $date . ' + ' . $hours * self::TIME_HOUR;
    }

    public function getDateSubHourExpression($date, $hours): string
    {
        return $date . ' - ' . $hours * self::TIME_HOUR;
    }

    public function getDateAddDaysExpression($date, $days): string
    {
        return $date . ' + ' . $days * self::TIME_DAY;
    }

    public function getDateSubDaysExpression($date, $days): string
    {
        return $date . ' - ' . $days * self::TIME_DAY;
    }

    public function getDateAddWeeksExpression($date, $weeks): string
    {
        return $date . ' + ' . $weeks * self::TIME_WEEK;
    }

    public function getDateSubWeeksExpression($date, $weeks): string
    {
        return $date . ' - ' . $weeks * self::TIME_WEEK;
    }

    public function getBitAndComparisonExpression($value1, $value2): string
    {
        return 'bitAnd(' . $value1 . ', ' . $value2 . ')';
    }

    public function getBitOrComparisonExpression($value1, $value2): string
    {
        return 'bitOr(' . $value1 . ', ' . $value2 . ')';
    }

    public function getForUpdateSQL(): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function appendLockHint(string $fromClause, int $lockMode): string
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getReadLockSQL(): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getWriteLockSQL(): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getDropIndexSQL($index, $table = null): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getDropConstraintSQL($constraint, $table): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getDropForeignKeySQL($foreignKey, $table): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getCommentOnColumnSQL($tableName, $columnName, $comment): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    protected function _getCreateTableSQL($tableName, array $columns, array $options = []): array
    {
        $engine        = !empty($options['engine']) ? $options['engine'] : 'ReplacingMergeTree';
        $engineOptions = '';

        if (isset($options['uniqueConstraints']) && !empty($options['uniqueConstraints'])) {
            throw ClickHouseException::notSupported('uniqueConstraints');
        }

        if (isset($options['indexes']) && !empty($options['indexes'])) {
            throw ClickHouseException::notSupported('uniqueConstraints');
        }

        /*
         * MergeTree* specific section
         */
        if (in_array(
            $engine,
            [
                'MergeTree',
                'CollapsingMergeTree',
                'SummingMergeTree',
                'AggregatingMergeTree',
                'ReplacingMergeTree',
                'GraphiteMergeTree',
            ],
            true
        )) {
            $indexGranularity   = !empty($options['indexGranularity']) ? $options['indexGranularity'] : 8192;
            $samplingExpression = '';

            /**
             * eventDateColumn section.
             */
            $dateColumnParams = [
                'type'    => Type::getType('date'),
                'default' => 'today()',
            ];
            if (!empty($options['eventDateProviderColumn'])) {
                $options['eventDateProviderColumn'] = trim($options['eventDateProviderColumn']);
                if (!isset($columns[$options['eventDateProviderColumn']])) {
                    throw new Exception('Table `' . $tableName . '` has not column with name: `' . $options['eventDateProviderColumn']);
                }

                if (!($columns[$options['eventDateProviderColumn']]['type'] instanceof DateType) &&
                    !($columns[$options['eventDateProviderColumn']]['type'] instanceof DateTimeType) &&
                    !($columns[$options['eventDateProviderColumn']]['type'] instanceof TextType) &&
                    !($columns[$options['eventDateProviderColumn']]['type'] instanceof IntegerType) &&
                    !($columns[$options['eventDateProviderColumn']]['type'] instanceof SmallIntType) &&
                    !($columns[$options['eventDateProviderColumn']]['type'] instanceof BigIntType) &&
                    !($columns[$options['eventDateProviderColumn']]['type'] instanceof FloatType) &&
                    !($columns[$options['eventDateProviderColumn']]['type'] instanceof DecimalType) &&
                    (
                        !($columns[$options['eventDateProviderColumn']]['type'] instanceof StringType) ||
                        $columns[$options['eventDateProviderColumn']]['fixed']
                    )
                ) {
                    throw new Exception('Column `' . $options['eventDateProviderColumn'] . '` with type `' . $columns[$options['eventDateProviderColumn']]['type']->getName() . '`, defined in `eventDateProviderColumn` option, has not valid DBAL Type');
                }

                $dateColumnParams['default'] =
                    $columns[$options['eventDateProviderColumn']]['type'] instanceof IntegerType ||
                    $columns[$options['eventDateProviderColumn']]['type'] instanceof SmallIntType ||
                    $columns[$options['eventDateProviderColumn']]['type'] instanceof FloatType ?
                        ('toDate(toDateTime(' . $options['eventDateProviderColumn'] . '))') :
                        ('toDate(' . $options['eventDateProviderColumn'] . ')');
            }
            if (empty($options['eventDateColumn'])) {
                $dateColumns = array_filter($columns, function ($column) {
                    return $column['type'] instanceof DateType;
                });

                if ($dateColumns) {
                    throw new Exception('Table `' . $tableName . '` has DateType columns: `' . implode('`, `', array_keys($dateColumns)) . '`, but no one of them is setted as `eventDateColumn` with 
                        $table->addOption("eventDateColumn", "%eventDateColumnName%")');
                }

                $eventDateColumnName = 'EventDate';
            } elseif (isset($columns[$options['eventDateColumn']])) {
                if (!($columns[$options['eventDateColumn']]['type'] instanceof DateType)) {
                    throw new Exception('In table `' . $tableName . '` you have set field `' . $options['eventDateColumn'] . '` (' . get_class($columns[$options['eventDateColumn']]['type']) . ')
                         as `eventDateColumn`, but it is not instance of DateType');
                }

                $eventDateColumnName = $options['eventDateColumn'];
                unset($columns[$options['eventDateColumn']]);
            } else {
                $eventDateColumnName = $options['eventDateColumn'];
            }
            $dateColumnParams['name'] = $eventDateColumnName;
            // insert into very beginning
            $columns = [$eventDateColumnName => $dateColumnParams] + $columns;

            /*
             * Primary key section
             */
            if (empty($options['primary'])) {
                throw new Exception('You need specify PrimaryKey for MergeTree* tables');
            }

            $primaryIndex = array_values($options['primary']);
            if (!empty($options['samplingExpression'])) {
                $samplingExpression = ', ' . $options['samplingExpression'];
                $primaryIndex[]     = $options['samplingExpression'];
            }

            $engineOptions = sprintf(
                '(%s%s, (%s), %d',
                $eventDateColumnName,
                $samplingExpression,
                implode(
                    ', ',
                    array_unique($primaryIndex)
                ),
                $indexGranularity
            );

            /*
             * any specific MergeTree* table parameters
             */
            if ('ReplacingMergeTree' === $engine && !empty($options['versionColumn'])) {
                if (!isset($columns[$options['versionColumn']])) {
                    throw new Exception('If you specify `versionColumn` for ReplacingMergeTree table -- 
                        you must add this column manually (any of UInt*, Date or DateTime types)');
                }

                if (!$columns[$options['versionColumn']]['type'] instanceof IntegerType &&
                    !$columns[$options['versionColumn']]['type'] instanceof BigIntType &&
                    !$columns[$options['versionColumn']]['type'] instanceof SmallIntType &&
                    !$columns[$options['versionColumn']]['type'] instanceof DateType &&
                    !$columns[$options['versionColumn']]['type'] instanceof DateTimeType
                ) {
                    throw new Exception('For ReplacingMergeTree tables `versionColumn` must be any of UInt* family, 
                        or Date, or DateTime types. ' . get_class($columns[$options['versionColumn']]['type']) . ' given.');
                }

                $engineOptions .= ', ' . $columns[$options['versionColumn']]['name'];
            }

            $engineOptions .= ')';
        }

        $sql[] = sprintf(
            'CREATE TABLE %s (%s) ENGINE = %s%s',
            $tableName,
            $this->getColumnDeclarationListSQL($columns),
            $engine,
            $engineOptions
        );

        return $sql;
    }

    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getAlterTableSQL(TableDiff $diff): array
    {
        $columnSql  = [];
        $queryParts = [];
        if (false !== $diff->newName || !empty($diff->renamedColumns)) {
            throw ClickHouseException::notSupported('RENAME COLUMN');
        }

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columnArray  = $column->toArray();
            $queryParts[] = 'ADD COLUMN ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] = 'DROP COLUMN ' . $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $column      = $columnDiff->column;
            $columnArray = $column->toArray();

            // Don't propagate default value changes for unsupported column types.
            if (($columnArray['type'] instanceof TextType || $columnArray['type'] instanceof BlobType) &&
                $columnDiff->hasChanged('default') &&
                1 === count($columnDiff->changedProperties)
            ) {
                continue;
            }

            $queryParts[] = 'MODIFY COLUMN ' . $this->getColumnDeclarationSQL(
                $column->getQuotedName($this),
                $columnArray
            );
        }

        $sql      = [];
        $tableSql = [];

        if (!$this->onSchemaAlterTable($diff, $tableSql) && (count($queryParts) > 0)) {
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . implode(
                ', ',
                $queryParts
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    protected function prepareDeclarationSQL(string $declarationSQL, array $columnDef): string
    {
        if (array_key_exists('notnull', $columnDef) && false === $columnDef['notnull']) {
            return 'Nullable(' . $declarationSQL . ')';
        }

        return $declarationSQL;
    }

    public function getColumnDeclarationSQL($name, array $field): string
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            $default = $this->getDefaultValueDeclarationSQL($field);

            $columnDef = $field['type']->getSqlDeclaration($field, $this) . $default;
        }

        return $name . ' ' . $columnDef;
    }

    public function getDecimalTypeDeclarationSQL(array $columnDef): string
    {
        return $this->prepareDeclarationSQL(StringClickHouseType::TYPE_STRING, $columnDef);
    }

    public function getCheckDeclarationSQL(array $definition): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getUniqueConstraintDeclarationSQL($name, UniqueConstraint $constraint): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getIndexDeclarationSQL($name, Index $index): void
    {
        // Index declaration in statements like CREATE TABLE is not supported.
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getForeignKeyReferentialActionSQL($action): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getUniqueFieldDeclarationSQL(): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getCurrentDateSQL(): string
    {
        return 'today()';
    }

    public function getCurrentTimeSQL(): string
    {
        return 'now()';
    }

    public function getCurrentTimestampSQL(): string
    {
        return 'toUnixTimestamp(now())';
    }

    public function getListDatabasesSQL(): string
    {
        return 'SHOW DATABASES';
    }

    public function getListTableColumnsSQL($table, $database = null): string
    {
        return sprintf(
            'DESCRIBE TABLE %s',
            ($database ? $this->quoteSingleIdentifier($database) . '.' : '') . $this->quoteSingleIdentifier($table)
        );
    }

    public function getListTablesSQL(): string
    {
        return "SELECT database, name FROM system.tables WHERE database != 'system' AND engine != 'View'";
    }

    public function getListViewsSQL($database): string
    {
        return "SELECT name FROM system.tables WHERE database != 'system' AND engine = 'View'";
    }

    public function getCreateViewSQL($name, $sql): string
    {
        return 'CREATE VIEW ' . $this->quoteIdentifier($name) . ' AS ' . $sql;
    }

    public function getDropViewSQL($name): string
    {
        return 'DROP TABLE ' . $this->quoteIdentifier($name);
    }

    public function getCreateDatabaseSQL($database): string
    {
        return 'CREATE DATABASE ' . $this->quoteIdentifier($database);
    }

    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return $this->prepareDeclarationSQL(DatableClickHouseType::TYPE_DATE_TIME, $fieldDeclaration);
    }

    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return $this->prepareDeclarationSQL(DatableClickHouseType::TYPE_DATE_TIME, $fieldDeclaration);
    }

    public function getTimeTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return $this->prepareDeclarationSQL(StringClickHouseType::TYPE_STRING, $fieldDeclaration);
    }

    public function getDateTypeDeclarationSQL(array $fieldDeclaration): string
    {
        return $this->prepareDeclarationSQL(DatableClickHouseType::TYPE_DATE, $fieldDeclaration);
    }

    public function getFloatDeclarationSQL(array $fieldDeclaration): string
    {
        return $this->prepareDeclarationSQL(
            NumericalClickHouseType::TYPE_FLOAT . BitNumericalClickHouseType::SIXTY_FOUR_BIT,
            $fieldDeclaration
        );
    }

    public function getDefaultTransactionIsolationLevel(): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    /* supports*() methods */

    public function supportsTransactions(): bool
    {
        return false;
    }

    public function supportsSavepoints(): bool
    {
        return false;
    }

    public function supportsPrimaryConstraints(): bool
    {
        return false;
    }

    public function supportsForeignKeyConstraints(): bool
    {
        return false;
    }

    public function supportsGettingAffectedRows(): bool
    {
        return false;
    }

    protected function doModifyLimitQuery($query, $limit, $offset): string
    {
        if (null === $limit) {
            return $query;
        }

        $query .= ' LIMIT ';
        if (null !== $offset) {
            $query .= $offset . ', ';
        }

        $query .= $limit;

        return $query;
    }

    public function getEmptyIdentityInsertSQL($tableName, $identifierColumnName): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function getTruncateTableSQL($tableName, $cascade = false): void
    {
        /*
         * For MergeTree* engines may be done with next workaround:
         *
         * SELECT partition FROM system.parts WHERE table= '$tableName';
         * ALTER TABLE $tableName DROP PARTITION $partitionName
         */
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function createSavePoint($savepoint): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function releaseSavePoint($savepoint): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    public function rollbackSavePoint($savepoint): void
    {
        throw ClickHouseException::notSupported(__METHOD__);
    }

    protected function getReservedKeywordsClass(): string
    {
        return ClickHouseKeywords::class;
    }

    public function getDefaultValueDeclarationSQL($field): string
    {
        if (!isset($field['default'])) {
            return '';
        }
        $defaultValue = $this->quoteStringLiteral($field['default']);
        $fieldType    = $field['type'] ?: null;
        if (null !== $fieldType) {
            if (DatableClickHouseType::TYPE_DATE === $fieldType ||
                $fieldType instanceof DateType ||
                in_array($fieldType, [
                    'Integer',
                    'SmallInt',
                    'Float',
                ], true) ||
                (
                    'BigInt' === $fieldType
                    && ParameterType::INTEGER === Type::getType('BigInt')->getBindingType()
                )
            ) {
                $defaultValue = $field['default'];
            } elseif (DatableClickHouseType::TYPE_DATE_TIME === $fieldType &&
                $field['default'] === $this->getCurrentTimestampSQL()
            ) {
                $defaultValue = $this->getCurrentTimestampSQL();
            }
        }

        return sprintf(' DEFAULT %s', $defaultValue);
    }

    public function getDoctrineTypeMapping($dbType): string
    {
        // FixedString
        if (0 === stripos($dbType, 'fixedstring')) {
            $dbType = 'fixedstring';
        }

        //Enum8
        if (0 === stripos($dbType, 'enum8')) {
            $dbType = 'enum8';
        }

        //Enum16
        if (0 === stripos($dbType, 'enum16')) {
            $dbType = 'enum16';
        }

        return parent::getDoctrineTypeMapping($dbType);
    }

    public function quoteStringLiteral($str): string
    {
        $c = $this->getStringLiteralQuoteCharacter();

        return $c . addslashes($str) . $c;
    }

    public function quoteSingleIdentifier($str): string
    {
        $c = $this->getIdentifierQuoteCharacter();

        return $c . addslashes($str) . $c;
    }

    public function getCurrentDatabaseExpression(): string
    {
        return 'currentDatabase()';
    }
}
