## Installation

```
composer require stereoflo/dbal-clickhouse
```

## Initialization
### Symfony
configure...
```yml
# app/config/config.yml
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
$conn = $this->get('doctrine.dbal.clickhouse_connection');
``
