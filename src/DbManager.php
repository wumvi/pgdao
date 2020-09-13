<?php
declare(strict_types=1);

namespace Wumvi\PgDao;

class DbManager
{
    /**
     * @var array<mixed>
     */
    public static array $vars = [];

    private string $url;
    private bool $isPersistent;

    /** @var resource|bool */
    private $connection = false;

    public function __construct(string $url, bool $isPersistent = true)
    {
        $this->url = $url;
        $this->isPersistent = $isPersistent;
    }

    public function isConnect(): bool
    {
        // @codeCoverageIgnoreStart
        if (!is_resource($this->connection)) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return pg_connection_status($this->connection) === PGSQL_CONNECTION_OK;
    }

    public function disconnect(): void
    {
        if (is_resource($this->connection)) {
            pg_close($this->connection);
        }
    }

    /**
     * @return resource
     *
     * @throws DbException
     */
    public function getConnection()
    {
        if (is_resource($this->connection)) {
            return $this->connection;
        }

        $url = $this->url;
        foreach (self::$vars as $var => $value) {
            $url = str_replace('{' . $var . '}', $value, $url);
        }

        $this->connection = $this->isPersistent ? @pg_pconnect($url) : @pg_connect($url);
        if ($this->connection === false) {
            throw new DbException('error-db-connect');
        }

        return $this->connection;
    }
}
