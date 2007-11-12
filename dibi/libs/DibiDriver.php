<?php

/**
 * dibi - tiny'n'smart database abstraction layer
 * ----------------------------------------------
 *
 * Copyright (c) 2005, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the "dibi license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://php7.org/dibi/
 *
 * @copyright  Copyright (c) 2005, 2007 David Grudl
 * @license    http://php7.org/dibi/license  dibi license
 * @link       http://php7.org/dibi/
 * @package    dibi
 */



/**
 * dibi Common Driver
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2005, 2007 David Grudl
 * @package    dibi
 * @version    $Revision$ $Date$
 */
abstract class DibiDriver extends NObject
{
    /**
     * Current connection configuration
     * @var array
     */
    private $config;

    /**
     * Connection resource
     * @var resource
     */
    private $connection;

    /**
     * Describes how convert some datatypes to SQL command
     * @var array
     */
    public $formats = array(
        'TRUE'     => "1",             // boolean true
        'FALSE'    => "0",             // boolean false
        'date'     => "'Y-m-d'",       // format used by date()
        'datetime' => "'Y-m-d H:i:s'", // format used by date()
    );



    /**
     * Creates object and (optionally) connects to a database
     *
     * @param array  connect configuration
     * @throws DibiException
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        if (empty($config['lazy'])) {
            $this->connect();
        }
    }



    /**
     * Automatically frees the resources allocated for this result set
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }



    /**
     * Connects to a database
     *
     * @return void
     */
    final public function connect()
    {
        $this->connection = $this->doConnect();
        dibi::notify('connected');
    }



    /**
     * Disconnects from a database
     *
     * @return void
     */
    final public function disconnect()
    {
        $this->doDisconnect();
        $this->connection = NULL;
        dibi::notify('disconnected');
    }



    /**
     * Returns configuration variable. If no $key is passed, returns the entire array.
     *
     * @see DibiDriver::__construct
     * @param string
     * @param mixed  default value to use if key not found
     * @return mixed
     */
    final public function getConfig($key = NULL, $default = NULL)
    {
        if ($key === NULL) {
            return $this->config;

        } elseif (isset($this->config[$key])) {
            return $this->config[$key];

        } else {
            return $default;
        }
    }



    /**
     * Returns the connection resource
     *
     * @return resource
     */
    final public function getConnection()
    {
        if ($this->connection === NULL) {
            $this->connect();
        }

        return $this->connection;
    }



    /**
     * Generates (translates) and executes SQL query
     *
     * @param  array|mixed    one or more arguments
     * @return DibiResult     Result set object (if any)
     * @throws DibiException
     */
    final public function query($args)
    {
        if (!is_array($args)) $args = func_get_args();

        $trans = new DibiTranslator($this);
        if ($trans->translate($args)) {
            return $this->nativeQuery($trans->sql);
        } else {
            throw new DibiException('SQL translate error: ' . $trans->sql);
        }
    }



    /**
     * Generates and prints SQL query
     *
     * @param  array|mixed  one or more arguments
     * @return bool
     */
    final public function test($args)
    {
        if (!is_array($args)) $args = func_get_args();

        $trans = new DibiTranslator($this);
        $ok = $trans->translate($args);
        dibi::dump($trans->sql);
        return $ok;
    }



    /**
     * Executes the SQL query
     *
     * @param string          SQL statement.
     * @return DibiResult     Result set object (if any)
     * @throws DibiException
     */
    final public function nativeQuery($sql)
    {
        dibi::notify('beforeQuery', $this, $sql);
        $res = $this->doQuery($sql);
        dibi::notify('afterQuery', $this, $res);
        // backward compatibility - will be removed!
        return $res instanceof DibiResult ? $res : TRUE;
    }



    /**
     * Apply configuration alias or default values
     *
     * @param array  connect configuration
     * @param string key
     * @param string alias key
     * @return void
     */
    protected static function alias(&$config, $key, $alias=NULL)
    {
        if (isset($config[$key])) return;

        if ($alias !== NULL && isset($config[$alias])) {
            $config[$key] = $config[$alias];
            unset($config[$alias]);
        } else {
            $config[$key] = NULL;
        }
    }



    /**
     * Internal: Connects to a database
     *
     * @throws DibiException
     * @return resource
     */
    abstract protected function doConnect();



    /**
     * Internal: Disconnects from a database
     *
     * @throws DibiException
     * @return void
     */
    abstract protected function doDisconnect();



    /**
     * Internal: Executes the SQL query
     *
     * @param string       SQL statement.
     * @return DibiResult  Result set object
     * @throws DibiDatabaseException
     */
    abstract protected function doQuery($sql);



    /**
     * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query
     *
     * @return int       number of rows or FALSE on error
     */
    abstract public function affectedRows();



    /**
     * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query
     *
     * @return int|FALSE  int on success or FALSE on failure
     */
    abstract public function insertId();



    /**
     * Begins a transaction (if supported).
     * @return void
     */
    abstract public function begin();



    /**
     * Commits statements in a transaction.
     * @return void
     */
    abstract public function commit();



    /**
     * Rollback changes in a transaction.
     * @return void
     */
    abstract public function rollback();



    /**
     * Returns last error
     * @deprecated
     */
    public function errorInfo()
    {
        throw new BadMethodCallException(__METHOD__ . ' has been deprecated');
    }



    /**
     * Escapes the string
     *
     * @param string     unescaped string
     * @param bool       quote string?
     * @return string    escaped and optionally quoted string
     */
    abstract public function escape($value, $appendQuotes = TRUE);



    /**
     * Delimites identifier (table's or column's name, etc.)
     *
     * @param string     identifier
     * @return string    delimited identifier
     */
    abstract public function delimite($value);



    /**
     * Gets a information of the current database.
     *
     * @return DibiReflection
     */
    abstract public function getDibiReflection();



    /**
     * Injects LIMIT/OFFSET to the SQL query
     *
     * @param string &$sql  The SQL query that will be modified.
     * @param int $limit
     * @param int $offset
     * @return void
     */
    abstract public function applyLimit(&$sql, $limit, $offset = 0);


} // class DibiDriver