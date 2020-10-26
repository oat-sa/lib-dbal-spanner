# Use Google Cloud Spanner from php

WIP: the schema management (DDL) is not yet covered in this prototype although some parts of the field creation syntax were implemented.

## Installation

Follow this [documentation](doc/spanner-from-php.md) for installation.

## Running the tests

Running the unit tests is straightforward (and there are not many):

```
./vendor/phpunit/phpunit/phpunit --testsuite unit
```

Be careful that running the above command without the `testsuite` option will run the unit tests but also the integration tests, which requires a few more steps as detailed below.

## Running the integration tests

- Set up a real Spanner instance and create a database on it. Since the DDL (schema managing) is not yet taken care of in this prototype, the schema creation is performed during the database creation.
- Run the integration tests once or several times as needed.
- At the end of the integration tests, you should delete the database and the instance to avoid increased costs from Google.

**/!\ Beware !** though that the billing is based on a "every started hour is due" policy for each instance. This means that an instance created and deleted within even a second will be billed for 1 hour.
So if you plan to run the integration tests several times within an hour, it's cheaper to setup the instance, then run the tests as many times you want on the same instance, and then delete the instance at the end, else each instance would be billed for one hour.

### Setup your environment

Copy [/tests/env.ini.dist](tests/env.ini.dist) to `tests/env.ini` and provide your test environment data.

### Instance and database creation

Use the script [spanner-instance.php](tests/scripts/spanner-instance.php) to prepare your environment:

#### Create an instance and database(s):

```
php tests/scripts/spanner-instance.php create
```

#### Delete an instance and all database(s):

```
php tests/Integration/spanner-instance.php delete
```

#### Create database tables for existing database:

```
php tests/Integration/spanner-instance.php migrate
```

#### Displays existing instance and database(s):

```
php tests/scripts/spanner-instance.php status
```

### Running the tests

Provided that you have a Spanner instance running and a database on it, running the integration tests is as simple as:

```
./vendor/phpunit/phpunit/phpunit --testsuite integration
```

Of course, you can also run all the tests at once, using:

```
./vendor/phpunit/phpunit/phpunit
```

#### Check code coverage

```shell script
./vendor/phpunit/phpunit/phpunit --coverage-html tests/report
``` 

Again, don't forget to delete your instance afterwards.

## Standards

This library follows PSR-12, please make sure your code is following the standards 
for both tests and production code:

Auto-fixing (does not cover all scenarios):

```shell script
bin/phpcbf --standard=PSR12 src
bin/phpcbf --standard=PSR12 tests
``` 

Check errors:

```shell script
bin/phpcs --standard=PSR12 src
bin/phpcs --standard=PSR12 tests
```
