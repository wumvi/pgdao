
[![Latest Stable Version](https://poser.pugx.org/wumvi/pgdao/v/stable?format=flat-square)](https://packagist.org/packages/wumvi/pgdao)
[![GitHub issues](https://img.shields.io/github/issues/wumvi/pgdao.svg?style=flat-square)](https://github.com/wumvi/pgdao/issues)
[![Build status](https://travis-ci.org/wumvi/pgdao.svg?branch=master)](https://travis-ci.org/wumvi/pgdao)
[![codecov](https://codecov.io/gh/wumvi/pgdao/branch/master/graph/badge.svg)](https://codecov.io/gh/wumvi/pgdao)
[![Maintainability](https://api.codeclimate.com/v1/badges/82701aabdb73505c6e92/maintainability)](https://codeclimate.com/github/wumvi/pgdao/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/82701aabdb73505c6e92/test_coverage)](https://codeclimate.com/github/wumvi/pgdao/test_coverage)


docker volume create postgres-dev

#### Init
```php
include './vendor/autoload.php';

$url = "host=localhost port=5432 dbname=docker user=docker password=pwd options='--client_encoding=UTF8' connect_timeout=1";

$dbManager = new \Wumvi\PgDao\DbManager($url);
$pgFetch = new \Wumvi\PgDao\PgFetch($dbManager);
```

#### Работа с таблицами
##### Вернуть таблицу как результат

```postgresql
create function get_data_by_table(p_book_id integer) returns SETOF books
    language plpgsql as
$$
BEGIN
    RETURN QUERY select * from books b where b.id = p_book_id;
END;
$$;
```

sql call
```postgresql
select * from get_data_by_table(1)
```
php call
```php
$data = $pgFetch->tableFetchAll('get_data_by_table(:book_id)', ['book_id' => 1]);
```

##### Вернуть строчку с заданым типом

```postgresql
CREATE TYPE book_type AS (f1 int, f2 text);

CREATE OR REPLACE FUNCTION get_book_type(in book_id int ) 
returns book_type
    AS $$ 
    SELECT id f1, name f2 
    from books 
    where id > book_id 
$$  LANGUAGE SQL;
```

sql call
```postgresql
SELECT * FROM get_book_type(1);
```

php call
```php
$data = $pgFetch->tableFetchAll('get_book_type(:book_id)', ['book_id' => 1]);
```

##### Вернуть таблицу с задаными полями
вариант для sql

```postgresql
CREATE or replace FUNCTION get_multi_row_free_table_sql(in book_id int)
returns table(id int, name text)
AS $$
    SELECT id, name
    from books
    where id > book_id
$$ LANGUAGE SQL;
```

sql call
```postgresql
SELECT * FROM get_multi_row_free_table_sql(1);
```

вариант для plpgsql
```postgresql
-- важно чтобы, out имена не совпадали с select именами
-- иначе база не поймёт что надо выводить и будет алертить
CREATE or replace FUNCTION get_multi_row_free_table_plpgsql(in p_book_id int)
returns table(book_id books.id%type, book_name books.name%type)
AS $$
begin
return query
    SELECT b.id, name
    from books b
    where id > p_book_id;
end;
$$ LANGUAGE plpgsql;
```

sql call
```postgresql
SELECT book_id, book_name FROM get_multi_row_free_table_plpgsql(1);
```

более сложный вариант с next и query
```postgresql
CREATE or replace FUNCTION get_multi_row_free_table_plpgsql_next()
returns table(book_id books.id%type, book_name books.name%type)
AS $$
begin
    book_id := 1;
    book_name := 'test-1';
return next;
    book_id := 2;
    book_name := 'test-2';
    return next;
    return query
        select b.id, b.name
        from books b
        where b.id = 4;
end;
$$ LANGUAGE plpgsql;
```
sql call
```postgresql
SELECT book_id, book_name FROM get_multi_row_free_table_plpgsql_next();
```
php call
```php
$data = $pgFetch->tableFetchAll('get_multi_row_free_table_plpgsql_next()');
```

##### Расширить вывод таблицы с полем
вариант для sql
```postgresql
CREATE or replace FUNCTION extend_table(in book_id int)
returns table(b books, status boolean) AS
$$
    SELECT b, true status 
    from books b 
    where id > book_id
$$ LANGUAGE SQL;
```
sql call
```postgresql
SELECT (t.b).*, t.status FROM extend_table_sql(1) t;
```
php call
```php
$data = $pgFetch->tableFetchAll('extend_table_sql(:book_id)', ['book_id' => 1], 3, '(t.b).*, t.status');
```
вариант для plpgsql
```postgresql
CREATE or replace FUNCTION extend_table_plpgsql(in book_id int)
returns table(book books, status boolean)
LANGUAGE plpgsql AS
$$
BEGIN
    return query 
        SELECT b as book, true as status 
        from books b 
        where id > book_id;
END;
$$;
```
sql call
```postgresql
SELECT (t.book).*, t.status FROM extend_table_plpgsql(1) t;
```
---
#### Работа со скалярными данными
##### Вернуть скалярный тип
Вариант для sql
```postgresql
CREATE OR REPLACE FUNCTION return_scalar_type_sql(in book_id int)
    returns int AS
$$
    select book_id + 1
$$ LANGUAGE SQL;
```
sql call
```postgresql
select return_scalar_type_sql(1);
```

вариант для plpgsql
```postgresql
CREATE OR REPLACE FUNCTION return_scalar_type_plpgsql(i integer) 
RETURNS integer AS $$
BEGIN
    RETURN i + 1;
END;
$$ LANGUAGE plpgsql;
```
sql call
```postgresql
select return_scalar_type_plpgsql(2)
```

вариант для select по полю из таблицы
```postgresql
-- Лучше поставить limit 1, иначе если функция вернёт более 1 строчки, то будет exception
CREATE or replace FUNCTION get_scalar_name_by_field_type(in book_id int)
returns books.name%type
AS
$$
begin
    return (select name from books where id > book_id limit 1);
end
$$ LANGUAGE plpgsql;
```
sql call
```postgresql
select get_scalar_name_by_field_type(1)
```
#### Работа с Record
##### Вернуть record
```postgresql
-- для PHP не очень удобрый вариант
CREATE or replace FUNCTION return_record_plpgsql(in book_id int)
returns setof record
AS $$
begin
    return query SELECT *
    from books b 
    where id > book_id limit 3;
end
$$ LANGUAGE plpgsql;
```

sql call
```postgresql
-- (обязательно правильный порядок типов полей, как в фукнции)
select * from return_record_plpgsql(2) as (id int, name varchar(20));
```

#### Работа с курсорами
##### Вернуть статичный курсор
```postgresql
create function get_data_by_static_cursor(p_book_id int) returns refcursor
    language plpgsql as
$$
DECLARE
    _result CONSTANT REFCURSOR := '_result';
BEGIN
    OPEN _result FOR select * from books b where b.id > p_book_id;
    RETURN _result;
END;
$$;
```

sql call
```postgresql
begin;
    select get_data_by_static_cursor(1);
    fetch all from __result;
commit ;
```

##### Вернуть имененованный курсор
```postgresql
create or replace function get_data_by_cursor_name(refcursor, p_book_id int) returns refcursor
    language plpgsql as
$$
BEGIN
    OPEN $1 FOR select * from books b where b.id = p_book_id;
    RETURN $1;
END;
$$;
```

sql call
```postgresql
begin;
    select get_data_by_cursor_name('ref01', 1);
    fetch all from ref01;
commit ;
```

##### Вернуть два курсора
```postgresql
create or replace function get_data_by_cursor_name_two(refcursor, refcursor, p_book_id int) returns refcursor
    language plpgsql as
$$
BEGIN
    OPEN $1 FOR select * from books b where b.id <= p_book_id;
    RETURN $1;
    OPEN $2 FOR select * from books b where b.id > p_book_id;
    RETURN $2;
END;
$$;
```

sql call
```postgresql
begin;
    select get_data_by_cursor_name_two('ref01', 'ref02', 1);
    fetch all from ref01;
    fetch all from ref02;
commit ;
```

#### Exception
```postgresql
CREATE OR REPLACE FUNCTION call_exception(p_book_id integer) 
RETURNS integer AS $$
BEGIN
    RAISE EXCEPTION 'Not found book ID --> %', p_book_id
          USING HINT = 'Check your book ID';
END;
$$ LANGUAGE plpgsql;
```

##### Void function
```postgresql
CREATE OR REPLACE FUNCTION call_void_func()
    RETURNS void AS $$
BEGIN
    insert into books (id, name)
    values(100, 'book-100');
END;
$$ LANGUAGE plpgsql;
```
sql call

```postgresql
select call_void_func()
```

php call 
```php
$pgFetch->call('call_void_func()');
```

#### Custom type
```postgresql
CREATE TYPE custom_type AS (f1 int, f2 text);
create or replace function test_types_func(
    refcursor,
    p_data date,
    p_text varchar(20),
    p_array int[],
    p_bool bool,
    p_custom_type custom_type,
    p_null_type date
) returns refcursor as
$$
BEGIN
    OPEN $1 FOR select p_data, p_text, p_array, p_bool, p_custom_type, p_null_type;
    RETURN $1;
END;
$$ language plpgsql;
```