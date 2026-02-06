<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once UNI_CPO_ABSPATH . 'includes/interfaces/interface-uni-cpo-cloud-storage.php';

/**
 * Dropbox cloud storage implementation using OAuth2
 */
class Uni_Cpo_Dropbox_Storage implements Uni_Cpo_Cloud_Storage_Interface
{
    private $settings;
    private $dropbox;

    public function __construct($settings = null)
    {
        $this->settings = $settings ?: UniCpo()->get_settings();
        $this->init_dropbox();
    }

    /**
     * Initialize Dropbox client with OAuth2 tokens
     */
    private function init_dropbox()
    {
        if (!$this->is_configured()) {
            return;
        }

        // Ensure autoloader is initialized (dependencies will be loaded automatically)
        if (method_exists(UniCpo(), 'init_autoloader')) {
            // Use private method via reflection since it's needed here
            $reflection = new ReflectionClass(UniCpo());
            $method = $reflection->getMethod('init_autoloader');
            $method->setAccessible(true);
            $method->invoke(UniCpo());
        }
        
        // Include our custom WordPress-compatible persistent data store
        require_once UNI_CPO_ABSPATH . 'includes/cloud-storage/class-uni-cpo-wordpress-persistent-data-store.php';

        $app_key = $this->settings['dropbox_app_key'];
        $app_secret = $this->settings['dropbox_app_secret'];
        $access_token = get_option('uni_cpo_dropbox_access_token', '');

        try {
            // Create Dropbox app instance with custom persistent data store
            $app = new \Kunnu\Dropbox\DropboxApp($app_key, $app_secret, $access_token);
            $persistentDataStore = new Uni_Cpo_WordPress_Persistent_Data_Store();
            $config = ['persistent_data_store' => $persistentDataStore];
            $this->dropbox = new \Kunnu\Dropbox\Dropbox($app, $config);
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("DROPBOX: Failed to initialize client: " . $e->getMessage(), 'error');
            $this->dropbox = null;
        }
    }

    /**
     * Refresh access token if expired
     *
     * @return bool Success status
     */
    private function refresh_token_if_needed()
    {
        $expires_at = get_option('uni_cpo_dropbox_expires_at', 0);
        $refresh_token = get_option('uni_cpo_dropbox_refresh_token', '');

        // Check if token is expired (with 5-minute buffer)
        if (time() > ($expires_at - 300) && !empty($refresh_token)) {
            try {
                uni_cpo_log_cloud_operation("DROPBOX: Refreshing access token", 'info');

                $app_key = $this->settings['dropbox_app_key'];
                $app_secret = $this->settings['dropbox_app_secret'];

                // Create temporary app for token refresh
                $app = new \Kunnu\Dropbox\DropboxApp($app_key, $app_secret);
                $dropbox = new \Kunnu\Dropbox\Dropbox($app);
                $authHelper = $dropbox->getAuthHelper();

                // Create AccessToken object with current token data
                $currentTokenData = [
                    'access_token' => get_option('uni_cpo_dropbox_access_token', ''),
                    'refresh_token' => $refresh_token,
                    'expires_in' => $expires_at - time(),
                    'account_id' => get_option('uni_cpo_dropbox_account_id', '')
                ];
                $currentToken = new \Kunnu\Dropbox\Models\AccessToken($currentTokenData);

                // Refresh the token
                $newToken = $authHelper->getRefreshedAccessToken($currentToken);

                // Update stored tokens
                update_option('uni_cpo_dropbox_access_token', $newToken->getToken());
                if ($newToken->getRefreshToken()) {
                    update_option('uni_cpo_dropbox_refresh_token', $newToken->getRefreshToken());
                }
                update_option('uni_cpo_dropbox_expires_at', time() + $newToken->getExpiryTime());

                // Reinitialize dropbox client with new token
                $this->init_dropbox();

                uni_cpo_log_cloud_operation("DROPBOX: Token refreshed successfully", 'info');
                return true;
            } catch (Exception $e) {
                uni_cpo_log_cloud_operation("DROPBOX: Token refresh failed: " . $e->getMessage(), 'error');
                return false;
            }
        }

        return true; // Token doesn't need refresh
    }

