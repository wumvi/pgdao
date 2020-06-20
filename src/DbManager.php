<?php
declare(strict_types=1);

namespace Wumvi\PgDao;

class DbManager
{
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

        $this->connection = $this->isPersistent ? @pg_pconnect($this->url) : @pg_connect($this->url);
        if ($this->connection === false) {
            throw new DbException('error-to-fetch');
        }

        return $this->connection;
    }
}
