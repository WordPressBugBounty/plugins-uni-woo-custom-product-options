<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Factory class for cloud storage providers
 */
class Uni_Cpo_Cloud_Storage_Factory
{
    private static $providers = array();

    /**
     * Register cloud storage providers
     */
    public static function init()
    {
        // Register built-in providers
        self::register_provider('dropbox', 'Uni_Cpo_Dropbox_Storage');
        self::register_provider('gdrive', 'Uni_Cpo_Google_Drive_Storage');
        
        // Allow plugins to register additional providers
        do_action('uni_cpo_register_cloud_storage_providers', __CLASS__);
    }

    /**
     * Register a cloud storage provider
     * 
     * @param string $name       Provider name (e.g., 'dropbox', 'google_drive')
     * @param string $class_name Provider class name
     */
    public static function register_provider($name, $class_name)
    {
        self::$providers[$name] = $class_name;
    }

    /**
     * Get a cloud storage provider instance
     * 
     * @param string $provider_name Provider name
     * @param array  $settings      Optional settings override
     * 
     * @return Uni_Cpo_Cloud_Storage_Interface|false Provider instance or false if not found
     */
    public static function get_provider($provider_name, $settings = null)
    {
        uni_cpo_log_cloud_operation("FACTORY: Requesting provider '{$provider_name}'", 'info');
        
        if (!isset(self::$providers[$provider_name])) {
            uni_cpo_log_cloud_operation("FACTORY ERROR: Provider '{$provider_name}' not registered", 'error');
            return false;
        }

        $class_name = self::$providers[$provider_name];
        uni_cpo_log_cloud_operation("FACTORY: Class name for '{$provider_name}': {$class_name}", 'info');
        
        if (!class_exists($class_name)) {
            // Try to load the class file
            $file_path = self::get_provider_file_path($provider_name);
            uni_cpo_log_cloud_operation("FACTORY: Loading class file from: " . ($file_path ?: 'NULL'), 'info');
            
            if ($file_path && file_exists($file_path)) {
                require_once $file_path;
                uni_cpo_log_cloud_operation("FACTORY: Class file loaded successfully", 'info');
            } else {
                uni_cpo_log_cloud_operation("FACTORY ERROR: Class file not found: " . ($file_path ?: 'NULL'), 'error');
            }
            
            if (!class_exists($class_name)) {
                uni_cpo_log_cloud_operation("FACTORY ERROR: Class '{$class_name}' still not found after loading file", 'error');
                return false;
            }
        }

        uni_cpo_log_cloud_operation("FACTORY: Creating instance of '{$class_name}'", 'info');
        try {
            $instance = new $class_name($settings);
            uni_cpo_log_cloud_operation("FACTORY: Successfully created provider instance", 'info');
            return $instance;
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("FACTORY ERROR: Failed to create provider instance: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get the current configured cloud storage provider
     * 
     * @return Uni_Cpo_Cloud_Storage_Interface|false Current provider or false if none/local
     */
    public static function get_current_provider()
    {
        $settings = UniCpo()->get_settings();
        $file_storage = isset($settings['file_storage']) ? $settings['file_storage'] : 'local';

        if ($file_storage === 'local') {
            return false; // Local storage doesn't use cloud providers
        }

        return self::get_provider($file_storage, $settings);
    }

    /**
     * Get all registered providers
     * 
     * @return array Array of provider names
     */
    public static function get_registered_providers()
    {
        return array_keys(self::$providers);
    }

    /**
     * Check if a provider is registered
     * 
     * @param string $provider_name Provider name
     * 
     * @return bool True if registered
     */
    public static function is_provider_registered($provider_name)
    {
        return isset(self::$providers[$provider_name]);
    }

    /**
     * Get the file path for a provider class
     * 
     * @param string $provider_name Provider name
     * 
     * @return string|false File path or false if not found
     */
    private static function get_provider_file_path($provider_name)
    {
        $file_paths = array(
            'dropbox' => UNI_CPO_ABSPATH . 'includes/cloud-storage/class-uni-cpo-dropbox-storage.php',
            'gdrive' => UNI_CPO_ABSPATH . 'includes/cloud-storage/class-uni-cpo-google-drive-storage.php',
        );

        return isset($file_paths[$provider_name]) ? $file_paths[$provider_name] : false;
    }

    /**
     * Process cloud upload for order items
     * 
     * @param int $order_id WooCommerce order ID
     * @return array Results of upload operations
     */
    public static function process_order_cloud_uploads($order_id)
    {
        $results = array();
        $provider = self::get_current_provider();

        if (!$provider || !$provider->is_configured()) {
            return $results; // No cloud provider or not configured
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return $results;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $item_results = self::process_item_cloud_uploads($item, $order_id, $provider);
            if (!empty($item_results)) {
                $results[$item_id] = $item_results;
            }
        }

        return $results;
    }

    /**
     * Process cloud uploads for a single order item
     * 
     * @param WC_Order_Item_Product $item     Order item
     * @param int                   $order_id Order ID
     * @param Uni_Cpo_Cloud_Storage_Interface $provider Cloud provider
     * 
     * @return array Upload results
     */
    private static function process_item_cloud_uploads($item, $order_id, $provider)
    {
        $results = array();
        $meta_data = $item->get_meta_data();

        foreach ($meta_data as $meta) {
            $key = $meta->key;
            $value = $meta->value;

            // Check if this is a file upload field (contains attachment IDs)
            if (self::is_file_upload_field($key, $value)) {
                $attachment_ids = is_array($value) ? $value : json_decode($value, true);
                if (!is_array($attachment_ids)) {
                    continue;
                }

                $field_results = self::upload_attachments_to_cloud($attachment_ids, $order_id, $provider, $key);
                if (!empty($field_results)) {
                    $results[$key] = $field_results;
                    // Update order item meta with cloud URLs instead of attachment IDs
                    $item->update_meta_data($key, $field_results['cloud_urls']);
                    $item->save_meta_data();
                }
            }
        }

        return $results;
    }

    /**
     * Check if a meta field contains file upload data (attachment IDs)
     * 
     * @param string $key   Meta key
     * @param mixed  $value Meta value
     * 
     * @return bool True if this is a file upload field
     */
    private static function is_file_upload_field($key, $value)
    {
        // Skip non-CPO fields
        if (strpos($key, '_') !== 0) {
            return false;
        }

        // Try to decode as JSON array of numbers (attachment IDs)
        $decoded = is_array($value) ? $value : json_decode($value, true);
        if (!is_array($decoded) || empty($decoded)) {
            return false;
        }

        // Check if all values are numeric (attachment IDs)
        foreach ($decoded as $item) {
            if (!is_numeric($item) || intval($item) <= 0) {
                return false;
            }
        }

        // Verify these are actual attachment IDs
        foreach ($decoded as $attachment_id) {
            if (get_post_type($attachment_id) !== 'attachment') {
                return false;
            }
        }

        return true;
    }

    /**
     * Upload attachments to cloud storage and delete local copies
     * 
     * @param array $attachment_ids               Array of attachment IDs
     * @param int   $order_id                     Order ID
     * @param Uni_Cpo_Cloud_Storage_Interface $provider Cloud provider
     * @param string $field_key                   Field key for logging
     * 
     * @return array Upload results
     */
    private static function upload_attachments_to_cloud($attachment_ids, $order_id, $provider, $field_key)
    {
        $results = array(
            'cloud_urls' => array(),
            'successful_uploads' => array(),
            'failed_uploads' => array(),
            'deleted_attachments' => array()
        );

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = intval($attachment_id);
            $file_path = get_attached_file($attachment_id);
            
            if (!$file_path || !file_exists($file_path)) {
                $results['failed_uploads'][] = array(
                    'attachment_id' => $attachment_id,
                    'error' => __('Local file not found', 'uni-cpo')
                );
                continue;
            }

            $filename = basename($file_path);
            $cloud_path = "/order-{$order_id}/{$filename}";

            // Upload to cloud with retry logic
            $upload_result = self::upload_with_retry($provider, $file_path, $cloud_path, 2);

            if ($upload_result['success']) {
                $results['successful_uploads'][] = array(
                    'attachment_id' => $attachment_id,
                    'cloud_path' => $cloud_path,
                    'cloud_url' => $upload_result['url']
                );
                $results['cloud_urls'][] = $upload_result['url'];

                // Delete local attachment after successful upload
                $delete_result = wp_delete_attachment($attachment_id, true);
                if ($delete_result) {
                    $results['deleted_attachments'][] = $attachment_id;
                }

                // Log successful upload
                self::log("Successfully uploaded attachment {$attachment_id} to cloud path {$cloud_path} for order {$order_id}", 'info');

            } else {
                $results['failed_uploads'][] = array(
                    'attachment_id' => $attachment_id,
                    'error' => $upload_result['error']
                );
                
                // Keep attachment ID in cloud_urls as fallback
                $results['cloud_urls'][] = $attachment_id;

                // Log failed upload
                self::log("Failed to upload attachment {$attachment_id} for order {$order_id}: {$upload_result['error']}", 'error');
            }
        }

        return $results;
    }

    /**
     * Upload file with retry logic
     * 
     * @param Uni_Cpo_Cloud_Storage_Interface $provider   Cloud provider
     * @param string                          $file_path   Local file path
     * @param string                          $cloud_path  Cloud destination path
     * @param int                             $max_retries Maximum retry attempts
     * 
     * @return array Upload result
     */
    private static function upload_with_retry($provider, $file_path, $cloud_path, $max_retries = 2)
    {
        $attempt = 1;
        $last_result = null;

        while ($attempt <= $max_retries) {
            $result = $provider->upload_file($file_path, $cloud_path);
            
            if ($result['success']) {
                if ($attempt > 1) {
                    self::log("Upload succeeded on attempt {$attempt} for {$cloud_path}", 'info');
                }
                return $result;
            }

            $last_result = $result;
            self::log("Upload attempt {$attempt} failed for {$cloud_path}: {$result['error']}", 'warning');
            $attempt++;
            
            if ($attempt <= $max_retries) {
                sleep(1); // Brief delay before retry
            }
        }

        return $last_result;
    }

    /**
     * Log cloud storage operations
     * 
     * @param string $message Log message
     * @param string $level   Log level (info, warning, error)
     */
    private static function log($message, $level = 'info')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Uni CPO Cloud Storage] [%s] %s', strtoupper($level), $message));
        }
    }
}