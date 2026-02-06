<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Interface for cloud storage providers
 * 
 * This interface defines the contract for implementing cloud storage providers
 * like Dropbox, Google Drive, etc.
 */
interface Uni_Cpo_Cloud_Storage_Interface
{
    /**
     * Upload a file to cloud storage
     * 
     * @param string $local_file_path Full path to local file
     * @param string $cloud_path      Destination path in cloud storage
     * @param array  $metadata        Optional metadata for the file
     * 
     * @return array Result array with success/error information
     *               Format: ['success' => bool, 'url' => string, 'error' => string, 'data' => mixed]
     */
    public function upload_file($local_file_path, $cloud_path, $metadata = array());

    /**
     * Delete a file from cloud storage
     * 
     * @param string $cloud_path Path to file in cloud storage
     * 
     * @return array Result array with success/error information
     *               Format: ['success' => bool, 'error' => string]
     */
    public function delete_file($cloud_path);

    /**
     * Check if the cloud storage is properly configured
     * 
     * @return bool True if configured and ready to use
     */
    public function is_configured();

    /**
     * Get the name of the storage provider
     * 
     * @return string Provider name (e.g., 'dropbox', 'google_drive')
     */
    public function get_provider_name();

    /**
     * Generate a public URL for accessing the file (if supported)
     * 
     * @param string $cloud_path Path to file in cloud storage
     * 
     * @return string|false Public URL or false if not supported
     */
    public function get_public_url($cloud_path);

    /**
     * Get storage provider specific settings/configuration
     * 
     * @return array Configuration array
     */
    public function get_settings();
}