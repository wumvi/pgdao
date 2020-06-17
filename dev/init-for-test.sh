#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username postgres <<-EOSQL
  CREATE USER docker WITH PASSWORD 'pwd';
  CREATE DATABASE docker;
  GRANT ALL PRIVILEGES ON DATABASE docker TO docker;
EOSQL

psql -v ON_ERROR_STOP=1 --username docker docker < /docker-entrypoint-initdb.d/init.sql.txt