    /**
     * Upload a file to Dropbox
     * 
     * @param string $local_file_path Full path to local file
     * @param string $cloud_path      Destination path in Dropbox
     * @param array  $metadata        Optional metadata for the file
     * 
     * @return array Result array with success/error information
     */
    public function upload_file($local_file_path, $cloud_path, $metadata = array())
    {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => __('Dropbox is not configured properly. Missing app credentials or authorization.', 'uni-cpo'),
                'url' => '',
                'data' => null
            );
        }

        if (!file_exists($local_file_path)) {
            return array(
                'success' => false,
                'error' => __('Local file does not exist.', 'uni-cpo'),
                'url' => '',
                'data' => null
            );
        }

        // Refresh token if needed
        if (!$this->refresh_token_if_needed()) {
            return array(
                'success' => false,
                'error' => __('Failed to refresh Dropbox access token. Please reauthorize.', 'uni-cpo'),
                'url' => '',
                'data' => null
            );
        }

        try {
            uni_cpo_log_cloud_operation("DROPBOX: Uploading file {$local_file_path} to {$cloud_path}", 'info');
            
            // Get file size for upload strategy decision
            $file_size = filesize($local_file_path);
            uni_cpo_log_cloud_operation("DROPBOX: File size: {$file_size} bytes", 'info');

            // Upload parameters
            $params = [
                'mode' => 'overwrite',
                'autorename' => false,
                'mute' => false
            ];

            // Use the new SDK's upload method (handles chunking automatically)
            $result = $this->dropbox->upload($local_file_path, $cloud_path, $params);

            if ($result) {
                uni_cpo_log_cloud_operation("DROPBOX: Upload successful", 'info');
                
                // Try to get a public URL (sharing link)
                $public_url = $this->get_public_url($cloud_path);

                return array(
                    'success' => true,
                    'error' => '',
                    'url' => $public_url ?: $cloud_path,
                    'data' => $result
                );
            } else {
                return array(
                    'success' => false,
                    'error' => __('Upload failed - no response from Dropbox', 'uni-cpo'),
                    'url' => '',
                    'data' => null
                );
            }

        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("DROPBOX: Upload failed: " . $e->getMessage(), 'error');
            
            // Check if it's an auth error and try token refresh
            if (strpos($e->getMessage(), 'invalid_access_token') !== false || 
                strpos($e->getMessage(), 'expired_access_token') !== false) {
                
                if ($this->refresh_token_if_needed()) {
                    // Retry upload once after token refresh
                    try {
                        $result = $this->dropbox->upload($local_file_path, $cloud_path, $params);
                        if ($result) {
                            $public_url = $this->get_public_url($cloud_path);
                            return array(
                                'success' => true,
                                'error' => '',
                                'url' => $public_url ?: $cloud_path,
                                'data' => $result
                            );
                        }
                    } catch (Exception $retry_e) {
                        uni_cpo_log_cloud_operation("DROPBOX: Retry upload failed: " . $retry_e->getMessage(), 'error');
                    }
                }
            }

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'url' => '',
                'data' => null
            );
        }
    }

    /**
     * Delete a file from Dropbox
     * 
     * @param string $cloud_path Path to file in Dropbox
     * 
     * @return array Result array with success/error information
     */
    public function delete_file($cloud_path)
    {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => __('Dropbox is not configured properly.', 'uni-cpo')
            );
        }

        // Refresh token if needed
        if (!$this->refresh_token_if_needed()) {
            return array(
                'success' => false,
                'error' => __('Failed to refresh Dropbox access token. Please reauthorize.', 'uni-cpo')
            );
        }

        try {
            uni_cpo_log_cloud_operation("DROPBOX: Deleting file {$cloud_path}", 'info');
            
            $result = $this->dropbox->delete($cloud_path);

            if ($result) {
                uni_cpo_log_cloud_operation("DROPBOX: Delete successful", 'info');
                return array(
                    'success' => true,
                    'error' => ''
                );
            } else {
                return array(
                    'success' => false,
                    'error' => __('Delete failed - no response from Dropbox', 'uni-cpo')
                );
            }

        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("DROPBOX: Delete failed: " . $e->getMessage(), 'error');
            
            // Check if it's an auth error and try token refresh
            if (strpos($e->getMessage(), 'invalid_access_token') !== false || 
                strpos($e->getMessage(), 'expired_access_token') !== false) {
                
                if ($this->refresh_token_if_needed()) {
                    // Retry delete once after token refresh
                    try {
                        $result = $this->dropbox->delete($cloud_path);
                        if ($result) {
                            return array('success' => true, 'error' => '');
                        }
                    } catch (Exception $retry_e) {
                        uni_cpo_log_cloud_operation("DROPBOX: Retry delete failed: " . $retry_e->getMessage(), 'error');
                    }
                }
            }

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Check if Dropbox is properly configured
     * 
     * @return bool True if configured and ready to use
     */
    public function is_configured()
    {
        $app_key = isset($this->settings['dropbox_app_key']) ? $this->settings['dropbox_app_key'] : '';
        $app_secret = isset($this->settings['dropbox_app_secret']) ? $this->settings['dropbox_app_secret'] : '';
        $access_token = get_option('uni_cpo_dropbox_access_token', '');
        $refresh_token = get_option('uni_cpo_dropbox_refresh_token', '');

        return !empty($app_key) && !empty($app_secret) && !empty($access_token) && !empty($refresh_token);
    }

    /**
     * Get the name of the storage provider
     * 
     * @return string Provider name
     */
    public function get_provider_name()
    {
        return 'dropbox';
    }

    /**
     * Generate a public URL for accessing the file
     * 
     * @param string $cloud_path Path to file in Dropbox
     * 
     * @return string|false Public URL or false if not supported
     */
    public function get_public_url($cloud_path)
    {
        if (!$this->is_configured() || !$this->dropbox) {
            uni_cpo_log_cloud_operation("DROPBOX: Cannot get public URL - not configured", 'error');
            return false;
        }

        uni_cpo_log_cloud_operation("DROPBOX: Attempting to get public URL for {$cloud_path}", 'info');

        try {
            // Use the new SDK's getTemporaryLink method
            $result = $this->dropbox->getTemporaryLink($cloud_path);
            
            if ($result && method_exists($result, 'getLink')) {
                $url = $result->getLink();
                uni_cpo_log_cloud_operation("DROPBOX: Successfully created temporary link: {$url}", 'info');
                return $url;
            }

        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("DROPBOX: Failed to get temporary link: " . $e->getMessage(), 'warning');
        }

        // Fallback: return the path itself
        uni_cpo_log_cloud_operation("DROPBOX: Could not generate public URL for {$cloud_path}", 'error');
        return false;
    }

    /**
     * Get Dropbox specific settings/configuration
     * 
     * @return array Configuration array
     */
    public function get_settings()
    {
        $access_token = get_option('uni_cpo_dropbox_access_token', '');
        $expires_at = get_option('uni_cpo_dropbox_expires_at', 0);
        
        return array(
            'provider' => 'dropbox',
            'app_key' => !empty($this->settings['dropbox_app_key']) ? '***' : '',
            'app_secret' => !empty($this->settings['dropbox_app_secret']) ? '***' : '',
            'access_token' => !empty($access_token) ? '***' : '',
            'expires_at' => $expires_at,
            'expires_in' => $expires_at - time(),
            'configured' => $this->is_configured()
        );
    }

    /**
     * Log cloud storage operations for debugging
     * 
     * @param string $message Log message
     * @param string $level   Log level (info, warning, error)
     */
    private function log($message, $level = 'info')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Uni CPO Dropbox] [%s] %s', strtoupper($level), $message));
        }
    }
}