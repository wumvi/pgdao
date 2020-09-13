<?php
declare(strict_types=1);

namespace Wumvi\PgDao;

class PgFetch
{
    public const DEFAULT_SELECT_MOD = '*';
    public const UNLIMIT = -1;

    private DbManager $dbManager;
    private bool $isDebug;

    public function __construct(DbManager $dbManager, $isDebug = false)
    {
        $this->dbManager = $dbManager;
        $this->isDebug = $isDebug;
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
    public static function queryParams($connection, string $fn, string $sql, array $vars, bool $isDebug = false)
    {
        $varsSorted = self::prepareVars($vars);
        $result = @pg_query_params($connection, $sql, $varsSorted);
        if ($result === false) {
            self::triggerError($connection, $sql, $vars, $isDebug);
            throw new DbException('error-to-sql-' . $fn);
        }

        return $result;
    }

    /**
     * @param resource $connection
     * @param string $sql
     * @param array<mixed> $vars
     * @param bool $isDebug
     */
    public static function triggerError($connection, string $sql, array $vars, bool $isDebug)
    {
        if ($isDebug) {
            $msg = sprintf(
                "Msg: %s\nSql: %s\nVars: %s",
                pg_last_error($connection),
                $sql,
                var_export($vars, true)
            );
            trigger_error($msg);
        }
    }

    /**
     * @param resource $connection
     * @param string $fn
     * @param bool $isDebug
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public static function simpleCursorFetch($connection, string $fn, bool $isDebug): array
    {
        $sql = 'SELECT ' . $fn . '; FETCH ALL FROM _result';
        $result = @pg_query($connection, $sql);
        if ($result === false) {
            self::triggerError($connection, $sql, [], $isDebug);
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
     * @param bool $isDebug
     *
     * @return array<mixed>
     *
     * @throws DbException
     */
    public static function partyCursorFetch(
        $connection,
        string $fn,
        array $vars = [],
        array $cursors = [],
        bool $isDebug
    ): array {
        $sql = 'BEGIN';
        $result = @pg_query($connection, $sql);
        // @codeCoverageIgnoreStart
        if ($result === false) {
            self::triggerError($connection, $sql, [], $isDebug);
            throw new DbException('error-to-start-transaction-in-' . $fn);
        }
        // @codeCoverageIgnoreEnd

        $sql = 'SELECT ' . self::replaceParams($fn, $vars);
        self::queryParams($connection, $fn, $sql, $vars, $isDebug);

        $data = [];
        if (empty($cursors)) {
            $sql = 'FETCH ALL FROM _result';
            $result = @pg_query($connection, $sql);
            // @codeCoverageIgnoreStart
            if ($result === false) {
                self::triggerError($connection, $sql, [], $isDebug);
                throw new DbException('error-to-fetch-in-' . $fn);
            }
            // @codeCoverageIgnoreEnd
            $data = pg_fetch_all($result) ?: [];
            $sql = 'CLOSE _result';
            $result = @pg_query($sql);
            if ($result === false) {
                self::triggerError($connection, $sql, [], $isDebug);
                throw new DbException('error-to-close-cursor-in-' . $fn);
            }
        } else {
            foreach ($cursors as $cursorName) {
                $sql = 'FETCH ALL FROM ' . $cursorName;
                $result = @pg_query($connection, $sql);
                // @codeCoverageIgnoreStart
                if ($result === false) {
                    self::triggerError($connection, $sql, [], $isDebug);
                    throw new DbException('error-to-fetch-cursor-' . $cursorName . '-in-' . $fn);
                }
                // @codeCoverageIgnoreEnd
                $data[$cursorName] = pg_fetch_all($result) ?: [];
                $sql = 'CLOSE ' . $cursorName;
                $result = @pg_query($sql);
                // @codeCoverageIgnoreStart
                if ($result === false) {
                    self::triggerError($connection, $sql, [], $isDebug);
                    throw new DbException('error-to-close-cursor-' . $cursorName . '-in-' . $fn);
                }
                // @codeCoverageIgnoreEnd
            }
        }
        // @codeCoverageIgnoreStart
        $sql = 'END';
        $result = @pg_query($connection, $sql);
        if ($result === false) {
            self::triggerError($connection, $sql, [], $isDebug);
            throw new DbException('error-to-end-transaction-in-' . $fn);
        }
        // @codeCoverageIgnoreEnd

        return $data;
    }

    /**
     * @param resource $connection
     * @param string $rawSql
     * @param array<mixed> $vars
     * @param bool $isDebug
     *
     * @return resource
     *
     * @throws DbException
     */
    public static function prepareRawSql($connection, string $rawSql, array $vars, bool $isDebug)
    {
        $sql = $fnWithParam = self::replaceParams($rawSql, $vars);

        return self::queryParams($connection, 'raw-sql', $sql, $vars, $isDebug);
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
        $result = self::queryParams($connection, $fn, $sql, $vars, $this->isDebug);

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
        $result = self::queryParams($connection, $fn, $sql, $vars, $this->isDebug);

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
        $result = self::queryParams($connection, $fn, $sql, $vars, $this->isDebug);

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
            self::simpleCursorFetch($connection, $fn, $this->isDebug) :
            self::partyCursorFetch($connection, $fn, $vars, $cursors, $this->isDebug);
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

        $result = self::prepareRawSql($connection, $rawSql, $vars, $this->isDebug);

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

        $result = self::prepareRawSql($connection, $rawSql, $vars, $this->isDebug);

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

        self::queryParams($connection, $fn, 'SELECT ' . $fn, $vars, $this->isDebug);
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
