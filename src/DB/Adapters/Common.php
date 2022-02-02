<?php

declare(strict_types=1);

namespace Difra\DB\Adapters;

use Difra\DB\Exception;
use Difra\Debugger;

/**
 * Abstract Db adapter
 * Class Common
 * @package Difra\DB
 */
abstract class Common
{
    /** @var \PDO|null */
    public ?\PDO $pdo = null;
    /** @var int */
    public int $queries = 0;
    /** @var array|null */
    protected ?array $config = null;
    /** @var bool */
    protected ?bool $connected = null;
    /** @var string|null */
    protected ?string $error = null;
    /** @var bool */
    protected bool $transaction = false;

    /**
     * Detect if this adapter is usable
     * @return bool
     * @throws \Difra\DB\Exception
     */
    public static function isAvailable(): bool
    {
        throw new Exception(get_called_class() . '::isAvailable() is not defined');
    }

    /**
     * Returns PDO connection string ($dsn parameter for constructor)
     * @return string
     */
    abstract protected function getConnectionString(): string;

    /**
     * Constructor
     * @param array $conf
     * @throws \Difra\DB\Exception
     */
    public function __construct(array $conf)
    {
        if (!static::isAvailable()) {
            throw new Exception("PDO adapter is not usable: {$conf['type']}");
        }
        $this->config = $conf;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->transaction) {
            $this->rollBack();
        }
    }

    /**
     * @param $query
     * @return ?\PDOStatement
     * @throws \Difra\DB\Exception
     */
    public function prepare($query): ?\PDOStatement
    {
        $this->connect();
        return $this->pdo->prepare($query) ?: null;
    }

    /**
     * Query database
     * @param string $query
     * @param array $parameters
     * @throws \Difra\DB\Exception
     */
    public function query(string $query, array $parameters = [])
    {
        Debugger::prepareDBLine();
        $sth = $this->prepare($query);
        $sth->execute($parameters);
        Debugger::addDBLine('DB', $query);
        $this->lastAffectedRows = $sth->rowCount();
        $this->queries++;
    }

    /**
     * Query database
     * @param string $query
     * @param array $parametersSet
     * @throws \Difra\DB\Exception
     */
    public function multiQuery(string $query, array $parametersSet = [])
    {
        Debugger::prepareDBLine();
        $sth = $this->prepare($query);
        $this->lastAffectedRows = 0;
        foreach ($parametersSet as $parameters) {
            $sth->execute($parameters);
            $this->lastAffectedRows += $sth->rowCount();
        }
        Debugger::addDBLine('DB', 'multiQuery ' . $query); // todo
    }

    /**
     * Connect to database
     * @throws \Difra\DB\Exception
     * @return void
     */
    protected function connect()
    {
        if ($this->connected === true) {
            return;
//        } elseif ($this->connected === false) {
//            throw new Exception('Database connection is not available');
        }
        $this->connected = false;
        try {
            $this->pdo = new \PDO(
                $this->getConnectionString(),
                !empty($this->config['username']) ? $this->config['username'] : null,
                !empty($this->config['password']) ? $this->config['password'] : null,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );
        } catch (\Exception $ex) {
            throw new Exception(
                'Database connection failed' . (Debugger::isEnabled() ? ' (' . $ex->getMessage() . ')' : '')
            );
        }
        $this->connected = true;
    }

    /**
     * Escape string(s)
     * @param array|string $data
     * @param bool $noQuotes
     * @return string|array
     * @throws \Difra\DB\Exception
     */
    public function escape(array|string $data, bool $noQuotes = false): array|string
    {
        $this->connect();
        if (!is_array($data)) {
            $esc = $this->pdo->quote((string)$data);
            if ($noQuotes) {
                $escLength = strlen($esc);
                if ($esc[0] == '\'' && $esc[$escLength - 1] == '\'' && $escLength > 1) {
                    return substr($esc, 1, $escLength - 2);
                }
            }
            return $esc;
        }
        foreach ($data as $key => $value) {
            $data[$key] = $this->escape($value);
        }
        return $data;
    }

    /**
     * Test connection to database server
     * @return bool
     */
    public function isConnected(): bool
    {
        try {
            $this->connect();
        } catch (\Exception) {
            return false;
        }
        return (bool)$this->connected;
    }

    /**
     * Fetch data from database
     * @param string $query
     * @param array $parameters
     * @return array
     * @throws \Difra\DB\Exception
     */
    public function fetch(string $query, array $parameters = []): array
    {
        Debugger::prepareDBLine();
        $sth = $this->prepare($query);
        $sth->execute($parameters);
        $result = $sth->fetchAll();
        Debugger::addDBLine('DB', $query);
        $this->queries++;
        return $result;
    }

    /**
     * Fetch single row
     * @param string $query
     * @param array $parameters
     * @return array|null
     * @throws \Difra\DB\Exception
     */
    public function fetchRow(string $query, array $parameters = []): ?array
    {
        Debugger::prepareDBLine();
        $sth = $this->prepare($query);
        $sth->execute($parameters);
        $result = $sth->fetch();
        Debugger::addDBLine('DB', $query);
        return $result ?: null;
    }

    /**
     * Fetch single column
     * @param string $query
     * @param array $parameters
     * @param int $columnNumber
     * @return array
     * @throws \Difra\DB\Exception
     */
    public function fetchColumn(string $query, array $parameters = [], int $columnNumber = 0): array
    {
        Debugger::prepareDBLine();
        $sth = $this->prepare($query);
        $sth->execute($parameters);

        $data = [];
        while (($row = $sth->fetchColumn($columnNumber)) !== false) {
            $data[] = $row;
        }
        Debugger::addDBLine('DB', $query);
        return $data;
    }

    /**
     * Fetch single cell
     * @param string $query
     * @param array $parameters
     * @return mixed
     * @throws \Difra\DB\Exception
     */
    public function fetchOne(string $query, array $parameters = []): mixed
    {
        $data = $this->fetchRow($query, $parameters);
        return !empty($data) ? reset($data) : null;
    }

    /**
     * Get last inserted row id
     * @return int
     */
    public function getLastId(): int
    {
        return intval($this->pdo->lastInsertId());
    }

    /** @var ?int Last affected rows number */
    private ?int $lastAffectedRows = null;

    /**
     * Get number of affected rows
     * @return int|null
     */
    public function getAffectedRows(): ?int
    {
        return $this->lastAffectedRows;
    }

    /**
     * Start transaction
     * @return bool
     * @throws \Difra\DB\Exception
     */
    public function beginTransaction(): bool
    {
        $this->connect();
        if (Debugger::isEnabled()) {
            $this->transaction = microtime(true);
            Debugger::addDBLine('DB', 'Transaction start');
        } else {
            $this->transaction = true;
        }
        return $this->pdo->beginTransaction();
    }

    /**
     * Roll back transaction
     * @return bool
     */
    public function rollBack(): bool
    {
        $this->transaction = false;
        $result = $this->pdo->rollBack();
        if (Debugger::isEnabled()) {
            Debugger::addDBLine('DB', 'Transaction rolled back');
        }
        return $result;
    }

    /**
     * Commit transaction
     * @return bool
     */
    public function commit(): bool
    {
        $this->transaction = false;
        $result = $this->pdo->commit();
        if (Debugger::isEnabled()) {
            Debugger::addDBLine('DB', 'Transaction committed in ' . (number_format((microtime(true) - $this->transaction) * 1000, 1)) . ' ms');
        }
        return $result;
    }

    /**
     * Get database name
     * @return string|null
     */
    public function getDatabase(): ?string
    {
        return $this->config['database'] ?? null;
    }
}
