<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class Uni_Cpo_Plugin_Settings {
    private $file;

    private $settings_base;

    private $settings;

    private $exclude_from_free;

    public function __construct( $file ) {
        $this->file = $file;
        $this->settings_base = 'uni_cpo_settings_general';
        $this->exclude_from_free = apply_filters( 'uni_cpo_exclude_from_free_plugin_settings', array(
            'ajax_add_to_cart',
            'change_product_image_in_cart',
            'product_image_size',
            'product_thumbnails_container',
            'display_weight_in_cart',
            'display_dimensions_in_cart',
            'range_slider_style',
            'file_upload',
            'multi_file_upload',
            'sample_feature',
            'gmap_api_key',
            'csv_delimiter'
        ) );
        // Initialise settings
        add_action( 'admin_init', array($this, 'init') );
        // Register plugin settings
        add_action( 'admin_init', array($this, 'register_settings') );
        // Add settings page to menu
        add_action( 'admin_menu', array($this, 'add_menu_item') );
        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array($this, 'add_settings_link') );
    }

    /**
     * Initialise settings
     * @return void
     */
    public function init() {
        $this->settings = $this->settings_fields();
    }

    /**
     * Add settings page to admin menu
     * @return void
     */
    public function add_menu_item() {
        $settings = add_submenu_page(
            'woocommerce',
            __( 'Uni CPO Settings', 'uni-cpo' ),
            __( 'Uni CPO Settings', 'uni-cpo' ),
            'manage_woocommerce',
            'uni-cpo-settings',
            array($this, 'settings_page')
        );
        $manager = add_submenu_page(
            'woocommerce',
            __( 'Uni CPO data manager tool', 'uni-cpo' ),
            __( 'Uni CPO data manager tool', 'uni-cpo' ),
            'manage_woocommerce',
            'uni-cpo-manager',
            array($this, 'manager_page')
        );
        $bulk_edit = add_submenu_page(
            'woocommerce',
            __( 'Uni CPO Bulk Edit', 'uni-cpo' ),
            __( 'Uni CPO Bulk Edit tool', 'uni-cpo' ),
            'manage_woocommerce',
            'uni-cpo-bulk-edit',
            array($this, 'bulk_edit_page')
        );
        $import = add_submenu_page(
            'woocommerce',
            __( 'Uni CPO Import/Export tool', 'uni-cpo' ),
            __( 'Uni CPO Import/Export tool', 'uni-cpo' ),
            'manage_woocommerce',
            'uni-cpo-import-export',
            array($this, 'import_export_page')
        );
    }

    /**
     * Add settings link to plugin list table
     *
     * @param array $links Existing links
     *
     * @return array        Modified links
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=uni-cpo-settings">' . __( 'Settings', 'uni-cpo' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
    }

    /**
     * Build settings fields
     * @return array Fields to be displayed on settings page
     */
    private function settings_fields() {
        $all_roles = wp_roles();
        $role_names = array_merge( $all_roles->role_names, [
            '' => __( 'Guest (unregistered)', 'uni-cpo' ),
        ], [
            'uni_cpo_own_role' => __( 'Own role', 'uni-cpo' ),
        ] );
        $settings['standard'] = array(
            'title'       => __( 'Standard', 'uni-cpo' ),
            'description' => __( 'All standard/general plugin settings', 'uni-cpo' ) . '<span id="standard"></span>',
            'fields'      => array(
                array(
                    'id'          => 'ajax_add_to_cart',
                    'label'       => __( 'Add product to the cart via AJAX', 'uni-cpo' ),
                    'description' => __( 'This option enables adding to cart via AJAX for all the products which use custom options and price calculation.', 'uni-cpo' ),
                    'type'        => 'checkbox',
                    'default'     => '',
                ),
                array(
                    'id'          => 'product_price_container',
                    'label'       => __( 'Custom selector (id/class) for a product price html tag', 'uni-cpo' ),
                    'description' => __( 'By default, the selector for a product price html tag is ".summary.entry-summary .price > .amount bdi, .summary.entry-summary .price ins .amount bdi". For instance, such CSS selector works in Storefront theme. The actual html markup of price tag section may vary, it depends on the theme and you may need to adjust this CSS selector.', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( 'CSS selector', 'uni-cpo' ),
                ),
                array(
                    'id'          => 'product_image_container',
                    'label'       => __( 'Custom selector (id/class) for a product image wrapper html tag', 'uni-cpo' ),
                    'description' => __( 'By default, the native WooCommerce selector for a product image wrapper html tag on a single product page is "figure.woocommerce-product-gallery__wrapper". However, the actual html markup of the image block can change depending the theme and you may need to define yours custom selector by inspecting the main product image and determining the custom CSS used for the wrapper. Reminder: this selector is for element that wraps the main image, not the image ("img" tag) itself!', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( 'CSS selector', 'uni-cpo' ),
                ),
                array(
                    'id'          => 'product_image_size',
                    'label'       => __( 'Image size that is used for single product main image', 'uni-cpo' ),
                    'description' => __( 'By default, this is "woocommerce_single". However the actual thumbnail size used depends on the theme and you may need to choose the correct one. This setting works in conjuction with the previous one and it is important to choose proper image size, so it will be used whenever a customer selects new option in a dropdown option or image select option with an image added to this chosen option.', 'uni-cpo' ),
                    'type'        => 'select',
                    'options'     => uni_cpo_get_image_sizes_list(),
                    'default'     => 'woocommerce_single',
                ),
                array(
                    'id'          => 'product_thumbnails_container',
                    'label'       => __( 'Custom selector (id/class) for a product thumbnails wrapper html tag', 'uni-cpo' ),
                    'description' => __( 'By default, the selector for a product thumbnails wrapper html tag on a single product page is "ol.flex-control-thumbs". However, the actual html markup of the thumbnails block depends on the theme and you may need to define yours custom selector.', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( 'CSS selector', 'uni-cpo' ),
                ),
                array(
                    'id'          => 'change_product_image_in_cart',
                    'label'       => __( 'Change product image in the cart', 'uni-cpo' ),
                    'description' => __( 'This option enables changing product image in the cart when you are using "Imagify", "Colorify" or "Image Conditional Logic"', 'uni-cpo' ),
                    'type'        => 'select',
                    'options'     => array(
                        'on'  => __( 'On', 'uni-cpo' ),
                        'off' => __( 'Off', 'uni-cpo' ),
                    ),
                    'default'     => 'on',
                ),
                array(
                    'id'          => 'display_weight_in_cart',
                    'label'       => __( 'Display weight in the cart', 'uni-cpo' ),
                    'description' => __( 'This option enables displaying product weight in the cart.', 'uni-cpo' ),
                    'type'        => 'checkbox',
                    'default'     => '',
                ),
                array(
                    'id'          => 'display_dimensions_in_cart',
                    'label'       => __( 'Display dimensions in the cart', 'uni-cpo' ),
                    'description' => __( 'This option enables displaying product dimensions in the cart.', 'uni-cpo' ),
                    'type'        => 'checkbox',
                    'default'     => '',
                ),
                array(
                    'id'          => 'range_slider_style',
                    'label'       => __( 'Style for range sliders', 'uni-cpo' ),
                    'description' => __( 'Default style is "HTML 5". This style will be applied for all range slider option instances in the store.', 'uni-cpo' ),
                    'type'        => 'select',
                    'options'     => array(
                        'flat'   => __( 'Flat', 'uni-cpo' ),
                        'modern' => __( 'Modern', 'uni-cpo' ),
                        'html5'  => __( 'HTML5', 'uni-cpo' ),
                        'nice'   => __( 'Nice', 'uni-cpo' ),
                        'simple' => __( 'Simple', 'uni-cpo' ),
                    ),
                    'default'     => 'html5',
                ),
                array(
                    'id'          => 'gmap_api_key',
                    'label'       => __( 'Google Map API Key', 'uni-cpo' ),
                    'description' => __( 'Add Google Map API key in order to use Google Map option', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => '',
                ),
                array(
                    'id'          => 'csv_delimiter',
                    'label'       => __( 'The delimiter usedin your CSV import files', 'uni-cpo' ),
                    'description' => __( 'Default is ";". You can export to CSV with ";" delim in Libre Office. On macOS you are probably using Numbers, so pick "," as delimiter as it is the only option in this case.', 'uni-cpo' ),
                    'type'        => 'select',
                    'options'     => array(
                        ';' => __( 'Use ";" as delimiter', 'uni-cpo' ),
                        ',' => __( 'Use "," as delimiter', 'uni-cpo' ),
                    ),
                    'default'     => ';',
                ),
                array(
                    'id'          => 'order_edit_role',
                    'label'       => __( 'User role when edit orders', 'uni-cpo' ),
                    'description' => __( 'This setting impacts price calculation on edit order screen. Very useful when you, as admin, would like to edit order and set price as for guests or other special role.', 'uni-cpo' ),
                    'type'        => 'select',
                    'options'     => $role_names,
                    'default'     => ';',
                )
            ),
        );
        $settings['file_upload'] = array(
            'title'       => __( 'File upload settings', 'uni-cpo' ),
            'description' => __( 'Settings related to file upload functionality', 'uni-cpo' ) . '<span id="file_upload"></span>',
            'fields'      => array(
                array(
                    'id'          => 'max_file_size',
                    'label'       => __( 'Upload max file size (Mb)', 'uni-cpo' ),
                    'description' => __( 'Global option: max file size that is allowed to be uploaded through File Upload option. Default is 2Mb.', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( '2', 'uni-cpo' ),
                ),
                array(
                    'id'          => 'mime_type',
                    'label'       => __( 'Allowed mime types', 'uni-cpo' ),
                    'description' => __( 'Global option: a comma separated list of allowed mime types as extension names. Default is: "jpg,zip". Important: file types defined here still must comply with allowed MIME types by WP itself. More info here: https://codex.wordpress.org/Function_Reference/get_allowed_mime_types', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( 'jpg,zip', 'uni-cpo' ),
                ),
                array(
                    'id'          => 'file_storage',
                    'label'       => __( 'Files storage', 'uni-cpo' ),
                    'description' => __( '"Local" is set by default', 'uni-cpo' ),
                    'type'        => 'select',
                    'options'     => array(
                        'local'   => __( 'Local', 'uni-cpo' ),
                        'dropbox' => __( 'Dropbox', 'uni-cpo' ),
                        'gdrive'  => __( 'Google Drive', 'uni-cpo' ),
                    ),
                    'default'     => 'local',
                ),
                array(
                    'id'          => 'custom_path_enable',
                    'label'       => __( 'Enables custom local folder for file uploads', 'uni-cpo' ),
                    'description' => __( 'By default, all the files are handled by the standard WP functions and are stored in the same as any regular attachments. This setting is called to separate file uploads via the plugin\'s File Upload option from regular attachments and store them in different folder with custom folders structure. This custom folder is still in "Uploads" folder.', 'uni-cpo' ),
                    'type'        => 'checkbox',
                    'default'     => '',
                    'dependency'  => '#file_storage:is(local)',
                ),
                array(
                    'id'          => 'custom_path',
                    'label'       => __( 'Define custom folders structure for file uploads', 'uni-cpo' ),
                    'description' => __( 'This setting works only if a custom local folder is enabled. The path always starts in the standard "uploads" folder. {{{POST_ID}}} and {{{DATE}}} variables may be used folders structure scheme.', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( 'cpo-uploads/{{{POST_ID}}}/{{{DATE}}}', 'uni-cpo' ),
                    'dependency'  => '#file_storage:is(local)',
                ),
                array(
                    'id'          => 'dropbox_app_key',
                    'label'       => __( 'Dropbox App Key', 'uni-cpo' ),
                    'description' => __( 'Your Dropbox app key. You can find this in your Dropbox app console. Required for OAuth authentication.', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( 'Enter your Dropbox App Key', 'uni-cpo' ),
                    'dependency'  => '#file_storage:is(dropbox)',
                ),
                array(
                    'id'          => 'dropbox_app_secret',
                    'label'       => __( 'Dropbox App Secret', 'uni-cpo' ),
                    'description' => __( 'Your Dropbox app secret. You can find this in your Dropbox app console. Required for OAuth authentication.', 'uni-cpo' ),
                    'type'        => 'password',
                    'default'     => '',
                    'placeholder' => __( 'Enter your Dropbox App Secret', 'uni-cpo' ),
                    'dependency'  => '#file_storage:is(dropbox)',
                ),
                array(
                    'id'          => 'dropbox_auth_status',
                    'label'       => __( 'Authorization Status', 'uni-cpo' ),
                    'description' => __( 'Current OAuth authorization status and controls for managing Dropbox access.', 'uni-cpo' ),
                    'type'        => 'custom',
                    'callback'    => array($this, 'render_dropbox_auth_status'),
                    'dependency'  => '#file_storage:is(dropbox)',
                ),
                array(
                    'id'          => 'gdrive_client_id',
                    'label'       => __( 'Google Drive Client ID', 'uni-cpo' ),
                    'description' => __( 'Enter your Google Drive OAuth2 Client ID from Google Cloud Console.', 'uni-cpo' ),
                    'type'        => 'text',
                    'default'     => '',
                    'placeholder' => __( 'Enter your Google Drive Client ID', 'uni-cpo' ),
                    'dependency'  => '#file_storage:is(gdrive)',
                ),
                array(
                    'id'          => 'gdrive_client_secret',
                    'label'       => __( 'Google Drive Client Secret', 'uni-cpo' ),
                    'description' => __( 'Enter your Google Drive OAuth2 Client Secret from Google Cloud Console.', 'uni-cpo' ),
                    'type'        => 'password',
                    'default'     => '',
                    'placeholder' => __( 'Enter your Google Drive Client Secret', 'uni-cpo' ),
                    'dependency'  => '#file_storage:is(gdrive)',
                ),
                array(
                    'id'          => 'gdrive_auth_status',
                    'label'       => __( 'Authorization Status', 'uni-cpo' ),
                    'description' => __( 'Current OAuth authorization status and controls for managing Google Drive access.', 'uni-cpo' ),
                    'type'        => 'custom',
                    'callback'    => array($this, 'render_gdrive_auth_status'),
                    'dependency'  => '#file_storage:is(gdrive)',
                )
            ),
        );
        $settings['sample_feature'] = array(
            'title'       => __( 'Free sample functionality', 'uni-cpo' ),
            'description' => __( 'Extra functionality "free sample"', 'uni-cpo' ) . '<span id="sample_feature"></span>',
            'fields'      => array(array(
                'id'          => 'free_sample_enable',
                'label'       => __( 'Enable "Free sample" functionality', 'uni-cpo' ),
                'description' => __( 'Enables so called "Free Sample" functionality (adding to cart free products (zero price)) and limits the total number of free products (if set).', 'uni-cpo' ),
                'type'        => 'checkbox',
                'default'     => '',
            ), array(
                'id'          => 'free_samples_limit',
                'label'       => __( '"Free sample" products limit', 'uni-cpo' ),
                'description' => __( 'Sets the maximum total number of free products which can be added to a single order. Set to "0" or leave it empty to allow unlimited samples.', 'uni-cpo' ),
                'type'        => 'text',
                'default'     => '',
                'placeholder' => __( '5', 'uni-cpo' ),
            )),
        );
        $settings = apply_filters( 'uni_cpo_plugin_settings_fields', $settings );
        return $settings;
    }

    /**
     * Register plugin settings
     * @return void
     */
    public function register_settings() {
        // Register the settings group once
        register_setting( 'general_settings', $this->settings_base, array($this, 'validate_settings') );
        if ( is_array( $this->settings ) ) {
            foreach ( $this->settings as $section => $data ) {
                if ( unicpo_fs()->is_not_paying() ) {
                    if ( in_array( $section, $this->exclude_from_free ) ) {
                        continue;
                    }
                }
                // Add section to page
                add_settings_section(
                    $section,
                    $data['title'],
                    array($this, 'settings_section'),
                    'general_settings'
                );
                foreach ( $data['fields'] as $field ) {
                    if ( unicpo_fs()->is_not_paying() ) {
                        if ( in_array( $field['id'], $this->exclude_from_free ) ) {
                            continue;
                        }
                    }
                    // Add field to page
                    add_settings_field(
                        $field['id'],
                        $field['label'],
                        array($this, 'display_field'),
                        'general_settings',
                        $section,
                        array(
                            'field' => $field,
                        )
                    );
                }
            }
        }
    }

    /**
     * Validate settings input
     * 
     * @param array $input Input data from form
     * @return array Sanitized input data
     */
    public function validate_settings( $input ) {
        // Return the input as-is for now
        // Individual fields can have their own validation if needed
        return $input;
    }

    public function settings_section( $section ) {
        $html = '<p> ' . $this->settings[$section['id']]['description'] . '</p>' . "\n";
        echo $html;
    }

    /**
     * Generate HTML for displaying fields
     *
     * @param array $args Field data
     *
     * @return void
     */
    public function display_field( $args ) {
        $field = $args['field'];
        $html = '';
        $option = UniCpo()->get_settings();
        $option_name = $this->settings_base . '[' . $field['id'] . ']';
        $data = '';
        if ( isset( $field['default'] ) ) {
            $data = $field['default'];
            if ( $option ) {
                $data = ( isset( $option[$field['id']] ) ? $option[$field['id']] : $field['default'] );
            }
        }
        switch ( $field['type'] ) {
            case 'text':
            case 'password':
            case 'number':
                $html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . $field['type'] . '" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . $data . '"/>' . "\n";
                break;
            case 'text_secret':
                $html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value=""/>' . "\n";
                break;
            case 'textarea':
                $html .= '<textarea id="' . esc_attr( $field['id'] ) . '" rows="5" cols="50" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '">' . $data . '</textarea><br/>' . "\n";
                break;
            case 'checkbox':
                $checked = '';
                if ( $data && 'on' == $data ) {
                    $checked = 'checked="checked"';
                }
                $html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . $field['type'] . '" name="' . esc_attr( $option_name ) . '" ' . $checked . '/>' . "\n";
                break;
            case 'checkbox_multi':
                foreach ( $field['options'] as $k => $v ) {
                    $checked = false;
                    if ( in_array( $k, $data ) ) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="checkbox" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '[]" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
                }
                break;
            case 'radio':
                foreach ( $field['options'] as $k => $v ) {
                    $checked = false;
                    if ( $k == $data ) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="radio" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
                }
                break;
            case 'select':
                $html .= '<select name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $field['id'] ) . '">';
                foreach ( $field['options'] as $k => $v ) {
                    $selected = false;
                    if ( $k == $data ) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                break;
            case 'select_multi':
                $html .= '<select name="' . esc_attr( $option_name ) . '[]" id="' . esc_attr( $field['id'] ) . '" multiple="multiple">';
                foreach ( $field['options'] as $k => $v ) {
                    $selected = false;
                    if ( in_array( $k, $data ) ) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '" />' . $v . '</label> ';
                }
                $html .= '</select> ';
                break;
            case 'image':
                $image_thumb = '';
                if ( $data ) {
                    $image_thumb = wp_get_attachment_thumb_url( $data );
                }
                $html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
                $html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __( 'Upload an image', 'uni-cpo' ) . '" data-uploader_button_text="' . __( 'Use image', 'uni-cpo' ) . '" class="image_upload_button button" value="' . __( 'Upload new image', 'uni-cpo' ) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __( 'Remove image', 'uni-cpo' ) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";
                break;
            case 'color':
                ?>
                <div
                        class="color-picker"
                        style="position:relative;">
                    <input
                            type="text"
                            name="<?php 
                esc_attr_e( $option_name );
                ?>"
                            class="color"
                            value="<?php 
                esc_attr_e( $data );
                ?>"/>
                    <div
                            style="position:absolute;background:#FFF;z-index:99;border-radius:100%;"
                            class="colorpicker"></div>
                </div>
                <?php 
                break;
            case 'custom':
                // Call the custom callback function
                if ( isset( $field['callback'] ) && is_callable( $field['callback'] ) ) {
                    call_user_func( $field['callback'], $field );
                }
                break;
        }
        switch ( $field['type'] ) {
            case 'checkbox_multi':
            case 'radio':
            case 'select_multi':
                $html .= '<br/><span class="description">' . $field['description'] . '</span>';
                break;
            default:
                $html .= '<label for="' . esc_attr( $field['id'] ) . '"><span class="description">' . $field['description'] . '</span></label>' . "\n";
                break;
        }
        echo $html;
    }

    /**
     * Validate individual settings field
     *
     * @param string $data Inputted value
     *
     * @return string       Validated value
     */
    public function validate_field( $data ) {
        if ( $data && strlen( $data ) > 0 && $data != '' ) {
            $data = urlencode( strtolower( str_replace( ' ', '-', $data ) ) );
        }
        return $data;
    }

    /**
     * Load settings page content
     * @return void
     */
    public function settings_page() {
        // Build page HTML
        $html = '<div class="wrap" id="plugin_settings">' . "\n";
        $html .= '<h1>' . esc_html__( 'Uni CPO Plugin Settings', 'uni-cpo' ) . '</h1>' . "\n";
        $html .= '<p class="uni-cpo-setup-actions">
						<a class="button button-primary button-large" href="' . esc_url( admin_url( 'post-new.php?post_type=product&cpo-tutorial=true' ) ) . '">' . esc_html__( 'Uni CPO product basic setup tutorial', 'uni-cpo' ) . '</a>
					</p>';
        $html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";
        // Setup navigation
        $html .= '<ul id="settings-sections" class="subsubsub hide-if-no-js">' . "\n";
        $html .= '<li><a class="tab all current" href="#all">' . __( 'All', 'uni-cpo' ) . '</a></li>' . "\n";
        foreach ( $this->settings as $section => $data ) {
            if ( unicpo_fs()->is_not_paying() ) {
                if ( in_array( $section, $this->exclude_from_free ) ) {
                    continue;
                }
            }
            $html .= '<li>| <a class="tab" href="#' . $section . '">' . $data['title'] . '</a></li>' . "\n";
        }
        $html .= '</ul>' . "\n";
        $html .= '<div class="clear"></div>' . "\n";
        // Get settings fields
        ob_start();
        settings_fields( 'general_settings' );
        do_settings_sections( 'general_settings' );
        $html .= ob_get_clean();
        $html .= '<p class="submit">' . "\n";
        $html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings', 'uni-cpo' ) ) . '" />' . "\n";
        $html .= '</p>' . "\n";
        $html .= '</form>' . "\n";
        $html .= '</div>' . "\n";
        echo $html;
    }

    /**
     * Uni CPO data manager functionality
     * @return void
     */
    public function manager_page() {
        // Build page HTML
        $html = '<div class="wrap" id="plugin_settings">' . "\n";
        $html .= '<h1>' . esc_html__( 'Uni CPO data manager tool', 'uni-cpo' ) . '</h1>' . "\n";
        $html .= '<h2>' . esc_html__( 'Add/edit product related general settings', 'uni-cpo' ) . '</h2>' . "\n";
        $html .= '<p>' . esc_html__( 'This tool makes it possible to edit many CPO related settings for many products at once.', 'uni-cpo' ) . '</p>';
        $html .= '<p><strong>' . esc_html__( 'This functionality is included in PRO version of the plugin', 'uni-cpo' ) . '</strong></p>';
        echo $html;
    }

    /**
     * Bulk edit functionality
     * @return void
     */
    public function bulk_edit_page() {
        // Build page HTML
        $html = '<div class="wrap" id="plugin_settings">' . "\n";
        $html .= '<h1>' . esc_html__( 'Uni CPO Bulk Edit tool', 'uni-cpo' ) . '</h1>' . "\n";
        $html .= '<p>' . esc_html__( 'This page is dedicated to copying and pasting of Uni CPO related settings in many products at once.', 'uni-cpo' ) . '</p>';
        $html .= '<p><strong>' . esc_html__( 'This functionality is included in PRO version of the plugin', 'uni-cpo' ) . '</strong></p>';
        echo $html;
    }

    /**
     * Import-export functionality
     * @return void
     */
    public function import_export_page() {
        // Build page HTML
        $html = '<div class="wrap" id="plugin_settings">' . "\n";
        $html .= '<h1>' . esc_html__( 'Uni CPO Import/Export tool', 'uni-cpo' ) . '</h1>' . "\n";
        $html .= '<h2>' . esc_html__( 'Import/Export of suboptions', 'uni-cpo' ) . '</h2>' . "\n";
        $html .= '<p>' . esc_html__( 'This section is dedicated to import/export functionality for suboptions of the following option types: Radio, Checkboxes and Select', 'uni-cpo' ) . '</p>';
        $html .= '<p><strong>' . esc_html__( 'This functionality is included in PRO version of the plugin', 'uni-cpo' ) . '</strong></p>';
        echo $html;
    }

    /**
     * Render Dropbox authorization status and button
     *
     * @param array $field Field configuration
     * @return void
     */
    public function render_dropbox_auth_status( $field ) {
        // Display success/error messages
        if ( isset( $_GET['success'] ) ) {
            switch ( $_GET['success'] ) {
                case 'authorized':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __( '✓ Dropbox authorization successful!', 'uni-cpo' ) . '</p></div>';
                    break;
                case 'revoked':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __( '✓ Dropbox authorization revoked successfully.', 'uni-cpo' ) . '</p></div>';
                    break;
            }
        }
        if ( isset( $_GET['error'] ) ) {
            $error = sanitize_text_field( $_GET['error'] );
            switch ( $error ) {
                case 'missing_credentials':
                    echo '<div class="notice notice-error is-dismissible"><p>' . __( '❌ Please configure your Dropbox App Key and App Secret first.', 'uni-cpo' ) . '</p></div>';
                    break;
                case 'no_code':
                    echo '<div class="notice notice-error is-dismissible"><p>' . __( '❌ Authorization failed: No authorization code received from Dropbox.', 'uni-cpo' ) . '</p></div>';
                    break;
                default:
                    echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( __( '❌ Dropbox Error: %s', 'uni-cpo' ), esc_html( $error ) ) . '</p></div>';
                    break;
            }
        }
        $settings = UniCpo()->get_settings();
        $app_key = ( isset( $settings['dropbox_app_key'] ) ? $settings['dropbox_app_key'] : '' );
        $app_secret = ( isset( $settings['dropbox_app_secret'] ) ? $settings['dropbox_app_secret'] : '' );
        $access_token = get_option( 'uni_cpo_dropbox_access_token', '' );
        $refresh_token = get_option( 'uni_cpo_dropbox_refresh_token', '' );
        $expires_at = get_option( 'uni_cpo_dropbox_expires_at', 0 );
        $is_configured = !empty( $app_key ) && !empty( $app_secret );
        $is_authorized = !empty( $access_token ) && !empty( $refresh_token );
        $is_expired = $is_authorized && time() > $expires_at;
        // Generate and display the redirect URI that needs to be configured in Dropbox app
        $redirect_uri = admin_url( 'admin-post.php?action=uni_cpo_dropbox_callback' );
        echo '<div id="dropbox_auth_status" style="background: #f0f6fc; border: 1px solid #0073aa; padding: 15px; margin-bottom: 15px; border-radius: 4px;">';
        echo '<h4 style="margin-top: 0; color: #0073aa;">' . __( '📋 Dropbox App Configuration Required', 'uni-cpo' ) . '</h4>';
        echo '<p>' . __( 'Before authorizing, you must add this Redirect URI to your Dropbox app settings:', 'uni-cpo' ) . '</p>';
        echo '<div style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px; font-family: monospace; font-size: 13px; margin: 10px 0; word-break: break-all;">';
        echo '<strong>' . esc_html( $redirect_uri ) . '</strong>';
        echo '</div>';
        echo '<p><small>' . __( '👆 Copy this URL and add it to the "Redirect URIs" section in your Dropbox app console at <a href="https://www.dropbox.com/developers/apps" target="_blank">https://www.dropbox.com/developers/apps</a>', 'uni-cpo' ) . '</small></p>';
        echo '</div>';
        echo '<div id="dropbox-auth-status">';
        if ( !$is_configured ) {
            echo '<div class="notice notice-warning inline">';
            echo '<p>' . __( 'Please enter your Dropbox App Key and App Secret above, then save settings before authorizing.', 'uni-cpo' ) . '</p>';
            echo '</div>';
        } elseif ( $is_authorized ) {
            echo '<div class="notice notice-success inline">';
            echo '<p>' . __( '✓ Authorized and ready to use.', 'uni-cpo' ) . '</p>';
            echo '</div>';
            echo '<button type="button" id="dropbox-revoke-btn" class="button button-secondary">' . __( 'Revoke Authorization', 'uni-cpo' ) . '</button>';
        } else {
            echo '<div class="notice notice-error inline">';
            echo '<p>' . __( 'Not authorized. Click the button below to authorize with Dropbox.', 'uni-cpo' ) . '</p>';
            echo '</div>';
            echo '<button type="button" id="dropbox-authorize-btn" class="button button-primary">' . __( 'Authorize with Dropbox', 'uni-cpo' ) . '</button>';
        }
        echo '</div>';
        // Add JavaScript for authorization handling
        echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#dropbox-authorize-btn").click(function() {
                var app_key = $("#dropbox_app_key").val();
                var app_secret = $("#dropbox_app_secret").val();
                
                if (!app_key || !app_secret) {
                    alert("' . esc_js( __( 'Please save your App Key and App Secret first.', 'uni-cpo' ) ) . '");
                    return;
                }
                
                window.location.href = "' . admin_url( 'admin-post.php?action=uni_cpo_dropbox_auth' ) . '";
            });
            
            $("#dropbox-revoke-btn").click(function() {
                if (confirm("' . esc_js( __( 'Are you sure you want to revoke Dropbox authorization?', 'uni-cpo' ) ) . '")) {
                    window.location.href = "' . admin_url( 'admin-post.php?action=uni_cpo_dropbox_revoke' ) . '";
                }
            });
        });
        </script>';
    }

    /**
     * Render Google Drive authorization status and button
     *
     * @param array $field Field configuration
     * @return void
     */
    public function render_gdrive_auth_status( $field ) {
        // Display success/error messages
        if ( isset( $_GET['success'] ) ) {
            switch ( $_GET['success'] ) {
                case 'gdrive_authorized':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __( '✓ Google Drive authorization successful!', 'uni-cpo' ) . '</p></div>';
                    break;
                case 'gdrive_revoked':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __( '✓ Google Drive authorization revoked successfully.', 'uni-cpo' ) . '</p></div>';
                    break;
            }
        }
        if ( isset( $_GET['error'] ) ) {
            $error = sanitize_text_field( $_GET['error'] );
            switch ( $error ) {
                case 'gdrive_missing_credentials':
                    echo '<div class="notice notice-error is-dismissible"><p>' . __( '❌ Please configure your Google Drive Client ID and Client Secret first.', 'uni-cpo' ) . '</p></div>';
                    break;
                case 'gdrive_no_code':
                    echo '<div class="notice notice-error is-dismissible"><p>' . __( '❌ Google Drive Authorization failed: No authorization code received from Google.', 'uni-cpo' ) . '</p></div>';
                    break;
                case 'gdrive_invalid_state':
                    echo '<div class="notice notice-error is-dismissible"><p>' . __( '❌ Google Drive Authorization failed: Invalid security token. This could be due to:<br>1. The authorization took too long (token expired after 5 minutes)<br>2. Multiple authorization attempts<br>3. Browser session issues<br><br>Please try authorizing again.', 'uni-cpo' ) . '</p></div>';
                    break;
                default:
                    echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( __( '❌ Google Drive Error: %s', 'uni-cpo' ), esc_html( $error ) ) . '</p></div>';
                    break;
            }
        }
        $settings = UniCpo()->get_settings();
        $client_id = ( isset( $settings['gdrive_client_id'] ) ? $settings['gdrive_client_id'] : '' );
        $client_secret = ( isset( $settings['gdrive_client_secret'] ) ? $settings['gdrive_client_secret'] : '' );
        $access_token = get_option( 'uni_cpo_gdrive_access_token', '' );
        $refresh_token = get_option( 'uni_cpo_gdrive_refresh_token', '' );
        $is_configured = !empty( $client_id ) && !empty( $client_secret );
        $is_authorized = !empty( $access_token ) && !empty( $refresh_token );
        // Generate and display the redirect URI that needs to be configured in Google Cloud Console
        $redirect_uri = admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&gdrive_callback=1' );
        echo '<div id="gdrive_auth_status" style="background: #f0f6fc; border: 1px solid #4285f4; padding: 15px; margin-bottom: 15px; border-radius: 4px;">';
        echo '<h4 style="margin-top: 0; color: #4285f4;">' . __( '📋 Google Cloud Console Configuration Required', 'uni-cpo' ) . '</h4>';
        echo '<p>' . __( 'Before authorizing, you must add this Redirect URI to your Google Cloud Console OAuth2 settings:', 'uni-cpo' ) . '</p>';
        echo '<div style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px; font-family: monospace; font-size: 13px; margin: 10px 0; word-break: break-all;">';
        echo '<strong>' . esc_html( $redirect_uri ) . '</strong>';
        echo '</div>';
        echo '<p><small>' . __( '👆 Copy this URL and add it to the "Authorized redirect URIs" section in your Google Cloud Console at <a href="https://console.cloud.google.com/apis/credentials" target="_blank">https://console.cloud.google.com/apis/credentials</a>', 'uni-cpo' ) . '</small></p>';
        echo '</div>';
        echo '<div id="gdrive-auth-status">';
        if ( !$is_configured ) {
            echo '<div class="notice notice-warning inline">';
            echo '<p>' . __( 'Please enter your Google Drive Client ID and Client Secret above, then save settings before authorizing.', 'uni-cpo' ) . '</p>';
            echo '</div>';
        } elseif ( $is_authorized ) {
            echo '<div class="notice notice-success inline">';
            echo '<p>' . __( '✓ Authorized and ready to use.', 'uni-cpo' ) . '</p>';
            echo '</div>';
            echo '<button type="button" id="gdrive-revoke-btn" class="button button-secondary">' . __( 'Revoke Authorization', 'uni-cpo' ) . '</button>';
        } else {
            echo '<div class="notice notice-error inline">';
            echo '<p>' . __( 'Not authorized. Click the button below to authorize with Google Drive.', 'uni-cpo' ) . '</p>';
            echo '</div>';
            echo '<button type="button" id="gdrive-authorize-btn" class="button button-primary">' . __( 'Authorize with Google Drive', 'uni-cpo' ) . '</button>';
        }
        echo '</div>';
        // Add JavaScript for authorization handling
        echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#gdrive-authorize-btn").click(function() {
                window.location.href = "' . admin_url( 'admin-post.php?action=uni_cpo_gdrive_authorize' ) . '";
            });
            
            $("#gdrive-revoke-btn").click(function() {
                if (confirm("' . __( 'Are you sure you want to revoke Google Drive authorization?', 'uni-cpo' ) . '")) {
                    window.location.href = "' . admin_url( 'admin-post.php?action=uni_cpo_gdrive_revoke' ) . '";
                }
            });
        });
        </script>';
    }

}
