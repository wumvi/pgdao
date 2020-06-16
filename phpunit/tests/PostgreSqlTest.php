<?php

use PHPUnit\Framework\TestCase;
use Wumvi\PgDao\DbException;
use Wumvi\PgDao\DbManager;
use \Wumvi\PgDao\PgFetch;

class PostgreSqlTest extends TestCase
{
    private const URL = "host=localhost port=5432 dbname=docker user=docker password=pwd options='--client_encoding=UTF8' connect_timeout=1";

    public function testConnect(): void
    {
        $db = new DbManager(self::URL, false);
        $db->getConnection();
        $this->assertTrue($db->isConnect(), 'connect to db');
        $db->disconnect();
    }

    public function testWrongConnect(): void
    {
        $this->expectException(DbException::class);
        $db = new DbManager('host=localhost2 port=5432', false);
        $db->getConnection();
    }

    /**
     * @throws DbException
     */
    public function testTable(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $act = $pgFetch->tableFetchAll('get_data_by_table(:book_id)', ['book_id' => 1]);
        $exp = [['id' => '1', 'name' => 'book-1']];
        $this->assertEquals($exp, $act, 'Func table fetch all');

        $act = $pgFetch->tableFetchFirst('get_data_by_table_array(:book_ids)', [
            'book_ids' => [2, 1]
        ]);
        $exp = ['id' => '2', 'name' => 'book-2'];
        $this->assertEquals($exp, $act, 'Func table fetch first array');

        $db->disconnect();
    }

    /**
     * @throws DbException
     */
    public function testCursor(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $act = $pgFetch->cursorFetchAll('get_data_by_cursor(:book_id)', ['book_id' => 2]);
        $exp = [['id' => '1', 'name' => 'book-1'], ['id' => '2', 'name' => 'book-2']];
        $this->assertEquals($exp, $act, 'Party cursor');

        $act = $pgFetch->cursorFetchAll('get_data_by_cursor(2)');
        $exp = [['id' => '1', 'name' => 'book-1'], ['id' => '2', 'name' => 'book-2']];
        $this->assertEquals($exp, $act, 'Simple cursor');

        $db->disconnect();
    }

    /**
     * @throws DbException
     */
    public function testScalar(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $act = $pgFetch->scalar('return_scalar_type_sql(1)');
        $this->assertEquals('2', $act, 'Scalar');

        $db->disconnect();
    }

    /**
     * @throws DbException
     */
    public function testEmptyResponse(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $act = $pgFetch->tableFetchFirst('get_data_by_table(:book_id)', ['book_id' => -1]);
        $this->assertEquals([], $act, 'Func table fetch all');

        $act = $pgFetch->tableFetchAll('get_data_by_table(:book_id)', ['book_id' => -1]);
        $this->assertEquals([], $act, 'Func table fetch first');

        $act = $pgFetch->cursorFetchAll('get_data_by_cursor(:book_id)', ['book_id' => -1]);
        $this->assertEquals([], $act, 'Func cursor fetch all');

        $db->disconnect();
    }

    /**
     * @throws DbException
     */
    public function testRaiseException(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $this->expectException(DbException::class);
        $pgFetch->scalar('call_exception(1)');

        $db->disconnect();
    }

    public function testRawSql(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $act = $pgFetch->rawSqlFetchAll('select * from books b where b.id in (1, 2)');
        $exp = [['id' => '1', 'name' => 'book-1'], ['id' => '2', 'name' => 'book-2']];
        $this->assertEquals($exp, $act, 'Raw sql fetch all');

        $act = $pgFetch->rawSqlFetchFirst('select * from books b where b.id = 1');
        $exp = ['id' => '1', 'name' => 'book-1'];
        $this->assertEquals($exp, $act, 'Raw sql first all');

        $act = $pgFetch->rawSqlFetchFirst('select :test as val', ['test' => [[1, 2], [3, 4]]]);
        $exp = ['val' => '{[1,2],[3,4]}'];
        $this->assertEquals($exp, $act, 'Convert array');

        $db->disconnect();
    }

    public function testWrongSqlCursor(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $this->expectException(DbException::class);
        $pgFetch->cursorFetchAll('nononono(1)');

        $db->disconnect();
    }

    public function testWrongSqlTable(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $this->expectException(DbException::class);
        $pgFetch->tableFetchAll('nononono(1)');

        $db->disconnect();
    }

    public function testWrongSqlScalar(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $this->expectException(DbException::class);
        $pgFetch->scalar('nononono(1)');

        $db->disconnect();
    }

    public function testWrongRawSql(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $this->expectException(DbException::class);
        $pgFetch->scalar('nononono(1)');

        $db->disconnect();
    }

    public function testCall()
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $pgFetch->call('call_void_func()');

        $act = $pgFetch->tableFetchFirst('get_data_by_table_array(ARRAY[100])');
        $exp = ['id' => '100', 'name' => 'book-100'];
        $this->assertEquals($exp, $act, 'Check call function');

        $db->disconnect();
    }

    public function testArrayToType(): void
    {
        $act = PgFetch::arrayToType([1, 'test']);
        $this->assertEquals('(1,\'test\')', $act, 'Convert array to type');
    }

    public function testCheckTypes(): void
    {
        $db = new DbManager(self::URL, false);
        $pgFetch = new PgFetch($db);

        $act = $pgFetch->cursorFetchAll('test_types_func(\'c2\', :date, :text, :array, :bool, :custom_type, :null_type)',
            [
                'date' => '2020-02-01',
                'text' => 'text2',
                'array' => [1, 2],
                'bool' => true,
                'custom_type' => PgFetch::arrayToType([1, '3']),
                'null_type' => null,
            ], ['c2']);
        $exp = [
            'c2' => [
                [
                    'p_data' => '2020-02-01',
                    'p_text' => 'text2',
                    'p_array' => '{1,2}',
                    'p_bool' => 't',
                    'p_custom_type' => "(1,'3')",
                    'p_null_type' => null,
                ]
            ]
        ];
        $this->assertEquals($exp, $act, 'call func with different types');

        $db->disconnect();
    }
}