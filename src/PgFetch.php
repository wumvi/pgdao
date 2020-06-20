<?php
declare(strict_types=1);

namespace Wumvi\PgDao;

class PgFetch
{
    public const DEFAULT_SELECT_MOD = '*';
    public const UNLIMIT = -1;

    private DbManager $dbManager;

    public function __construct(DbManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * @param string $sql
     * @param array<mixed> $vars
     *
     * @return string
     */
    public static function replaceParams(string $sql, array $vars): string
    {
        $fnWithParam = $sql;
        $varIndex = 1;
        foreach ($vars as $name => $value) {
            $fnWithParam = str_replace(':' . $name, '$' . $varIndex, $fnWithParam);
            $varIndex += 1;
        }

        return $fnWithParam;
    }

    /**
     * @param string $fn
     * @param array<mixed> $vars
     * @param int $limit
     * @param string $selectMod
     *
     * @return string
     */
    public static function makeFnTableSql(
        string $fn,
        array $vars = [],
        int $limit = self::UNLIMIT,
        string $selectMod = self::DEFAULT_SELECT_MOD
    ) {
        $fnWithParam = self::replaceParams($fn, $vars);
        $sql = 'SELECT ' . $selectMod . ' FROM ' . $fnWithParam . ' t';
        if ($limit !== self::UNLIMIT) {
            $sql .= ' LIMIT ' . $limit;
        }

        return $sql;
    }

    /**
     * @param string $fn
     * @param array<mixed> $vars
     *
     * @return string
     */
    public static function makeFnScalarSql(string $fn, array $vars = [])
    {
        $fnWithParam = self::replaceParams($fn, $vars);

        return 'SELECT ' . $fnWithParam . ' AS RESULT';
    }

    /**
     * @param resource $connection
     * @param string $fn
     * @param string $sql
     * @param array<mixed> $vars
     *
     * @return resource
     *
     * @throws DbException
     */
    public static function queryParams($connection, string $fn, string $sql, array $vars)
    {
        $vars = self::prepareVars($vars);
        $result = @pg_query_params($connection, $sql, $vars);
        if ($result === false) {
            throw new DbException('error-to-exec-' . $fn);
        }

        return $result;
    }

    /**
     * @param resource $connection
     * @param string $fn
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public static function simpleCursorFetch($connection, string $fn): array
    {
        $sql = 'SELECT ' . $fn . '; FETCH ALL FROM _result';
        $result = @pg_query($connection, $sql);
        if ($result === false) {
            throw new DbException('error-to-fetch-from-' . $fn);
        }

        return pg_fetch_all($result) ?: [];
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function convert($value)
    {
        switch (gettype($value)) {
            case 'string':
                return '\'' . pg_escape_string($value) . '\'';
            case 'array':
                $data = array_map(fn($item) => self::convert($item), $value);

                return '[' . implode(',', $data) . ']';
            default:
                return $value;
        }
    }

    /**
     * @param array<mixed> $vars
     *
     * @return array<mixed>
     */
    public static function prepareVars(array $vars): array
    {
        $result = [];
        foreach ($vars as $var) {
            if (is_array($var)) {
                $list = array_map(fn($item) => self::convert($item), $var);
                $var = '{' . implode(',', $list) . '}';
            }

            $result[] = $var;
        }

        return $result;
    }

    /**
     * @param resource $connection
     * @param string $fn
     * @param array<mixed> $vars
     * @param array<string> $cursors
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public static function partyCursorFetch(
        $connection,
        string $fn,
        array $vars = [],
        array $cursors = []
    ): array {
        $result = @pg_query($connection, 'BEGIN');
        // @codeCoverageIgnoreStart
        if ($result === false) {
            throw new DbException('error-to-start-transaction-in-' . $fn);
        }
        // @codeCoverageIgnoreEnd
        $sql = 'SELECT ' . self::replaceParams($fn, $vars);
        self::queryParams($connection, $fn, $sql, $vars);

        $data = [];
        if (empty($cursors)) {
            $result = @pg_query($connection, 'FETCH ALL FROM _result');
            // @codeCoverageIgnoreStart
            if ($result === false) {
                throw new DbException('error-to-fetch-in-' . $fn);
            }
            // @codeCoverageIgnoreEnd
            $data = pg_fetch_all($result) ?: [];
            $result = @pg_query('CLOSE _result');
            if ($result === false) {
                throw new DbException('error-to-close-cursor-in-' . $fn);
            }
        } else {
            foreach ($cursors as $cursorName) {
                $result = @pg_query($connection, 'FETCH ALL FROM ' . $cursorName);
                // @codeCoverageIgnoreStart
                if ($result === false) {
                    throw new DbException('error-to-fetch-cursor-' . $cursorName . '-in-' . $fn);
                }
                // @codeCoverageIgnoreEnd
                $data[$cursorName] = pg_fetch_all($result) ?: [];
                $result = @pg_query('CLOSE ' . $cursorName);
                // @codeCoverageIgnoreStart
                if ($result === false) {
                    throw new DbException('error-to-close-cursor-' . $cursorName . '-in-' . $fn);
                }
                // @codeCoverageIgnoreEnd
            }
        }
        // @codeCoverageIgnoreStart
        $result = @pg_query($connection, 'END');
        if ($result === false) {
            throw new DbException('error-to-end-transaction-in-' . $fn);
        }
        // @codeCoverageIgnoreEnd

        return $data;
    }

    /**
     * @param resource $connection
     * @param string $rawSql
     * @param array<mixed> $vars
     *
     * @return resource
     *
     * @throws DbException
     */
    public static function prepareRawSql($connection, string $rawSql, array $vars)
    {
        $sql = $fnWithParam = self::replaceParams($rawSql, $vars);

        return self::queryParams($connection, 'raw-sql', $sql, $vars);
    }

    /**
     * @param string $fn
     * @param array<mixed> $vars
     * @param int $limit
     * @param string $selectMod
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public function tableFetchAll(
        string $fn,
        array $vars = [],
        int $limit = self::UNLIMIT,
        string $selectMod = self::DEFAULT_SELECT_MOD
    ): array {
        $connection = $this->dbManager->getConnection();
        $sql = self::makeFnTableSql($fn, $vars, $limit, $selectMod);
        $result = self::queryParams($connection, $fn, $sql, $vars);

        return pg_fetch_all($result) ?: [];
    }

    /**
     * @param string $fn
     * @param array<mixed> $vars
     * @param string $selectMod
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public function tableFetchFirst(
        string $fn,
        array $vars = [],
        string $selectMod = self::DEFAULT_SELECT_MOD
    ): array {
        $connection = $this->dbManager->getConnection();
        $sql = self::makeFnTableSql($fn, $vars, 1, $selectMod);
        $result = self::queryParams($connection, $fn, $sql, $vars);

        return pg_fetch_assoc($result) ?: [];
    }

    /**
     * @param string $fn
     * @param array<mixed> $vars
     *
     * @return string
     *
     * @throws DbException
     */
    public function scalar(string $fn, array $vars = [])
    {
        $connection = $this->dbManager->getConnection();
        $sql = self::makeFnScalarSql($fn, $vars);
        $result = self::queryParams($connection, $fn, $sql, $vars);

        return pg_fetch_result($result, 0, 0);
    }

    /**
     * @param string $fn
     * @param array<mixed> $vars
     * @param array<string> $cursors
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public function cursorFetchAll(string $fn, array $vars = [], $cursors = []): array
    {
        $connection = $this->dbManager->getConnection();

        return empty($vars) ?
            self::simpleCursorFetch($connection, $fn) :
            self::partyCursorFetch($connection, $fn, $vars, $cursors);
    }

    /**
     * @param string $rawSql
     * @param array<mixed> $vars
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public function rawSqlFetchAll(string $rawSql, array $vars = [])
    {
        $connection = $this->dbManager->getConnection();

        $result = self::prepareRawSql($connection, $rawSql, $vars);

        return pg_fetch_all($result) ?: [];
    }

    /**
     * @param string $rawSql
     * @param array<mixed> $vars
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public function rawSqlFetchFirst(string $rawSql, array $vars = []): array
    {
        $connection = $this->dbManager->getConnection();

        $result = self::prepareRawSql($connection, $rawSql, $vars);

        return pg_fetch_assoc($result) ?: [];
    }

    /**
     * @param string $fn
     * @param array<mixed> $vars
     *
     * @throws DbException
     */
    public function call(string $fn, array $vars = []): void
    {
        $connection = $this->dbManager->getConnection();

        self::queryParams($connection, $fn, 'SELECT ' . $fn, $vars);
    }

    /**
     * @param array<mixed> $array
     *
     * @return string
     */
    public static function arrayToType(array $array): string
    {
        return '(' . implode(',', array_map(fn($item) => self::convert($item), $array)) . ')';
    }
}
