<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Interface will be autoloaded from vendor/ directory by Composer

/**
 * WordPress-compatible persistent data store for Dropbox OAuth
 * Uses WordPress transients instead of PHP sessions for better compatibility
 */
class Uni_Cpo_WordPress_Persistent_Data_Store implements \Kunnu\Dropbox\Store\PersistentDataStoreInterface
{
    /**
     * Prefix for WordPress transients
     */
    const TRANSIENT_PREFIX = 'uni_cpo_dropbox_';
    
    /**
     * Transient expiration time (30 minutes)
     */
    const TRANSIENT_EXPIRATION = 1800;

    /**
     * Get a value from the data store
     *
     * @param string $key
     * @return mixed|null
     */
    public function get($key)
    {
        $transient_key = self::TRANSIENT_PREFIX . $key;
        $value = get_transient($transient_key);
        
        return $value !== false ? $value : null;
    }

    /**
     * Set a value in the data store
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $transient_key = self::TRANSIENT_PREFIX . $key;
        set_transient($transient_key, $value, self::TRANSIENT_EXPIRATION);
    }

    /**
     * Clear a value from the data store
     *
     * @param string $key
     */
    public function clear($key)
    {
        $transient_key = self::TRANSIENT_PREFIX . $key;
        delete_transient($transient_key);
    }
}