## Installation

```
composer require stereoflo/dbal-clickhouse
```

## Initialization
### Symfony
configure...
```.dotenv
# .env
CLICKHOUSE_HOST=127.0.0.1
CLICKHOUSE_PORT=8123
CLICKHOUSE_USER=default
CLICKHOUSE_PASSWORD=
```

```yml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        dbname:   default
        host:     '%env(resolve:CLICKHOUSE_HOST)%'
        port:     '%env(resolve:CLICKHOUSE_PORT)%'
        user:     '%env(resolve:CLICKHOUSE_USER)%'
        password: '%env(resolve:CLICKHOUSE_PASSWORD)%'
        driver_class: DBALClickHouse\Driver
        wrapper_class: DBALClickHouse\Connection
        options:
            enable_http_compression: 1
            max_execution_time: 60

```
...and get from the service container
```php
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array<array<string, string>>
     */
    public function getByUserId(int $userId): array
    {
        $result = $this->connection
            ->createQueryBuilder()
            ->select('user.user_id')
            ->from('users', 'user')
            ->where('user.user_id = :user_id')
            ->setParameter('user_id', $userId)
            ->executeQuery();

        return $result->fetchAllAssociative();
``
