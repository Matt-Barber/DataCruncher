<?php
/**
 * Database Handler
 *
 * @package DataCruncher
 * @subpackage Helpers
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
declare(strict_types=1);
namespace mfmbarber\DataCruncher\Helpers\Databases;

use mfmbarber\DataCruncher\Exceptions;
use mfmbarber\DataCruncher\Config\Validation;
use mfmbarber\DataCruncher\Helpers\Interfaces\DataInterface;

class Database implements DataInterface
{
    private const CONDITIONS = [
        'EQUALS' => '=',
        'GREATER' => '>',
        'LESS' => '<',
        'NOT' => 'NOT',
        'BETWEEN' => 'BETWEEN',
        'NOT_BETWEEN' => 'NOT BETWEEN',
        'EMPTY' => 'IS NULL',
        'NOT_EMPTY' => 'IS NOT NULL',
        'CONTAINS' => 'LIKE',
        'IN'    => 'IN'
    ];

    public $connection = null;
    public $dsn;

    private $username;
    private $password;
    private $table;

    private $query = null;
    private $headers = [];
    private $sortKey = null;

    /**
     * Sets the source for the DATABASE manipulator object.
     * At this stage it simply defines the state
     *
     * @param string    $db             The name of the database
     * @param array     $properties     A hash table defining specific properties
     *                                  This must contain username, password and table
     * @return void
    **/
    public function setSource(string $db, array $properties) : void
    {
        $type = $properties['type'] ?? 'mysql';
        $host = $properties['host'] ?? 'localhost';
        $this->setDSN($type, $db, $host);
        // This will always hold true - we can't assert this
        if (!isset($properties['table'])) {
            throw new \Exception('A table key must be passed in the properties array');
        }
        // Maybe the user doesn't have any credentials protection - potential on local
        // So let's assert, and flag this up - and if the assertions are switched off
        // let's use null coalscale.
        assert(
            isset($properties['username']) && is_string($properties['username']),
            "The key username must be set in the properties array to a string '' is valid"
        );
        assert(
            isset($properties['password']) && is_string($properties['password']),
            "The key password must be set in the properties array to a string '' is valid"
        );

        $this->username = $properties['username'] ?? '';
        $this->password = $properties['password'] ?? '';

        $this->setTable($properties['table']);
    }

    /**
     * A public setter method that allows us to change the table in the target DB
     * if required
     *
     * @param string    $table  The table to point to
     *
     * @return void
    **/
    public function setTable(string $table) : void
    {
        $this->table = $table;
    }

    /**
     * A getter method that returns the headers on the table
     * This will always force a refresh unless specifically told not to
     *
     * @param bool  $force  Should the headers be reset? Defaults to true
     *
     * @return array
    **/
    public function getHeaders(bool $force = true) : array
    {
        if ($force || $this->headers === []) {
            // TODO : fix and stuff
            // You wouldn't be using this on an empty table - so 'fuck it'
            // there isn't a cross compliant way of handling this across SQLLITE
            // mysql and postgres - I should probably write a wrapper or something
            $sql = "SELECT * FROM {$this->table} LIMIT 1";
            $desc = $this->connection->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
            $desc->execute();
            $this->headers = array_keys($desc->fetch(\PDO::FETCH_ASSOC));
        }
        return $this->headers;
    }


    /**
     * Given a field name, return the type for this field (loosely)
     *
     * @param string    $field  A field in the table
     *
     * @return string
    **/
    public function getFieldType(string $field) : string
    {
        $sql = "SELECT $field FROM {$this->table} LIMIT 1";
        $desc = $this->connection->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
        $desc->execute();
        return Validation::getType($desc->fetch(\PDO::FETCH_OBJ)->{$field});
    }


    /**
     * Create a DB connection and store it in the state of the object using PDO
     *
     * @param bool  $reconnect  Optionally force a reconnection if the connection is already open
     *                          i.e. changing DSN then reconnecting
     *
     * @return void
    **/
    public function open(bool $reconnect = false) : bool
    {
        if ($this->connection !== null && !$reconnect) {
            throw new \PDOException("Connection is already active");
        }
        try {
            $this->connection = new \PDO($this->dsn, $this->username, $this->password);
            return true;
        } catch (\PDOException $e) {
            throw new \PDOException(
                "Couldn't establish connection using {$this->dsn} is the server running?"
            );
        }
    }

    /**
     * Kill a connection object
     *
     * @return void
    **/
    public function close() : void
    {
        if ($this->connection === null) {
            throw new \PDOException("No connection active");
        }
        $this->connection = null;
    }

    /**
     * Reset the result
     *
     * @return void
    **/
    public function reset() : void
    {
      throw new \Exception("Not implemented");
    }

    /**
     * Sort the data by a given key for each query
     * @param string    $key    A valid key in the table
    **/
    public function sort(string $key) : ?array
    {
        if (!in_array($key, $this->getHeaders())) {
            throw new \PDOException("$key is not a header in the table");
        }
        $this->sortKey = $key;
        return null;
    }

    /**
     * Execute a PDO prepared statement and store the result in the state attribute
     * _query -> think of this as our _fp
     *
     * @param array     $fields     The return fields for the request
     * @param string    $where      The name of the field to query
     * @param string    $condition  The condition to execute
     * @param mixed     $value      The value for the condition
     *
     * @return void
    **/
    public function query($fields, $where = null, $condition = null, $value = null)
    {
        $sql = 'SELECT ' . implode(', ', array_keys($fields)) . " FROM {$this->table}";
        // TODO a fuck tonne of parsing of where  condition and value based on constant CONDITIOND
        if (isset($where)) {
            $condition = self::CONDITIONS[$condition];
            switch ($condition) {
                case 'LIKE':
                    $value = "%$value%";
                break;
                // TODO Fix this
                case 'IN':
                    $value = '('. implode(',', $value) .')';
                break;
            }
            $sql .= " WHERE $where $condition";
            if (isset($value)) {
                $sql .= " :value";
            }
        }
        if ($this->sortKey !== null) {
            $sql .= " ORDER BY $this->sortKey";
        }
        $this->query = $this->connection->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
        $this->query->execute((isset($value)) ? [':value' => $value] : null);

    }

    /**
     * Returns the connection status for the object
     *
     * @return mixed
    **/
    public function getSourceName() : string
    {
        return $this->dsn;
    }

    /**
     * Return the next row from the result
     *
     * @return array
    **/
    public function getNextDataRow() : \Generator
    {
        if ($this->query !== null && ($line = $this->query->fetch(\PDO::FETCH_ASSOC))) {
            yield $line;
        }
        return [];
    }

    /**
     * Insert a data row into the target table
     *
     * @param array $row
    **/
    public function writeDataRow(array $row) : bool
    {
        // Create an array of substitutions
        $subs = array_map(
            function($item) {
                return ":$item";
            },
            array_keys($row)
        );
        // Create the prepared insert phrase
        $sql = "INSERT INTO {$this->table} (" .
            implode(', ', array_keys($row)) .
        ") VALUES (" .
            implode(', ', $subs) .
        ")";
        $insert = $this->connection->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
        // execute combining our values with our substitutions
        // If this fails it'll through an SQL exception
        $insert->execute(array_combine($subs, array_values($row)));
        return true;
    }

    /**
     * Sets the DSN for the PDO object held in connection
     *
     * @param string    $type   The DB type, i.e. mysql
     * @param string    $db     The name of the target DB
     * @param string    $host   The location of the DB
     *
    **/
    private function setDSN(string $type, string $db, string $host) : void
    {
        $this->dsn = "$type:dbname=$db;host=$host";
    }


}
