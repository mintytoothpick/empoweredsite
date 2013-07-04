<?php
/**
 * Wrapper for Zend_Cache_Frontend.
 * It takes in count the cache.enable config setting and disables the cache when required.
 * Also chooses the best Backend to use.
 *
 * @author Leonel Quinteros
 *
 */

class Brigade_Cache_Adapter
{
    private $_cache;
    private $_enableCache;

    /**
     * Constructor
     * Retrieves configuration settings from Zend_Registry
     * Enables cache (or not)
     * Initialize.
     *
     * @author Leonel Quinteros
     *
     * @return void
     */
    public function __construct()
    {
        $config = Zend_Registry::get('configuration');
        $this->_enableCache = (bool) $config->cache->enable;

        $this->_initCache();
    }

    /**
     * Initializes a Zend_Cache object based on configuration.
     *
     * TODO: Check for Memcache service status and fallbacks to File cache.
     *
     * @author Leonel Quinteros
     *
     * @return void
     */
    private function _initCache()
    {
        $config = Zend_Registry::get('configuration');

        if($this->_enableCache)
        {
            // Config memcache
            $frontendOptions = array(
                'lifetime' => $config->cache->frontend->lifetime, // cache lifetime of 1 day
                'automatic_serialization' => true,
                'cache_id_prefix' => $config->cache->frontend->cache_id_prefix,
            );

            $backendOptions = array(
                'host' => $config->cache->backend->host,
                'port' => $config->cache->backend->port,
                'persistent' => true,
                'weight' => 1,
                'timeout' => 5,
                'retry_interval' => 15,
                'status' => true,
            );

            try
            {
                @$testConn = memcache_connect($config->cache->backend->host, $config->cache->backend->port);
                if(!$testConn)
                {
                    throw new Exception('Memcached connection failed');
                }

                // Getting a Zend_Cache_Core object
                $this->_cache = Zend_Cache::factory(
                                                'Core',
                                                'Memcached',
                                                $frontendOptions,
                                                $backendOptions
                );
            }
            catch(Exception $e)
            {
                $this->_enableCache = false;
            }
        }
    }


    /**
     * Wrapper function for Zend_Cache::load()
     * Retrieves an entry from cache.
     * Returns false if caching is disabled on configuration.
     *
     * @author Leonel Quinteros
     *
     * @param string $key
     *
     * @return Cached value
     */
    public function load($key)
    {
        if(!$this->_enableCache)
        {
            return false;
        }

        return $this->_cache->load($key);
    }


    /**
     * Wrapper function for Zend_Cache::test()
     * Checks if a key exists in cache.
     * Returns false if caching is disabled on configuration.
     *
     * @author Leonel Quinteros
     *
     * @param string $key
     *
     * @return bool
     */
    public function test($key)
    {
        if(!$this->_enableCache)
        {
            return false;
        }

        return $this->_cache->test($key);
    }


    /**
     * Wrapper function for Zend_Cache::save()
     * Puts an entry in cache.
     * Returns false and does nothing if caching is disabled on configuration.
     *
     * @author Leonel Quinteros
     *
     * @param string $key
     *
     * @return bool
     */
    public function save($value, $key)
    {
        if(!$this->_enableCache)
        {
            return false;
        }

        return $this->_cache->save($value, $key);
    }


    /**
     * Wrapper function for Zend_Cache::delete()
     * Removes an entry from cache.
     * Returns false and does nothing if caching is disabled on configuration.
     *
     * @author Leonel Quinteros
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete($key)
    {
        if(!$this->_enableCache)
        {
            return false;
        }

        return $this->_cache->delete($key);
    }
}
