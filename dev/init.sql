create table books
(
	id integer,
	name varchar(20)
);

insert into books (id, name) values
(1, 'book-1'),
(2, 'book-2'),
(3, 'book-3'),
(4, 'book-4');

create function get_data_by_table(p_book_id integer) returns SETOF books
    language plpgsql as
$$
BEGIN
    RETURN QUERY select * from books b where b.id = p_book_id;
END;
$$;

create function get_data_by_table_array(p_book_ids integer[]) returns SETOF books
    language plpgsql as
$$
BEGIN
    RETURN QUERY select * from books b where b.id = p_book_ids[1];
END;
$$;

create or replace function get_data_by_cursor_name(refcursor, p_book_id int) returns refcursor
    language plpgsql as
$$
BEGIN
    OPEN $1 FOR select * from books b where b.id = p_book_id;
    RETURN $1;
END;
$$;

CREATE OR REPLACE FUNCTION return_scalar_type_sql(in book_id int)
    returns int AS
$$
    select book_id + 1
$$ LANGUAGE SQL;

create function get_data_by_table(p_book_id integer) returns SETOF books
    language plpgsql as
$$
BEGIN
    RETURN QUERY select * from books b where b.id = p_book_id;
END;
$$;

create function get_data_by_cursor(p_book_id int) returns refcursor
    language plpgsql as
$$
DECLARE
    _result CONSTANT REFCURSOR := '_result';
BEGIN
    OPEN _result FOR select * from books b where b.id > p_book_id;
    RETURN _result;
END;
$$;

CREATE OR REPLACE FUNCTION call_exception(p_book_id integer)
    RETURNS integer AS
$$
BEGIN
    RAISE EXCEPTION 'Not found book ID --> %', p_book_id
        USING HINT = 'Check your book ID';
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION call_void_func()
    RETURNS void AS
$$
BEGIN
    update books
    set name = 'book-test'
    where id = 1000;
END;
$$ LANGUAGE plpgsql;

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



