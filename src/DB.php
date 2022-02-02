<?php

declare(strict_types=1);

namespace Difra;

use Difra\DB\Adapters\MySQL;
use Difra\DB\Adapters\Sqlite;

/**
 * Factory for DB
 * Class DB
 * @package Difra
 */
class DB
{
    /** @var array Configuration instances */
    private static array $adapters = [];

    /**
     * @param string $instance
     * @return MySQL|Sqlite
     * @throws \Difra\DB\Exception
     */
    public static function getInstance(string $instance = 'default'): Sqlite|MySQL
    {
        if (isset(self::$adapters[$instance])) {
            // TODO: ping db
            return self::$adapters[$instance];
        }

        $cfg = Config::getInstance()->get('db');
        if (empty($cfg) || empty($cfg[$instance])) {
            throw new \Difra\DB\Exception("DB configuration '$instance' is not available");
        }
        $type = strtolower($cfg[$instance]['type'] ?? 'none');
        return match ($type) {
            'mysql' => self::$adapters[$instance] = new MySQL($cfg[$instance]),
            'sqlite' => self::$adapters[$instance] = new Sqlite($cfg[$instance]),
            default => throw new \Difra\DB\Exception("PDO adapter not found for '{$type}'"),
        };
    }

    /**
     * Create set string from array keys
     * Example:
     * ['key1' => 'value1', 'key2' => 'value2'] converts to "`key1`=:key1,`key2`=:key2"
     * Warning: keys are not escaped!
     *
     * @param $array
     * @return string
     */
    public static function getSetKeys($array) : string
    {
        $set = [];
        foreach ($array as $key => $value) {
            $set[] = "`$key`=:$key";
        }
        return implode(',', $set);
    }
}
