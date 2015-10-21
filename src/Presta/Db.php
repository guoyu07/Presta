<?php

namespace Presta;

class Db {

    /** @var array List of DB instances */
    public static $instance = array();

    /** @var array List of server settings */
    public static $_servers = array();

    /** @var null Flag used to load slave servers only once.
     * See loadSlaveServers() method.
     */
    public static $_slave_servers_loaded = null;

    /**
     * Returns database object instance.
     *
     * @param bool $master Decides whether the connection to be returned by the master server or the slave server
     * @return Db Singleton instance of Db object
     */
    public static function getInstance($master = true) {
        static $id = 0;

        // This MUST not be declared with the class members because some defines (like _DB_SERVER_) may not exist yet (the constructor can be called directly with params)
        if (!self::$_servers) {
            self::$_servers = array(
                array('server' => _DB_SERVER_, 'user' => _DB_USER_, 'password' => _DB_PASSWD_, 'database' => _DB_NAME_), /* MySQL Master server */
            );
        }

        if (!$master) {
            self::loadSlaveServers();
        }

        $total_servers = count(self::$_servers);
        if ($master || $total_servers == 1) {
            $id_server = 0;
        } else {
            $id++;
            $id_server = ($total_servers > 2 && ($id % $total_servers) != 0) ? $id % $total_servers : 1;
        }

        if (!isset(self::$instance[$id_server])) {
            $class = self::getClass();
            self::$instance[$id_server] = new $class(
                    self::$_servers[$id_server]['server'], self::$_servers[$id_server]['user'], self::$_servers[$id_server]['password'], self::$_servers[$id_server]['database']
            );
        }

        return self::$instance[$id_server];
    }

    /**
     * @param $test_db Db
     * Unit testing purpose only
     */
    public static function setInstanceForTesting($test_db) {
        self::$instance[0] = $test_db;
    }

    /**
     * Unit testing purpose only
     */
    public static function deleteTestingInstance() {
        self::$instance = array();
    }

    /**
     * Loads configuration settings for slave servers if needed.
     */
    protected static function loadSlaveServers() {
        if (self::$_slave_servers_loaded !== null) {
            return;
        }

        // Add here your slave(s) server(s) in this file
        if (file_exists(_PS_ROOT_DIR_ . '/config/db_slave_server.inc.php')) {
            self::$_servers = array_merge(self::$_servers, require(_PS_ROOT_DIR_ . '/config/db_slave_server.inc.php'));
        }

        self::$_slave_servers_loaded = true;
    }

    /**
     * Returns the best child layer database class.
     *
     * @return string
     */
    public static function getClass() {
        $class = '\Presta\Db\MySQL';
        if (PHP_VERSION_ID >= 50200 && extension_loaded('pdo_mysql')) {
            $class = '\Presta\Db\PDO';
        } elseif (extension_loaded('mysqli')) {
            $class = '\Presta\Db\MySQLi';
        }

        return $class;
    }

    /**
     * Try a connection to the database
     *
     * @param string $server Server address
     * @param string $user Login for database connection
     * @param string $pwd Password for database connection
     * @param string $db Database name
     * @param bool $new_db_link
     * @param string|bool $engine
     * @param int $timeout
     * @return int Error code or 0 if connection was successful
     */
    public static function checkConnection($server, $user, $pwd, $db, $new_db_link = true, $engine = null, $timeout = 5) {
        return call_user_func_array(array(self::getClass(), 'tryToConnect'), array($server, $user, $pwd, $db, $new_db_link, $engine, $timeout));
    }

    /**
     * Try a connection to the database and set names to UTF-8
     *
     * @param string $server Server address
     * @param string $user Login for database connection
     * @param string $pwd Password for database connection
     * @return bool
     */
    public static function checkEncoding($server, $user, $pwd) {
        return call_user_func_array(array(self::getClass(), 'tryUTF8'), array($server, $user, $pwd));
    }

    /**
     * Try a connection to the database and check if at least one table with same prefix exists
     *
     * @param string $server Server address
     * @param string $user Login for database connection
     * @param string $pwd Password for database connection
     * @param string $db Database name
     * @param string $prefix Tables prefix
     * @return bool
     */
    public static function hasTableWithSamePrefix($server, $user, $pwd, $db, $prefix) {
        return call_user_func_array(array(self::getClass(), 'hasTableWithSamePrefix'), array($server, $user, $pwd, $db, $prefix));
    }

    /**
     * Tries to connect to the database and create a table (checking creation privileges)
     *
     * @param string $server
     * @param string $user
     * @param string $pwd
     * @param string $db
     * @param string $prefix
     * @param string|null $engine Table engine
     * @return bool|string True, false or error
     */
    public static function checkCreatePrivilege($server, $user, $pwd, $db, $prefix, $engine = null) {
        return call_user_func_array(array(self::getClass(), 'checkCreatePrivilege'), array($server, $user, $pwd, $db, $prefix, $engine));
    }

    /**
     * Checks if auto increment value and offset is 1
     *
     * @param string $server
     * @param string $user
     * @param string $pwd
     * @return bool
     */
    public static function checkAutoIncrement($server, $user, $pwd) {
        return call_user_func_array(array(self::getClass(), 'checkAutoIncrement'), array($server, $user, $pwd));
    }

    /**
     * Executes a query
     *
     * @deprecated 1.5.0.1
     * @param string|DbQuery $sql
     * @param bool $use_cache
     * @return array|bool|mysqli_result|PDOStatement|resource
     * @throws PrestaShopDatabaseException
     */
    public static function s($sql, $use_cache = true) {
        Tools::displayAsDeprecated();
        return self::getInstance()->executeS($sql, true, $use_cache);
    }

    /**
     * Executes a query
     *
     * @deprecated 1.5.0.1
     * @param $sql
     * @param int $use_cache
     * @return array|bool|mysqli_result|PDOStatement|resource
     */
    public static function ps($sql, $use_cache = 1) {
        Tools::displayAsDeprecated();
        $ret = self::s($sql, $use_cache);
        return $ret;
    }

    /**
     * Executes a query and kills process (dies)
     *
     * @deprecated 1.5.0.1
     * @param $sql
     * @param int $use_cache
     */
    public static function ds($sql, $use_cache = 1) {
        Tools::displayAsDeprecated();
        self::s($sql, $use_cache);
        die();
    }
}