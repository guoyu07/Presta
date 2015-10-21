<?php

/*
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2015 PrestaShop SA
 *  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

namespace Presta\Db;

if (file_exists(_PS_ROOT_DIR_ . '/config/settings.inc.php')) {
    include_once(_PS_ROOT_DIR_ . '/config/settings.inc.php');
}

/**
 * Class DbCore
 */
abstract class DbCore {

    /** @var int Constant used by insert() method */
    const INSERT = 1;

    /** @var int Constant used by insert() method */
    const INSERT_IGNORE = 2;

    /** @var int Constant used by insert() method */
    const REPLACE = 3;

    /** @var int Constant used by insert() method */
    const ON_DUPLICATE_KEY = 4;

    /** @var string Server (eg. localhost) */
    protected $server;

    /**  @var string Database user (eg. root) */
    protected $user;

    /** @var string Database password (eg. can be empty !) */
    protected $password;

    /** @var string Database name */
    protected $database;

    /** @var bool */
    protected $is_cache_enabled;

    /** @var PDO|mysqli|resource Resource link */
    protected $link;

    /** @var PDOStatement|mysqli_result|resource|bool SQL cached result */
    protected $result;

    /**
     * Store last executed query
     *
     * @var string
     */
    protected $last_query;

    /**
     * Store hash of the last executed query
     *
     * @var string
     */
    protected $last_query_hash;

    /**
     * Last cached query
     *
     * @var string
     */
    protected $last_cached;

    /**
     * Opens a database connection
     *
     * @return PDO|mysqli|resource
     */
    abstract public function connect();

    /**
     * Closes database connection
     */
    abstract public function disconnect();

    /**
     * Execute a query and get result resource
     *
     * @param string $sql
     * @return PDOStatement|mysqli_result|resource|bool
     */
    abstract protected function _query($sql);

    /**
     * Get number of rows in a result
     *
     * @param mixed $result
     * @return int
     */
    abstract protected function _numRows($result);

    /**
     * Get the ID generated from the previous INSERT operation
     *
     * @return int|string
     */
    abstract public function Insert_ID();

    /**
     * Get number of affected rows in previous database operation
     *
     * @return int
     */
    abstract public function Affected_Rows();

    /**
     * Get next row for a query which does not return an array
     *
     * @param PDOStatement|mysqli_result|resource|bool $result
     * @return array|object|false|null
     */
    abstract public function nextRow($result = false);

    /**
     * Get all rows for a query which return an array
     *
     * @param PDOStatement|mysqli_result|resource|bool|null $result
     * @return array
     */
    abstract protected function getAll($result = false);

    /**
     * Get database version
     *
     * @return string
     */
    abstract public function getVersion();

    /**
     * Protect string against SQL injections
     *
     * @param string $str
     * @return string
     */
    abstract public function _escape($str);

    /**
     * Returns the text of the error message from previous database operation
     *
     * @return string
     */
    abstract public function getMsgError();

    /**
     * Returns the number of the error from previous database operation
     *
     * @return int
     */
    abstract public function getNumberError();

    /**
     * Sets the current active database on the server that's associated with the specified link identifier.
     * Do not remove, useful for some modules.
     *
     * @param string $db_name
     * @return bool|int
     */
    abstract public function set_db($db_name);

    /**
     * Selects best table engine.
     *
     * @return string
     */
    abstract public function getBestEngine();

    /**
     * Instantiates a database connection
     *
     * @param string $server Server address
     * @param string $user User login
     * @param string $password User password
     * @param string $database Database name
     * @param bool $connect If false, don't connect in constructor (since 1.5.0.1)
     */
    public function __construct($server, $user, $password, $database, $connect = true) {
        $this->server = $server;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->is_cache_enabled = (defined('_PS_CACHE_ENABLED_')) ? _PS_CACHE_ENABLED_ : false;

        if (!defined('_PS_DEBUG_SQL_')) {
            define('_PS_DEBUG_SQL_', false);
        }

        if ($connect) {
            $this->connect();
        }
    }

    /**
     * Disable the use of the cache
     *
     */
    public function disableCache() {
        $this->is_cache_enabled = false;
    }

    /**
     * Enable & flush the cache
     *
     */
    public function enableCache() {
        $this->is_cache_enabled = true;
        \Presta\Cache::getInstance()->flush();
    }

    /**
     * Closes connection to database
     */
    public function __destruct() {
        if ($this->link) {
            $this->disconnect();
        }
    }

