<?php

namespace Presta;

class Cache {
    
    /**
     * @var Cache
     */
    protected static $instance;

    /**
     * @var array Store local cache
     */
    protected static $local = array();
    
    /**
     * @return Cache
     */
    public static function getInstance() {
        if (!self::$instance) {
            $caching_system = '\\Presta\\Cache\\Adapter\\' . _PS_CACHING_SYSTEM_;
            self::$instance = new $caching_system();
        }
        return self::$instance;
    }

    /**
     * Unit testing purpose only
     * @param $test_instance Cache
     */
    public static function setInstanceForTesting($test_instance) {
        self::$instance = $test_instance;
    }

    /**
     * Unit testing purpose only
     */
    public static function deleteTestingInstance() {
        self::$instance = null;
    }
    
    public static function store($key, $value) {
        // PHP is not efficient at storing array
        // Better delete the whole cache if there are
        // more than 1000 elements in the array
        if (count(self::$local) > 1000) {
            self::$local = array();
        }
        self::$local[$key] = $value;
    }

    public static function retrieve($key) {
        return isset(self::$local[$key]) ? self::$local[$key] : null;
    }

    public static function retrieveAll() {
        return self::$local;
    }

    public static function isStored($key) {
        return isset(self::$local[$key]);
    }

    public static function clean($key) {
        if (strpos($key, '*') !== false) {
            $regexp = str_replace('\\*', '.*', preg_quote($key, '#'));
            foreach (array_keys(self::$local) as $key)
                if (preg_match('#^' . $regexp . '$#', $key))
                    unset(self::$local[$key]);
        } else
            unset(self::$local[$key]);
    }

}