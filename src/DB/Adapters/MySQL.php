<?php

declare(strict_types=1);

namespace Difra\DB\Adapters;

/**
 * MySQL Adapter
 * Class MySQL
 * @package Difra\DB
 */
class MySQL extends Common
{
    /**
     * @inherit
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('pdo_mysql');
    }

    /**
     * @inherit
     */
    protected function getConnectionString(): string
    {
        return "{$this->config['type']}:host={$this->config['hostname']};dbname={$this->config['database']};charset=utf8";
    }
}
