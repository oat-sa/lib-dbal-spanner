# Use Google Cloud Spanner from php

All this is WIP!

## Installation

Following this [documentation](doc/spanner-from-php.md) for installation.

## Instance and database creation

Give the name of the instance an database you want to create in the according fields in `tests/spanner.php`:

```
$instanceName = 'php-dbal-tests-instance';
$databaseName = 'spanner-test-database';
```

3 commands are provided to manually manage a Spanner instance and create a first database schema (schema management by DBAL is outside of the scope of this prototype):

Create an instance and database(s):

```
php tests/spanner.php start
```

Displays existing instance and database(s):

```
php tests/spanner.php list
```

Delete an instance and all database(s):

```
php tests/spanner.php stop
```

