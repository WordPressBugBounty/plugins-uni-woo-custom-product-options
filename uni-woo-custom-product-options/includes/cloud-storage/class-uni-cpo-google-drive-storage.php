<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once UNI_CPO_ABSPATH . 'includes/interfaces/interface-uni-cpo-cloud-storage.php';

use Google\Auth\OAuth2;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Google Drive cloud storage implementation using OAuth2
 * Lightweight implementation using google/auth and direct HTTP calls
 */
class Uni_Cpo_Google_Drive_Storage implements Uni_Cpo_Cloud_Storage_Interface
{
    private $settings;
    private $oauth2;
    private $http_client;
    private $access_token;

    const DRIVE_API_BASE = 'https://www.googleapis.com/drive/v3';
    const DRIVE_UPLOAD_BASE = 'https://www.googleapis.com/upload/drive/v3';
    const OAUTH_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    const OAUTH_AUTH_URI = 'https://accounts.google.com/o/oauth2/v2/auth';

    public function __construct($settings = null)
    {
        $this->settings = $settings ?: UniCpo()->get_settings();
        $this->http_client = new Client([
            'timeout' => 60,
            'connect_timeout' => 10
        ]);
        $this->init_google_client();
    }

    /**
     * Initialize Google OAuth2 client
     */
    private function init_google_client()
    {
        if (!$this->is_configured()) {
            return;
        }

        $client_id = $this->settings['gdrive_client_id'];
        $client_secret = $this->settings['gdrive_client_secret'];
        $access_token_json = get_option('uni_cpo_gdrive_access_token', '');

        try {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Initializing OAuth2 client", 'info');

            // Initialize OAuth2
            $this->oauth2 = new OAuth2([
                'clientId' => $client_id,
                'clientSecret' => $client_secret,
                'authorizationUri' => self::OAUTH_AUTH_URI,
                'tokenCredentialUri' => self::OAUTH_TOKEN_URI,
                'redirectUri' => admin_url('admin.php?page=uni-cpo-settings&tab=file_uploads&gdrive_callback=1'),
                'scope' => 'https://www.googleapis.com/auth/drive.file'
            ]);

            if (!empty($access_token_json)) {
                $token_data = json_decode($access_token_json, true);
                if ($token_data && isset($token_data['access_token'])) {
                    $this->access_token = $token_data;
                    $this->oauth2->updateToken($token_data);
                }
            }

            uni_cpo_log_cloud_operation("GOOGLE DRIVE: OAuth2 client initialized successfully", 'info');
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Failed to initialize client: " . $e->getMessage(), 'error');
            $this->oauth2 = null;
        }
    }

    /**
     * Refresh access token if expired
     *
     * @return bool Success status
     */
    private function refresh_token_if_needed()
    {
        if (!$this->oauth2) {
            return false;
        }

        // Check if token is expired
        if (isset($this->access_token['expires_in']) && isset($this->access_token['created'])) {
            $expires_at = $this->access_token['created'] + $this->access_token['expires_in'];
            if (time() < $expires_at - 300) { // 5 minute buffer
                return true; // Token still valid
            }
        }

        $refresh_token = get_option('uni_cpo_gdrive_refresh_token', '');

        if (empty($refresh_token)) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: No refresh token available", 'error');
            return false;
        }