    /**
     * Executes SQL query based on selected type
     *
     * @deprecated 1.5.0.1 Use insert() or update() method instead.
     * @param string $table
     * @param array $data
     * @param string $type (INSERT, INSERT IGNORE, REPLACE, UPDATE).
     * @param string $where
     * @param int $limit
     * @param bool $use_cache
     * @param bool $use_null
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    public function autoExecute($table, $data, $type, $where = '', $limit = 0, $use_cache = true, $use_null = false) {
        $type = strtoupper($type);
        switch ($type) {
            case 'INSERT' :
                return $this->insert($table, $data, $use_null, $use_cache, self::INSERT, false);

            case 'INSERT IGNORE' :
                return $this->insert($table, $data, $use_null, $use_cache, self::INSERT_IGNORE, false);

            case 'REPLACE' :
                return $this->insert($table, $data, $use_null, $use_cache, self::REPLACE, false);

            case 'UPDATE' :
                return $this->update($table, $data, $where, $limit, $use_null, $use_cache, false);

            default :
                throw new Exception('Wrong argument (miss type) in Db::autoExecute()');
        }
    }

    /**
     * Filter SQL query within a blacklist
     *
     * @param string $table Table where insert/update data
     * @param array $values Data to insert/update
     * @param string $type INSERT or UPDATE
     * @param string $where WHERE clause, only for UPDATE (optional)
     * @param int $limit LIMIT clause (optional)
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    public function autoExecuteWithNullValues($table, $values, $type, $where = '', $limit = 0) {
        return $this->autoExecute($table, $values, $type, $where, $limit, 0, true);
    }

    /**
     * Execute a query and get result resource
     *
     * @param string|Query $sql
     * @return bool|mysqli_result|PDOStatement|resource
     * @throws PrestaShopDatabaseException
     */
    public function query($sql) {
        if ($sql instanceof Query) {
            $sql = $sql->build();
        }

        $this->result = $this->_query($sql);

        if (!$this->result && $this->getNumberError() == 2006) {
            if ($this->connect()) {
                $this->result = $this->_query($sql);
            }
        }

        if (_PS_DEBUG_SQL_) {
            $this->displayError($sql);
        }

        return $this->result;
    }

