Dependencies:
- Slim framework latest
- SQLite3 Class, PHP 5.3.0 or higher (preferably latest)

Composer (getcomposer.org) was used to load the Slim framework dependency, please use Composer to install the dependency.
The Slim framework provides a minimal routing system, without any fancy bells and whistles I didn't need.

Use the included create.sql and populate.sql to create and populate the database (db.sqlite3) tables with some dummy data.

The full API structure can be found in notes.txt, with the only exception being that all methods rely on HTTP Basic Authentication instead of the custom Token method.