        try {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Refreshing access token", 'info');

            $this->oauth2->setRefreshToken($refresh_token);
            $new_token = $this->oauth2->fetchAuthToken(null);

            if (isset($new_token['error'])) {
                uni_cpo_log_cloud_operation("GOOGLE DRIVE: Token refresh failed: " . $new_token['error'], 'error');
                return false;
            }

            // Add created timestamp
            $new_token['created'] = time();

            // Update stored tokens
            update_option('uni_cpo_gdrive_access_token', json_encode($new_token));
            if (isset($new_token['refresh_token'])) {
                update_option('uni_cpo_gdrive_refresh_token', $new_token['refresh_token']);
            }

            $this->access_token = $new_token;
            $this->oauth2->updateToken($new_token);

            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Token refreshed successfully", 'info');
            return true;
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Token refresh failed: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Make an authenticated API request to Google Drive
     *
     * @param string $method HTTP method
     * @param string $uri API endpoint URI
     * @param array $options Guzzle request options
     * @return array Response data
     * @throws Exception
     */
    private function api_request($method, $uri, $options = [])
    {
        if (!$this->oauth2 || !isset($this->access_token['access_token'])) {
            throw new Exception('Not authenticated with Google Drive');
        }

        // Add authorization header
        if (!isset($options['headers'])) {
            $options['headers'] = [];
        }
        $options['headers']['Authorization'] = 'Bearer ' . $this->access_token['access_token'];

        try {
            $response = $this->http_client->request($method, $uri, $options);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?: [];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $error_body = (string) $e->getResponse()->getBody();
                $error_data = json_decode($error_body, true);

                // Check if it's an auth error
                if ($e->getResponse()->getStatusCode() == 401) {
                    throw new Exception('Authentication failed: ' . ($error_data['error']['message'] ?? 'Unauthorized'));
                }

                throw new Exception($error_data['error']['message'] ?? $e->getMessage());
            }
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Upload a file to Google Drive
     *
     * @param string $local_file_path Full path to local file
     * @param string $cloud_path      Destination path in Google Drive (used as filename)
     * @param array  $metadata        Optional metadata for the file
     *
     * @return array Result array with success/error information
     */
    public function upload_file($local_file_path, $cloud_path, $metadata = array())
    {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => __('Google Drive is not configured properly. Missing client credentials or authorization.', 'uni-cpo'),
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
                'error' => __('Failed to refresh Google Drive access token. Please reauthorize.', 'uni-cpo'),
                'url' => '',
                'data' => null
            );
        }

        try {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Uploading file {$local_file_path} to {$cloud_path}", 'info');

            $file_size = filesize($local_file_path);
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: File size: {$file_size} bytes", 'info');

            // Parse cloud_path to extract folder structure
            $path_parts = explode('/', trim($cloud_path, '/'));
            $filename = array_pop($path_parts);
            $folder_path = $path_parts;

            // Get the parent folder ID
            $parent_folder_id = $this->get_or_create_folder_structure($folder_path);

            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Uploading file '{$filename}' to folder ID: {$parent_folder_id}", 'info');

            // Create file metadata
            $file_metadata = [
                'name' => $filename,
                'parents' => [$parent_folder_id]
            ];

            // Read file content
            $content = file_get_contents($local_file_path);
            $mime_type = mime_content_type($local_file_path);

            // Create multipart upload
            $boundary = uniqid();
            $delimiter = "\r\n--" . $boundary . "\r\n";
            $close_delimiter = "\r\n--" . $boundary . "--";

            $multipart_body = $delimiter;
            $multipart_body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
            $multipart_body .= json_encode($file_metadata);
            $multipart_body .= $delimiter;
            $multipart_body .= "Content-Type: " . $mime_type . "\r\n";
            $multipart_body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $multipart_body .= base64_encode($content);
            $multipart_body .= $close_delimiter;

            // Upload file
            $result = $this->api_request('POST', self::DRIVE_UPLOAD_BASE . '/files?uploadType=multipart&fields=id,webViewLink,webContentLink', [
                'headers' => [
                    'Content-Type' => 'multipart/related; boundary=' . $boundary
                ],
                'body' => $multipart_body
            ]);

            if ($result && isset($result['id'])) {
                uni_cpo_log_cloud_operation("GOOGLE DRIVE: Upload successful, file ID: " . $result['id'], 'info');

                // Make file publicly accessible
                $this->make_file_public($result['id']);

                // Get public URL
                $public_url = $this->get_public_url_by_id($result['id']);

                return array(
                    'success' => true,
                    'error' => '',
                    'url' => $public_url ?: ($result['webViewLink'] ?? ''),
                    'data' => array(
                        'file_id' => $result['id'],
                        'web_view_link' => $result['webViewLink'] ?? '',
                        'web_content_link' => $result['webContentLink'] ?? ''
                    )
                );
            } else {
                return array(
                    'success' => false,
                    'error' => __('Upload failed - no response from Google Drive', 'uni-cpo'),
                    'url' => '',
                    'data' => null
                );
            }

        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Upload failed: " . $e->getMessage(), 'error');

            // Check if it's an auth error
            if (strpos($e->getMessage(), 'Authentication failed') !== false) {
                if ($this->refresh_token_if_needed()) {
                    // Retry upload once after token refresh
                    return $this->upload_file($local_file_path, $cloud_path, $metadata);
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
     * Delete a file from Google Drive
     *
     * @param string $cloud_path Path to file in Google Drive (file ID or name)
     *
     * @return array Result array with success/error information
     */
    public function delete_file($cloud_path)
    {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => __('Google Drive is not configured properly.', 'uni-cpo')
            );
        }

        // Refresh token if needed
        if (!$this->refresh_token_if_needed()) {
            return array(
                'success' => false,
                'error' => __('Failed to refresh Google Drive access token. Please reauthorize.', 'uni-cpo')
            );
        }

        try {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Deleting file {$cloud_path}", 'info');

            $file_id = $this->get_file_id_from_path($cloud_path);

            if (!$file_id) {
                return array(
                    'success' => false,
                    'error' => __('File not found in Google Drive', 'uni-cpo')
                );
            }

            $this->api_request('DELETE', self::DRIVE_API_BASE . '/files/' . $file_id);

            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Delete successful", 'info');
            return array(
                'success' => true,
                'error' => ''
            );

        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Delete failed: " . $e->getMessage(), 'error');

            // Check if it's an auth error
            if (strpos($e->getMessage(), 'Authentication failed') !== false) {
                if ($this->refresh_token_if_needed()) {
                    return $this->delete_file($cloud_path);
                }
            }

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Check if Google Drive is properly configured
     *
     * @return bool True if configured and ready to use
     */
    public function is_configured()
    {
        $client_id = isset($this->settings['gdrive_client_id']) ? $this->settings['gdrive_client_id'] : '';
        $client_secret = isset($this->settings['gdrive_client_secret']) ? $this->settings['gdrive_client_secret'] : '';
        $access_token = get_option('uni_cpo_gdrive_access_token', '');

        return !empty($client_id) && !empty($client_secret) && !empty($access_token);
    }

    /**
     * Get the name of the storage provider
     *
     * @return string Provider name
     */
    public function get_provider_name()
    {
        return 'gdrive';
    }

    /**
     * Generate a public URL for accessing the file
     *
     * @param string $cloud_path Path to file in Google Drive (file ID or name)
     *
     * @return string|false Public URL or false if not supported
     */
    public function get_public_url($cloud_path)
    {
        if (!$this->is_configured()) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Cannot get public URL - not configured", 'error');
            return false;
        }

        $file_id = $this->get_file_id_from_path($cloud_path);
        if (!$file_id) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Cannot get public URL - file not found", 'error');
            return false;
        }

        return $this->get_public_url_by_id($file_id);
    }

    /**
     * Get Google Drive specific settings/configuration
     *
     * @return array Configuration array
     */
    public function get_settings()
    {
        $access_token = get_option('uni_cpo_gdrive_access_token', '');
        $token_data = !empty($access_token) ? json_decode($access_token, true) : array();
        $expires_in = isset($token_data['expires_in']) ? $token_data['expires_in'] : 0;

        return array(
            'provider' => 'gdrive',
            'client_id' => !empty($this->settings['gdrive_client_id']) ? '***' : '',
            'client_secret' => !empty($this->settings['gdrive_client_secret']) ? '***' : '',
            'access_token' => !empty($access_token) ? '***' : '',
            'expires_in' => $expires_in,
            'configured' => $this->is_configured()
        );
    }

    /**
     * Get or create upload folder in Google Drive
     *
     * @return string Folder ID
     */
    private function get_or_create_upload_folder()
    {
        $folder_name = 'UniCPO-Uploads';
        $folder_id = get_option('uni_cpo_gdrive_upload_folder_id', '');

        // Check if cached folder still exists
        if (!empty($folder_id)) {
            try {
                $this->api_request('GET', self::DRIVE_API_BASE . '/files/' . $folder_id);
                return $folder_id; // Folder exists
            } catch (Exception $e) {
                // Folder doesn't exist, create new one
                delete_option('uni_cpo_gdrive_upload_folder_id');
            }
        }

        try {
            // Create new folder
            $folder_metadata = [
                'name' => $folder_name,
                'mimeType' => 'application/vnd.google-apps.folder'
            ];

            $result = $this->api_request('POST', self::DRIVE_API_BASE . '/files?fields=id', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($folder_metadata)
            ]);

            $folder_id = $result['id'];
            update_option('uni_cpo_gdrive_upload_folder_id', $folder_id);

            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Created upload folder with ID: {$folder_id}", 'info');
            return $folder_id;

        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Failed to create upload folder: " . $e->getMessage(), 'error');
            return ''; // Use root folder as fallback
        }
    }

    /**
     * Get or create folder structure in Google Drive
     *
     * @param array $folder_path Array of folder names
     * @return string Final folder ID where file should be uploaded
     */
    private function get_or_create_folder_structure($folder_path)
    {
        // Start with the main upload folder
        $parent_folder_id = $this->get_or_create_upload_folder();

        // If no subfolder structure needed, return the main upload folder
        if (empty($folder_path)) {
            return $parent_folder_id;
        }

        uni_cpo_log_cloud_operation("GOOGLE DRIVE: Creating folder structure: " . implode('/', $folder_path), 'info');

        // Traverse/create each folder level
        foreach ($folder_path as $folder_name) {
            if (empty($folder_name)) {
                continue;
            }

            // Check if folder already exists in parent
            $existing_folder_id = $this->find_folder_by_name($folder_name, $parent_folder_id);

            if ($existing_folder_id) {
                uni_cpo_log_cloud_operation("GOOGLE DRIVE: Found existing folder '{$folder_name}' with ID: {$existing_folder_id}", 'info');
                $parent_folder_id = $existing_folder_id;
            } else {
                // Create new folder
                $parent_folder_id = $this->create_folder($folder_name, $parent_folder_id);
                uni_cpo_log_cloud_operation("GOOGLE DRIVE: Created folder '{$folder_name}' with ID: {$parent_folder_id}", 'info');
            }

            if (empty($parent_folder_id)) {
                uni_cpo_log_cloud_operation("GOOGLE DRIVE: Failed to create/find folder '{$folder_name}'", 'error');
                return $this->get_or_create_upload_folder(); // Fallback to main folder
            }
        }

        return $parent_folder_id;
    }

    /**
     * Find folder by name within a parent folder
     *
     * @param string $folder_name Name of folder to find
     * @param string $parent_id   Parent folder ID
     * @return string|false Folder ID if found, false otherwise
     */
    private function find_folder_by_name($folder_name, $parent_id)
    {
        try {
            $query = "mimeType='application/vnd.google-apps.folder' and name='" . addslashes($folder_name) . "' and '" . $parent_id . "' in parents and trashed=false";

            $result = $this->api_request('GET', self::DRIVE_API_BASE . '/files?' . http_build_query([
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 1
            ]));

            if (isset($result['files']) && count($result['files']) > 0) {
                return $result['files'][0]['id'];
            }

            return false;
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Error searching for folder '{$folder_name}': " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create a new folder in Google Drive
     *
     * @param string $folder_name Name of folder to create
     * @param string $parent_id   Parent folder ID
     * @return string|false Folder ID if created, false otherwise
     */
    private function create_folder($folder_name, $parent_id)
    {
        try {
            $folder_metadata = [
                'name' => $folder_name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parent_id]
            ];

            $result = $this->api_request('POST', self::DRIVE_API_BASE . '/files?fields=id', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($folder_metadata)
            ]);

            return $result['id'] ?? false;
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Failed to create folder '{$folder_name}': " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Make file publicly accessible
     *
     * @param string $file_id Google Drive file ID
     */
    private function make_file_public($file_id)
    {
        try {
            $permission = [
                'role' => 'reader',
                'type' => 'anyone'
            ];

            $this->api_request('POST', self::DRIVE_API_BASE . '/files/' . $file_id . '/permissions', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($permission)
            ]);

            uni_cpo_log_cloud_operation("GOOGLE DRIVE: File {$file_id} made public", 'info');
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Failed to make file public: " . $e->getMessage(), 'warning');
        }
    }

    /**
     * Get public URL by file ID
     *
     * @param string $file_id Google Drive file ID
     *
     * @return string|false Public URL or false if failed
     */
    private function get_public_url_by_id($file_id)
    {
        try {
            // Direct download link format for publicly shared files
            return "https://drive.google.com/uc?id={$file_id}&export=download";
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Failed to generate public URL for {$file_id}: " . $e->getMessage(), 'warning');
            return false;
        }
    }

    /**
     * Get file ID from path (file ID or filename)
     *
     * @param string $cloud_path File path/name/ID
     *
     * @return string|false File ID or false if not found
     */
    private function get_file_id_from_path($cloud_path)
    {
        // If it looks like a file ID (long alphanumeric string), return it
        if (preg_match('/^[a-zA-Z0-9_-]{25,}$/', $cloud_path)) {
            return $cloud_path;
        }

        // Otherwise, search by filename in upload folder
        try {
            $folder_id = $this->get_or_create_upload_folder();
            $filename = basename($cloud_path);
            $query = "name='" . addslashes($filename) . "'";
            if (!empty($folder_id)) {
                $query .= " and '{$folder_id}' in parents";
            }

            $result = $this->api_request('GET', self::DRIVE_API_BASE . '/files?' . http_build_query([
                'q' => $query,
                'fields' => 'files(id,name)'
            ]));

            if (isset($result['files']) && !empty($result['files'])) {
                return $result['files'][0]['id'];
            }
        } catch (Exception $e) {
            uni_cpo_log_cloud_operation("GOOGLE DRIVE: Error searching for file: " . $e->getMessage(), 'error');
        }

        return false;
    }
}