    /**
     * Executes an INSERT query
     *
     * @param string $table Table name without prefix
     * @param array $data Data to insert as associative array. If $data is a list of arrays, multiple insert will be done
     * @param bool $null_values If we want to use NULL values instead of empty quotes
     * @param bool $use_cache
     * @param int $type Must be Db::INSERT or Db::INSERT_IGNORE or Db::REPLACE
     * @param bool $add_prefix Add or not _DB_PREFIX_ before table name
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    public function insert($table, $data, $null_values = false, $use_cache = true, $type = self::INSERT, $add_prefix = true) {
        if (!$data && !$null_values) {
            return true;
        }

        if ($add_prefix) {
            $table = _DB_PREFIX_ . $table;
        }

        if ($type == self::INSERT) {
            $insert_keyword = 'INSERT';
        } elseif ($type == self::INSERT_IGNORE) {
            $insert_keyword = 'INSERT IGNORE';
        } elseif ($type == self::REPLACE) {
            $insert_keyword = 'REPLACE';
        } elseif ($type == self::ON_DUPLICATE_KEY) {
            $insert_keyword = 'INSERT';
        } else {
            throw new Exception('Bad keyword, must be Db::INSERT or Db::INSERT_IGNORE or Db::REPLACE');
        }

        // Check if $data is a list of row
        $current = current($data);
        if (!is_array($current) || isset($current['type'])) {
            $data = array($data);
        }

        $keys = array();
        $values_stringified = array();
        $first_loop = true;
        $duplicate_key_stringified = '';
        foreach ($data as $row_data) {
            $values = array();
            foreach ($row_data as $key => $value) {
                if (!$first_loop) {
                    // Check if row array mapping are the same
                    if (!in_array("`$key`", $keys)) {
                        throw new Exception('Keys form $data subarray don\'t match');
                    }

                    if ($duplicate_key_stringified != '') {
                        throw new Exception('On duplicate key cannot be used on insert with more than 1 VALUE group');
                    }
                } else {
                    $keys[] = '`' . bqSQL($key) . '`';
                }

                if (!is_array($value)) {
                    $value = array('type' => 'text', 'value' => $value);
                }
                if ($value['type'] == 'sql') {
                    $values[] = $string_value = $value['value'];
                } else {
                    $values[] = $string_value = $null_values && ($value['value'] === '' || is_null($value['value'])) ? 'NULL' : "'{$value['value']}'";
                }

                if ($type == self::ON_DUPLICATE_KEY) {
                    $duplicate_key_stringified .= '`' . bqSQL($key) . '` = ' . $string_value . ',';
                }
            }
            $first_loop = false;
            $values_stringified[] = '(' . implode(', ', $values) . ')';
        }
        $keys_stringified = implode(', ', $keys);

        $sql = $insert_keyword . ' INTO `' . $table . '` (' . $keys_stringified . ') VALUES ' . implode(', ', $values_stringified);
        if ($type == self::ON_DUPLICATE_KEY) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . substr($duplicate_key_stringified, 0, -1);
        }

        return (bool) $this->q($sql, $use_cache);
    }

    /**
     * Executes an UPDATE query
     *
     * @param string $table Table name without prefix
     * @param array $data Data to insert as associative array. If $data is a list of arrays, multiple insert will be done
     * @param string $where WHERE condition
     * @param int $limit
     * @param bool $null_values If we want to use NULL values instead of empty quotes
     * @param bool $use_cache
     * @param bool $add_prefix Add or not _DB_PREFIX_ before table name
     * @return bool
     */
    public function update($table, $data, $where = '', $limit = 0, $null_values = false, $use_cache = true, $add_prefix = true) {
        if (!$data) {
            return true;
        }

        if ($add_prefix) {
            $table = _DB_PREFIX_ . $table;
        }

        $sql = 'UPDATE `' . bqSQL($table) . '` SET ';
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $value = array('type' => 'text', 'value' => $value);
            }
            if ($value['type'] == 'sql') {
                $sql .= '`' . bqSQL($key) . "` = {$value['value']},";
            } else {
                $sql .= ($null_values && ($value['value'] === '' || is_null($value['value']))) ? '`' . bqSQL($key) . '` = NULL,' : '`' . bqSQL($key) . "` = '{$value['value']}',";
            }
        }

        $sql = rtrim($sql, ',');
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        if ($limit) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return (bool) $this->q($sql, $use_cache);
    }

    /**
     * Executes a DELETE query
     *
     * @param string $table Name of the table to delete
     * @param string $where WHERE clause on query
     * @param int $limit Number max of rows to delete
     * @param bool $use_cache Use cache or not
     * @param bool $add_prefix Add or not _DB_PREFIX_ before table name
     * @return bool
     */
    public function delete($table, $where = '', $limit = 0, $use_cache = true, $add_prefix = true) {
        if (_DB_PREFIX_ && !preg_match('#^' . _DB_PREFIX_ . '#i', $table) && $add_prefix) {
            $table = _DB_PREFIX_ . $table;
        }

        $this->result = false;
        $sql = 'DELETE FROM `' . bqSQL($table) . '`' . ($where ? ' WHERE ' . $where : '') . ($limit ? ' LIMIT ' . (int) $limit : '');
        $res = $this->query($sql);
        if ($use_cache && $this->is_cache_enabled) {
            \Presta\Cache::getInstance()->deleteQuery($sql);
        }

        return (bool) $res;
    }

    /**
     * Executes a query
     *
     * @param string|Query $sql
     * @param bool $use_cache
     * @return bool
     */
    public function execute($sql, $use_cache = true) {
        if ($sql instanceof Query) {
            $sql = $sql->build();
        }

        $this->result = $this->query($sql);
        if ($use_cache && $this->is_cache_enabled) {
            \Presta\Cache::getInstance()->deleteQuery($sql);
        }

        return (bool) $this->result;
    }

    /**
     * Executes return the result of $sql as array
     *
     * @param string|Query $sql Query to execute
     * @param bool $array Return an array instead of a result object (deprecated since 1.5.0.1, use query method instead)
     * @param bool $use_cache
     * @return array|false|null|mysqli_result|PDOStatement|resource
     * @throws PrestaShopDatabaseException
     */
    public function executeS($sql, $array = true, $use_cache = true) {
        if ($sql instanceof Query) {
            $sql = $sql->build();
        }

        $this->result = false;
        $this->last_query = $sql;

        if ($use_cache && $this->is_cache_enabled && $array) {
            $this->last_query_hash = \Presta\Tools::encryptIV($sql);
            if (($result = \Presta\Cache::getInstance()->get($this->last_query_hash)) !== false) {
                $this->last_cached = true;
                return $result;
            }
        }

        // This method must be used only with queries which display results
        if (!preg_match('#^\s*\(?\s*(select|show|explain|describe|desc)\s#i', $sql)) {
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                throw new Exception('Db->executeS() must be used only with select, show, explain or describe queries');
            }
            return $this->execute($sql, $use_cache);
        }

        $this->result = $this->query($sql);

        if (!$this->result) {
            $result = false;
        } else {
            if (!$array) {
                $use_cache = false;
                $result = $this->result;
            } else {
                $result = $this->getAll($this->result);
            }
        }

        $this->last_cached = false;
        if ($use_cache && $this->is_cache_enabled && $array) {
            \Presta\Cache::getInstance()->setQuery($sql, $result);
        }

        return $result;
    }

    /**
     * Returns an associative array containing the first row of the query
     * This function automatically adds "LIMIT 1" to the query
     *
     * @param string|Query $sql the select query (without "LIMIT 1")
     * @param bool $use_cache Find it in cache first
     * @return array|bool|object|null
     */
    public function getRow($sql, $use_cache = true) {
        if ($sql instanceof Query) {
            $sql = $sql->build();
        }

        $sql = rtrim($sql, " \t\n\r\0\x0B;") . ' LIMIT 1';
        $this->result = false;
        $this->last_query = $sql;

        if ($use_cache && $this->is_cache_enabled) {
            $this->last_query_hash = \Presta\Tools::encryptIV($sql);
            if (($result = \Presta\Cache::getInstance()->get($this->last_query_hash)) !== false) {
                $this->last_cached = true;
                return $result;
            }
        }

        $this->result = $this->query($sql);
        if (!$this->result) {
            $result = false;
        } else {
            $result = $this->nextRow($this->result);
        }

        $this->last_cached = false;

        if (is_null($result)) {
            $result = false;
        }

        if ($use_cache && $this->is_cache_enabled) {
            \Presta\Cache::getInstance()->setQuery($sql, $result);
        }

        return $result;
    }

    /**
     * Returns a value from the first row, first column of a SELECT query
     *
     * @param string|Query $sql
     * @param bool $use_cache
     * @return string|false|null
     */
    public function getValue($sql, $use_cache = true) {
        if ($sql instanceof Query) {
            $sql = $sql->build();
        }

        if (!$result = $this->getRow($sql, $use_cache)) {
            return false;
        }

        return array_shift($result);
    }

    /**
     * Get number of rows for last result
     *
     * @return int
     */
    public function numRows() {
        if (!$this->last_cached && $this->result) {
            $nrows = $this->_numRows($this->result);
            if ($this->is_cache_enabled) {
                \Presta\Cache::getInstance()->set($this->last_query_hash . '_nrows', $nrows);
            }
            return $nrows;
        } elseif ($this->is_cache_enabled && $this->last_cached) {
            return \Presta\Cache::getInstance()->get($this->last_query_hash . '_nrows');
        }
    }

    /**
     * Executes a query
     *
     * @param string|Query $sql
     * @param bool $use_cache
     * @return bool|mysqli_result|PDOStatement|resource
     * @throws PrestaShopDatabaseException
     */
    protected function q($sql, $use_cache = true) {
        if ($sql instanceof Query) {
            $sql = $sql->build();
        }

        $this->result = false;
        $result = $this->query($sql);
        if ($use_cache && $this->is_cache_enabled) {
            \Presta\Cache::getInstance()->deleteQuery($sql);
        }

        if (_PS_DEBUG_SQL_) {
            $this->displayError($sql);
        }

        return $result;
    }

    /**
     * Displays last SQL error
     *
     * @param string|bool $sql
     * @throws PrestaShopDatabaseException
     */
    public function displayError($sql = false) {
        global $webservice_call;

        $errno = $this->getNumberError();
        if (_PS_DEBUG_SQL_ && $errno && !defined('PS_INSTALLATION_IN_PROGRESS')) {
            if ($sql) {
                throw new Exception($this->getMsgError() . '<br /><br /><pre>' . $sql . '</pre>');
            }

            throw new Exception($this->getMsgError());
        }
    }

    /**
     * Sanitize data which will be injected into SQL query
     *
     * @param string $string SQL data which will be injected into SQL query
     * @param bool $html_ok Does data contain HTML code ? (optional)
     * @return string Sanitized data
     */
    public function escape($string, $html_ok = false, $bq_sql = false) {
        if (_PS_MAGIC_QUOTES_GPC_) {
            $string = stripslashes($string);
        }

        if (!is_numeric($string)) {
            $string = $this->_escape($string);

            if (!$html_ok) {
                $string = strip_tags(\Presta\Tools::nl2br($string));
            }

            if ($bq_sql === true) {
                $string = str_replace('`', '\`', $string);
            }
        }

        return $string;
    }

    /**
     * Get used link instance
     *
     * @return PDO|mysqli|resource Resource
     */
    public function getLink() {
        return $this->link;
    }

}
