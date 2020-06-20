<?php

namespace Wumvi\PgDao;

/**
 * @codeCoverageIgnore
 */
class DbDao
{
    protected PgFetch $db;

    public function __construct(DbManager $dbManager)
    {
        $this->db = new PgFetch($dbManager);
    }
}
