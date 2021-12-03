## Installation

```
composer require stereoflo/dbal-clickhouse
```

## Initialization
### Custom PHP script
```php
$connectionParams = [
    'host' => 'localhost',
    'port' => 8123,
    'user' => 'default',
    'password' => '',
    'dbname' => 'default',
    'driverClass' => 'DBALClickHouse\Driver',
    'wrapperClass' => 'DBALClickHouse\Connection',
    'driverOptions' => [
        'extremes' => false,
        'readonly' => true,
        'max_execution_time' => 30,
        'enable_http_compression' => 0,
        'https' => false,
    ],
];
$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, new \Doctrine\DBAL\Configuration());
```
`driverOptions` are special `smi2/phpclickhouse` client [settings](https://github.com/smi2/phpClickHouse#settings)

### Symfony
configure...
```yml
# app/config/config.yml
doctrine:
    dbal:
        connections:
            clickhouse:
                host:     localhost
                port:     8123
                user:     default
                password: ""
                dbname:   default
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
$conn = $this->get('doctrine.dbal.clickhouse_connection');
``
