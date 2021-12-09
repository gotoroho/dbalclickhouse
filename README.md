## Installation

```
composer require stereoflo/dbal-clickhouse
```

## Initialization
### Symfony
configure...
```yml
# config/packages/doctrine.yaml
doctrine:
  dbal:
    dbname:   default
    host:     localhost
    port:     8123
    user:     default
    password: ""
    driver_class: DBALClickHouse\Driver
    wrapper_class: DBALClickHouse\Connection
    options:
      enable_http_compression: 1
      max_execution_time: 60
      #mysql:
            #   ...
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
    public function getByAffiliateId(int $userId): array
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
