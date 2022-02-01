<?php

namespace Difra\DB\Adapters;

/**
 * Sqlite Adapter
 * Class Sqlite
 * @package Difra\DB
 */
class Sqlite extends Common
{
    /**
     * @inherit
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    /**
     * @inherit
     */
    protected function getConnectionString(): string
    {
        return
            "sqlite:{$this->config['file']}";
    }
}
