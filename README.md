[TOC]

SQL Server Driver for Drupal for Windows or Linux
=====================

This contrib module allows the Drupal CMS to connect to Microsoft SQL Server databases.

Setup
-----

Use [composer](http://getcomposer.org) to install the module:

```bash
$ php composer require drupal/sqlsrv:8.x-2.x-dev
```

The `drivers/` directory needs to be copied to webroot of your drupal installation, or you can symlink directly the directories by running in your "webs" directory:

```bash
mklink /d drivers modules\contrib\sqlsrv\drivers
```

## Configuration

This driver has advanced features that you can setup in you settings.php.

```php
$settings['mssql'] = [
  'default_isolation_level' => false,
  'default_direct_queries' => true,
  'default_statement_caching' => false,
  'append_stack_comments' => false,
  'additional_dsn' => []
];
```

**default_isolation_level**

The transaction isolation level used by the driver.  Available options are:

```php
PDO::SQLSRV_TXN_READ_UNCOMMITTED
PDO::SQLSRV_TXN_READ_COMMITTED
PDO::SQLSRV_TXN_REPEATABLE_READ
PDO::SQLSRV_TXN_SNAPSHOT
PDO::SQLSRV_TXN_SERIALIZABLE
```

Use "false" to default to the connection's deafult configured isolation level. On very high traffic **transactional** sites you are encouraged to use advanced transactional configuration to avoid deadlocks and improve performance.

**default_direct_queries**

Forces the usage of PDO::SQLSRV_ATTR_DIRECT_QUERY for all queries.

https://docs.microsoft.com/es-es/sql/connect/php/direct-statement-execution-prepared-statement-execution-pdo-sqlsrv-driver?view=sql-server-ver15

This setting is only recommended for diagnose and debugging purposes

**default_statement_caching**

Enables caching of prepared PDO statements. Very useful to improve performance on high traffic transactional sites. Increases memory requirements.

