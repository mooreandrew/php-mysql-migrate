PHP MySQL Migration
============

This is an sql migration script for incremental database changes.

Features
--------
* Insert/Update/Delete/Create on a Mysql Database
* Incremental sql (ideal for agile/ci development)
* keeps track of which files already executed
* Out of order processing

This will create the database if it doesn't already exists. It also adds an extra table called migrations to keep track of which files have already been processed (flywaydb style).

If an sql statement fails, it will rollback all the changes in that file.

How to use?
-------------

Configure the config.inc.php file:

```
define('DATABASE_NAME', 'databse');
define('DATABASE_USER',  'username');
define('DATABASE_PASS',  'password');
define('DATABASE_SERVER',  'localhost');
```

Put your sql file in sql folder (plain sql) with the naming convention of:

```
version__name.sql
```

Then to run the migration do:

```
php phpmysqlmigration/migrate.php
```

Adding existing sql to this?
----------------------------

If you already have existing sql in your database and you want to implement it into this. Just do a mysqldump or an export in phpmyadmin and change the name to follow the convention. Then either recreate your database or just add the name of the file to the migrations table so it won't be executed again.