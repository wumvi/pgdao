#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username  postgres <<-EOSQL
    CREATE USER docker WITH PASSWORD 'pwd';
    CREATE DATABASE docker;
    GRANT ALL PRIVILEGES ON DATABASE docker TO docker;
    create table books
    (
      id int,
      name varchar(20)
    );
    insert into books (id, name) values (1, 'book-1'), (2, 'book-2'), (3, 'book-3');
EOSQL

psql -v ON_ERROR_STOP=1 --username  postgres < /docker-entrypoint-initdb.d/init.sql