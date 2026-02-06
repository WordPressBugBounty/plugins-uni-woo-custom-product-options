<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Uni_Cpo Class
 */
final class Uni_Cpo {
    /**
     * Uni_Cpo version.
     *
     * @var string
     */
    public $version = '4.9.60';

    /**
     * The single instance of the class.
     *
     * @var Uni_Cpo
     */
    protected static $_instance = null;

    /**
     * Option factory instance.
     *
     * @var Uni_Cpo_Option_Factory
     */
    public $option_factory = null;

    /**
     * Module factory instance.
     *
     * @var Uni_Cpo_Module_Factory
     */
    public $module_factory = null;

    /**
     * Plugin's settings
     *
     * @var Uni_Cpo_Option_Factory
     */
    public $settings_scheme = [];

    /**
     * Is pro
     *
     * @var Uni_Cpo_Option_Factory
     */
    protected $is_pro = false;

    protected $debug_mode = false;

    /**
     *
     */
    protected $var_slug;

    protected $nov_slug;

    protected $builder_id;

    private static $plugin_updates = array();

    /**
     * Throw error on object clone
     *
     * The whole idea of the singleton design pattern is that there is a single
     * object therefore, we don't want the object to be cloned.
     *
     * @return void
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'uni-cpo' ), '1.0.0' );
    }

    /**
     * Disable unserializing of the class
     *
     * @return void
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'uni-cpo' ), '1.0.0' );
    }

    /**
     * Main Uni_Cpo Instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Uni_Cpo Constructor.
     */
    public function __construct() {
        $this->define_constants();
        $this->is_pro = unicpo_fs()->is__premium_only();
        $this->includes();
        $this->init_hooks();
        add_action( 'activated_plugin', array($this, 'activation') );
        $this->var_slug = 'uni_cpo_';
        $this->nov_slug = 'uni_nov_cpo_';
        $this->builder_id = 'uni_cpo_options';
        if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
            $this->debug_mode = true;
        }
    }

    /**
     *  Init hooks
     */
    private function init_hooks() {
        add_action( 'init', array($this, 'init'), 0 );
    }

    /**
     * Define Uni_Cpo Constants.
     */
    private function define_constants() {
        $upload_dir = wp_upload_dir();
        $plugin_settings = $this->get_settings();
        $this->define( 'UNI_CPO_PLUGIN_FILE', __FILE__ );
        $this->define( 'UNI_CPO_ABSPATH', dirname( __FILE__ ) . '/' );
        $this->define( 'UNI_CPO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
        $this->define( 'UNI_CPO_VERSION', $this->version );
        $this->define( 'UNI_CPO_CSS_DIR', wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'cpo-css' ) );
        $this->define( 'UNI_CPO_CSS_URI', trailingslashit( $upload_dir['baseurl'] ) . 'cpo-css' );
        $this->define( 'UNI_CPO_TEMP_DIR', wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'cpo_temp' ) );
    }

    /**
     * Define constant if not already set.
     *
     * @param string $name
     * @param string|bool $value
     */
    private function define( $name, $value ) {
        if ( !defined( $name ) ) {
            define( $name, $value );
        }
    }

    /**
     * What type of request is this?
     *
     * @param string $type admin, ajax, cron or frontend.
     *
     * @return bool
     */
    private function is_request( $type ) {
        switch ( $type ) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined( 'DOING_AJAX' );
            case 'cron':
                return defined( 'DOING_CRON' );
            case 'frontend':
                return (!is_admin() || defined( 'DOING_AJAX' )) && !defined( 'DOING_CRON' );
        }
    }

    /**
     *  Includes
     */
    public function includes() {
        if ( $this->is_pro() ) {
            // Initialize composer autoloader
            $this->init_autoloader();
        }
        //
        include_once UNI_CPO_ABSPATH . 'includes/abstracts/abstract-uni-cpo-data.php';
        include_once UNI_CPO_ABSPATH . 'includes/abstracts/abstract-uni-cpo-option.php';
        include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-option-factory.php';
        include_once UNI_CPO_ABSPATH . 'includes/abstracts/abstract-uni-cpo-module.php';
        include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-module-factory.php';
        include_once UNI_CPO_ABSPATH . 'includes/abstracts/abstract-uni-cpo-setting.php';
        //
        include_once UNI_CPO_ABSPATH . 'includes/interfaces/class-uni-cpo-object-data-store-interface.php';
        include_once UNI_CPO_ABSPATH . 'includes/interfaces/class-uni-cpo-option-data-store-interface.php';
        include_once UNI_CPO_ABSPATH . 'includes/interfaces/class-uni-cpo-option-interface.php';
        include_once UNI_CPO_ABSPATH . 'includes/interfaces/class-uni-cpo-module-data-store-interface.php';
        include_once UNI_CPO_ABSPATH . 'includes/interfaces/class-uni-cpo-module-interface.php';
        include_once UNI_CPO_ABSPATH . 'includes/interfaces/class-uni-cpo-setting-interface.php';
        //
        include_once UNI_CPO_ABSPATH . 'includes/data-stores/class-uni-cpo-data-store.php';
        include_once UNI_CPO_ABSPATH . 'includes/data-stores/class-uni-cpo-data-store-wp.php';
        include_once UNI_CPO_ABSPATH . 'includes/data-stores/class-uni-cpo-option-data-store-cpt.php';
        include_once UNI_CPO_ABSPATH . 'includes/data-stores/class-uni-cpo-module-data-store-cpt.php';
        //
        include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-data-exception.php';
        //
        include_once UNI_CPO_ABSPATH . 'includes/uni-cpo-option-functions.php';
        // TODO differentiate inclusion of files when edit mode on or off
        if ( $this->is_request( 'frontend' ) || $this->is_request( 'admin' ) || $this->is_request( 'cron' ) ) {
            // row / column / modules
            include_once UNI_CPO_ABSPATH . 'includes/modules/class-uni-cpo-module-row.php';
            include_once UNI_CPO_ABSPATH . 'includes/modules/class-uni-cpo-module-column.php';
            include_once UNI_CPO_ABSPATH . 'includes/modules/class-uni-cpo-module-button.php';
            include_once UNI_CPO_ABSPATH . 'includes/modules/class-uni-cpo-module-text.php';
            include_once UNI_CPO_ABSPATH . 'includes/modules/class-uni-cpo-module-image.php';
            // options
            include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-text-area.php';
            include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-text-input.php';
            include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-radio.php';
            include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-select.php';
            if ( $this->is_pro() ) {
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-checkbox.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-file-upload.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-multi-file-upload.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-datepicker.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-range-slider.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-dynamic-notice.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-matrix.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-extra-cart-button.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-google-map.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-distance-by-postcode.php';
                include_once UNI_CPO_ABSPATH . 'includes/options/class-uni-cpo-option-font-preview.php';
                $option_classes_pro = apply_filters( 'uni_cpo_option_classes_pro', [] );
                if ( !empty( $option_classes_pro ) ) {
                    foreach ( $option_classes_pro as $option_class ) {
                        include_once $option_class;
                    }
                }
            }
            $option_classes = apply_filters( 'uni_cpo_option_classes', [] );
            if ( !empty( $option_classes ) ) {
                foreach ( $option_classes as $option_class ) {
                    include_once $option_class;
                }
            }
        }
        if ( $this->is_request( 'frontend' ) || $this->is_request( 'cron' ) ) {
            // settings
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-width-type.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-width.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-width-px.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-content-width.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-height-type.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-height.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-height-px.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-vertical-align.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-color.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-color-from.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-color-to.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-color-top.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-color-bottom.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-color-hover.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-color-active.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-text-align.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-text-align-label.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-font-family.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-font-style.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-font-weight.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-font-size.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-font-size-label.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-font-size-desc.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-font-size-px.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-letter-spacing.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-line-height.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-background-type.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-background-color.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-background-hover-color.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-background-image.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-border-top.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-border-bottom.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-border-left.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-border-right.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-border-unit.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-margin.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-padding.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-id-name.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-class-name.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-offset-px.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-gap-px.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-display.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-flex-direction.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-justify-content.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-align-items.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-gap.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-flex-wrap.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-float.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-content.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-align.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-href.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-target.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-rel.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-radius.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-image.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-divider-style.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-sync.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-slug.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-required.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-datepicker-disabled.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-timepicker.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-timepicker-type.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-time-min.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-time-max.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-minute-step.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-type.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-min-val.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-max-val.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-step-val.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-def-val.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-min-chars.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-max-chars.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-rate.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-label.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-label-tag.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-order-label.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-tooltip.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-tooltip.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-tooltip-class.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-tooltip-image.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-tooltip-type.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-enable-cartedit.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-select-options.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-radio-options.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-mode-radio.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-geom-radio.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-upload-mode.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-max-filesize.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-max-files.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-mime-types.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-mode-checkbox.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-geom-checkbox.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-changeimage.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-order-visibility.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-addtocart-mode.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-samples-mode.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-origin-postcode.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-postcode-type.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-postcode-country.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-fontsampler-id.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-fc.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-fc-default.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-fc-scheme.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-validation-msg.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-vc-extra.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-vc.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-vc-scheme.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-sc.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-sc-default.php';
            include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-sc-scheme.php';
            if ( $this->is_pro() ) {
                // radio
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-encoded-image.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-resetbutton.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-resetbutton-text.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-is-imagify.php';
                // date picker
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-date-type.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-day-night.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-date-min.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-date-max.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-date-conjunction.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-disabled-dates.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-date-rules.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-first-day-of-week.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-date-format.php';
                // range slider
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-range-type.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-range-grid.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-range-input.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-range-from.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-range-to.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-range-prefix.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-range-postfix.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-custom-values.php';
                // dynamic notice
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-notice-text.php';
                // matrix
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-matrix-data.php';
                // map
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-map-center.php';
                include_once UNI_CPO_ABSPATH . 'includes/settings/class-uni-cpo-setting-cpo-map-zoom.php';
                $settings_classes_pro = apply_filters( 'uni_cpo_settings_classes_pro', [] );
                if ( !empty( $settings_classes_pro ) ) {
                    foreach ( $settings_classes_pro as $settings_class ) {
                        include_once $settings_class;
                    }
                }
            }
            $settings_classes = apply_filters( 'uni_cpo_settings_classes', [] );
            if ( !empty( $settings_classes ) ) {
                foreach ( $settings_classes as $settings_class ) {
                    include_once $settings_class;
                }
            }
            // common js templates
            include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-templates.php';
        }
        if ( $this->is_request( 'admin' ) || $this->is_request( 'ajax' ) ) {
        }
        if ( $this->is_request( 'ajax' ) ) {
            include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-ajax.php';
        }
        include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-frontend-scripts.php';
        include_once UNI_CPO_ABSPATH . 'includes/admin/uni-cpo-admin-functions.php';
        include_once UNI_CPO_ABSPATH . 'includes/admin/class-uni-cpo-admin-pointers.php';
        include_once UNI_CPO_ABSPATH . 'includes/admin/class-uni-cpo-plugin-settings.php';
        include_once UNI_CPO_ABSPATH . 'includes/class-eval-math.php';
        include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-post-types.php';
        include_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-product.php';
        include_once UNI_CPO_ABSPATH . 'includes/uni-cpo-core-functions.php';
    }

    /**
     * Init
     */
    public function init() {
        // Before init action.
        do_action( 'before_uni_cpo_init' );
        $this->check_version();
        // Multilanguage support
        $this->load_plugin_textdomain();
        Uni_Cpo_Product::init();
        //
        $this->option_factory = new Uni_Cpo_Option_Factory();
        $this->module_factory = new Uni_Cpo_Module_Factory();
        $this->settings_scheme = new Uni_Cpo_Plugin_Settings(__FILE__);
        add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts'), 10 );
        // Dropbox OAuth actions
        add_action( 'admin_post_uni_cpo_dropbox_auth', array($this, 'handle_dropbox_auth') );
        add_action( 'admin_post_uni_cpo_dropbox_callback', array($this, 'handle_dropbox_callback') );
        add_action( 'admin_post_uni_cpo_dropbox_revoke', array($this, 'handle_dropbox_revoke') );
        // Google Drive OAuth actions
        add_action( 'admin_post_uni_cpo_gdrive_authorize', array($this, 'handle_gdrive_authorize') );
        add_action( 'admin_post_uni_cpo_gdrive_revoke', array($this, 'handle_gdrive_revoke') );
        // Google Drive OAuth callback (handled in admin page)
        add_action( 'admin_init', array($this, 'handle_gdrive_callback') );
        // Init action.
        do_action( 'uni_cpo_init' );
        if ( isset( $_GET['order_again'] ) ) {
            add_filter(
                'woocommerce_add_to_cart_validation',
                'uni_cpo_woocommerce_add_to_cart_validation',
                10,
                6
            );
        }
        add_shortcode( 'unicpo_form', array($this, 'product_form_shortcode') );
        add_action(
            'woocommerce_product_options_sku',
            array($this, 'info_about_dynamic_discount'),
            10,
            11
        );
    }

    /**
     * @return void
     */
    public function info_about_dynamic_discount() {
        if ( !function_exists( 'UniCpoCustomSku' ) ) {
            echo '<p>' . sprintf( __( 'Uni CPO users can use <strong>dynamic SKU</strong> functionality after installing %s add-on.', 'uni-cpo' ), '<a href="https://moomoo-agency.gitbook.io/uni-cpo-4-documentation/uni-cpo-add-ons/dynamic-sku-for-woocommerce" target="_blank">' . __( '"Custom SKU"', 'uni-cpo' ) . '</a>' ) . '</p>';
        }
    }

    /**
     * @return void
     */
    public function product_form_shortcode() {
        do_action( 'woocommerce_before_add_to_cart_button' );
    }

    /**
     *  Get the builder container CSS ID
     */
    public function get_builder_id() {
        return $this->builder_id;
    }

    /**
     *  get_var_slug
     */
    public function get_var_slug() {
        return $this->var_slug;
    }

    /**
     *  get_nov_slug
     */
    public function get_nov_slug() {
        return $this->nov_slug;
    }

    /**
     *  is_debug
     */
    public function is_debug() {
        return $this->debug_mode;
    }

    /**
     * Scripts and styles used in back end
     * @since  1.0.0
     */
    function admin_scripts() {
        $screen = get_current_screen();
        $screen_id = ( $screen ? $screen->id : '' );
        $post_type = ( $screen ? $screen->post_type : '' );
        $localizations = Uni_Cpo_Frontend_Scripts::get_localizations();
        $order_meta_boxes = wc_get_order_types( 'order-meta-boxes' );
        if ( in_array( $screen_id, array('product', 'edit-product') ) ) {
            wp_enqueue_style(
                'uni-cpo-styles-product',
                $this->plugin_url() . '/assets/css/admin-product.css',
                false,
                UNI_CPO_VERSION,
                'all'
            );
            wp_register_script(
                'uni-cpo-scripts-product',
                $this->plugin_url() . '/assets/js/admin-product.js',
                array(),
                UNI_CPO_VERSION
            );
            wp_enqueue_script( 'uni-cpo-scripts-product' );
        } elseif ( in_array( str_replace( 'edit-', '', $screen_id ), $order_meta_boxes ) || in_array( str_replace( 'edit-', '', $post_type ), $order_meta_boxes ) ) {
            wp_register_script(
                'moment',
                $this->plugin_url() . '/includes/vendors/moment/moment.min.js',
                array(),
                '2.19.1'
            );
            wp_register_script(
                'flatpickr',
                $this->plugin_url() . '/includes/vendors/flatpickr/flatpickr.js',
                array(),
                '4.6.13'
            );
            wp_register_script(
                'parsleyjs',
                $this->plugin_url() . '/includes/vendors/parsleyjs/parsley.min.js',
                array('jquery'),
                '2.8.0'
            );
            wp_register_script(
                'parsley-localization',
                $this->plugin_url() . '/includes/vendors/parsleyjs/i18n/en.js',
                array('parsleyjs'),
                '2.8.0'
            );
            wp_register_script(
                'uni-cpo-scripts-order',
                $this->plugin_url() . '/assets/js/admin-order.js',
                array(
                    'wc-admin-order-meta-boxes',
                    'moment',
                    'flatpickr',
                    'parsleyjs'
                ),
                UNI_CPO_VERSION
            );
            wp_enqueue_script( 'uni-cpo-scripts-order' );
            // media uploader
            wp_enqueue_media();
            wp_localize_script( 'parsleyjs', 'uni_parsley_loc', $localizations['parsleyjs'] );
            $uni_cpo_i18n = apply_filters( 'uni_cpo_i18n_admin_strings', array(
                'flatpickr' => $localizations['flatpickr'],
                'no_file'   => __( 'No file uploaded', 'uni-cpo' ),
            ) );
            wp_localize_script( 'uni-cpo-scripts-order', 'unicpo_i18n', $uni_cpo_i18n );
            wp_enqueue_style(
                'flatpickr',
                $this->plugin_url() . '/includes/vendors/flatpickr/flatpickr.css',
                false,
                '4.3.2',
                'all'
            );
            wp_enqueue_style(
                'uni-cpo-font-awesome',
                $this->plugin_url() . '/includes/vendors/font-awesome/css/fontawesome-all.min.css',
                false,
                '5.0.10',
                'all'
            );
            wp_enqueue_style(
                'uni-cpo-styles-order',
                $this->plugin_url() . '/assets/css/admin-order.css',
                false,
                UNI_CPO_VERSION,
                'all'
            );
        } elseif ( in_array( $screen_id, array('woocommerce_page_uni-cpo-import-export', 'woocommerce_page_uni-cpo-settings', 'woocommerce_page_uni-cpo-bulk-edit') ) ) {
            wp_enqueue_style(
                'uni-cpo-styles-settings',
                $this->plugin_url() . '/assets/css/admin-settings.css',
                false,
                UNI_CPO_VERSION,
                'all'
            );
            wp_register_script(
                'uni-cpo-scripts-settings',
                $this->plugin_url() . '/assets/js/admin-settings.js',
                array('jquery'),
                UNI_CPO_VERSION
            );
            wp_enqueue_script( 'uni-cpo-scripts-settings' );
        } elseif ( in_array( $screen_id, array('woocommerce_page_uni-cpo-manager') ) ) {
            wp_register_script(
                'uni-cpo-manager',
                $this->plugin_url() . '/assets/js/unicpo-managers.js',
                array(),
                UNI_CPO_VERSION,
                true
            );
            wp_enqueue_script( 'uni-cpo-manager' );
            $manager = array(
                'products' => get_products_data_for_manager(),
                'options'  => uni_cpo_get_posts_slugs(),
                'currency' => get_woocommerce_currency_symbol(),
            );
            wp_localize_script( 'uni-cpo-manager', 'unicpoManager', $manager );
        } elseif ( in_array( $screen_id, array('woocommerce_page_uni-cpo-settings') ) ) {
            wp_enqueue_style( 'farbtastic' );
            wp_enqueue_script( 'farbtastic' );
            wp_enqueue_media();
            wp_register_script(
                'uni-cpo-admin-utils',
                $this->plugin_url() . '/assets/js/admin-utils.js',
                array('farbtastic', 'jquery'),
                UNI_CPO_VERSION
            );
            wp_enqueue_script( 'uni-cpo-admin-utils' );
        }
    }

    /**
     * load_plugin_textdomain()
     */
    public function load_plugin_textdomain() {
        $locale = apply_filters( 'plugin_locale', get_locale(), 'uni-cpo' );
        load_textdomain( 'uni-cpo', WP_LANG_DIR . '/uni-woo-custom-product-options/uni-cpo-' . $locale . '.mo' );
        load_plugin_textdomain( 'uni-cpo', false, plugin_basename( dirname( __FILE__ ) ) . "/languages" );
    }

    /**
     * check_version()
     */
    public function check_version() {
        $current_version = get_option( 'uni_cpo_version', null );
        if ( is_null( $current_version ) ) {
            update_option( 'uni_cpo_version', $this->version );
        }
        if ( !defined( 'IFRAME_REQUEST' ) && !empty( $plugin_updates ) && version_compare( $current_version, max( array_keys( self::$plugin_updates ) ), '<' ) ) {
            $this->update_plugin();
            do_action( 'uni_cpo_updated' );
        }
    }

    /**
     * is_pro()
     */
    public function is_pro() {
        return $this->is_pro;
    }

    /**
     * default_settings()
     */
    function default_settings() {
        return array(
            'ajax_add_to_cart'             => '',
            'product_price_container'      => '.summary.entry-summary .price > .amount bdi, .summary.entry-summary .price ins .amount bdi',
            'product_image_container'      => 'div.woocommerce-product-gallery__wrapper',
            'product_image_size'           => 'woocommerce_single',
            'product_thumbnails_container' => 'ol.flex-control-thumbs',
            'gmap_api_key'                 => '',
            'csv_delimiter'                => ';',
            'order_edit_role'              => 'administrator',
            'change_product_image_in_cart' => 'on',
            'display_weight_in_cart'       => '',
            'display_dimensions_in_cart'   => '',
            'range_slider_style'           => 'html5',
            'max_file_size'                => 2,
            'mime_type'                    => 'jpg,zip',
            'file_storage'                 => 'local',
            'custom_path_enable'           => '',
            'custom_path'                  => '',
            'dropbox_app_key'              => '',
            'dropbox_app_secret'           => '',
            'free_sample_enable'           => '',
            'free_samples_limit'           => '',
        );
    }

    /**
     * get_settings()
     */
    function get_settings() {
        $settings = get_option( 'uni_cpo_settings_general', $this->default_settings() );
        // Ensure $settings is an array to prevent array_merge() errors
        if ( !is_array( $settings ) ) {
            $settings = $this->default_settings();
        }
        return array_merge( $this->default_settings(), $settings );
    }

    /**
     * update_plugin()
     */
    private function update_plugin() {
        // Silence
    }

    /**
     * plugin_url()
     */
    public function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * plugin_path()
     */
    public function plugin_path() {
        return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

    /**
     * Get Ajax URL.
     * @return string
     */
    public function ajax_url() {
        return admin_url( 'admin-ajax.php', 'relative' );
    }

    /**
     * cpo_activation()
     */
    public function activation( $plugin ) {
    }

    /**
     * Initialize composer autoloader for dependencies
     */
    private function init_autoloader() {
        static $autoloader_loaded = false;
        if ( !$autoloader_loaded ) {
            $autoloader_path = UNI_CPO_ABSPATH . 'vendor/autoload.php';
            if ( file_exists( $autoloader_path ) ) {
                require_once $autoloader_path;
                $autoloader_loaded = true;
                if ( function_exists( 'uni_cpo_log_cloud_operation' ) ) {
                    uni_cpo_log_cloud_operation( "Composer autoloader loaded successfully from: {$autoloader_path}", 'info' );
                }
            } else {
                if ( function_exists( 'uni_cpo_log_cloud_operation' ) ) {
                    uni_cpo_log_cloud_operation( "Composer autoloader not found at: {$autoloader_path}. Please run composer install.", 'error' );
                }
                error_log( "Uni CPO: Composer autoloader not found at: {$autoloader_path}" );
            }
        }
    }

    /**
     * Load composer dependencies (backward compatibility method)
     */
    private function load_dropbox_sdk() {
        $this->init_autoloader();
    }

    /**
     * Handle Dropbox authorization redirect
     */
    public function handle_dropbox_auth() {
        if ( $this->is_pro() ) {
            // Check if user can manage options
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Unauthorized', 'uni-cpo' ) );
            }
            $settings = $this->get_settings();
            $app_key = ( isset( $settings['dropbox_app_key'] ) ? $settings['dropbox_app_key'] : '' );
            $app_secret = ( isset( $settings['dropbox_app_secret'] ) ? $settings['dropbox_app_secret'] : '' );
            if ( empty( $app_key ) || empty( $app_secret ) ) {
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&error=missing_credentials' ) );
                exit;
            }
            try {
                // Include the new Dropbox SDK and all required dependencies
                $this->load_dropbox_sdk();
                // Include our custom WordPress-compatible persistent data store
                require_once UNI_CPO_ABSPATH . 'includes/cloud-storage/class-uni-cpo-wordpress-persistent-data-store.php';
                // Create Dropbox app instance with custom persistent data store
                $app = new \Kunnu\Dropbox\DropboxApp($app_key, $app_secret);
                $persistentDataStore = new Uni_Cpo_WordPress_Persistent_Data_Store();
                $config = [
                    'persistent_data_store' => $persistentDataStore,
                ];
                $dropbox = new \Kunnu\Dropbox\Dropbox($app, $config);
                $authHelper = $dropbox->getAuthHelper();
                // Get authorization URL (CSRF token will be auto-generated and stored by the SDK)
                $redirectUri = admin_url( 'admin-post.php?action=uni_cpo_dropbox_callback' );
                $authUrl = $authHelper->getAuthUrl(
                    $redirectUri,
                    [],
                    null,
                    'offline'
                );
                // Redirect to Dropbox authorization
                wp_redirect( $authUrl );
                exit;
            } catch ( Exception $e ) {
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&error=' . urlencode( $e->getMessage() ) ) );
                exit;
            }
        }
    }

    /**
     * Handle Dropbox OAuth callback
     */
    public function handle_dropbox_callback() {
        if ( $this->is_pro() ) {
            // Check if user can manage options
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( __( 'Unauthorized', 'uni-cpo' ) );
            }
            $code = ( isset( $_GET['code'] ) ? $_GET['code'] : '' );
            $state = ( isset( $_GET['state'] ) ? $_GET['state'] : '' );
            if ( empty( $code ) ) {
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&error=no_code' ) );
                exit;
            }
            $settings = $this->get_settings();
            $app_key = ( isset( $settings['dropbox_app_key'] ) ? $settings['dropbox_app_key'] : '' );
            $app_secret = ( isset( $settings['dropbox_app_secret'] ) ? $settings['dropbox_app_secret'] : '' );
            try {
                // Include the new Dropbox SDK and all required dependencies
                $this->load_dropbox_sdk();
                // Include our custom WordPress-compatible persistent data store
                require_once UNI_CPO_ABSPATH . 'includes/cloud-storage/class-uni-cpo-wordpress-persistent-data-store.php';
                // Create Dropbox app instance with the same persistent data store
                $app = new \Kunnu\Dropbox\DropboxApp($app_key, $app_secret);
                $persistentDataStore = new Uni_Cpo_WordPress_Persistent_Data_Store();
                $config = [
                    'persistent_data_store' => $persistentDataStore,
                ];
                $dropbox = new \Kunnu\Dropbox\Dropbox($app, $config);
                $authHelper = $dropbox->getAuthHelper();
                // Get access token (CSRF token validation will happen automatically)
                $redirectUri = admin_url( 'admin-post.php?action=uni_cpo_dropbox_callback' );
                $accessToken = $authHelper->getAccessToken( $code, $state, $redirectUri );
                // Store tokens
                update_option( 'uni_cpo_dropbox_access_token', $accessToken->getToken() );
                update_option( 'uni_cpo_dropbox_refresh_token', $accessToken->getRefreshToken() );
                update_option( 'uni_cpo_dropbox_expires_at', time() + $accessToken->getExpiryTime() );
                update_option( 'uni_cpo_dropbox_account_id', $accessToken->getAccountId() );
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&success=authorized' ) );
                exit;
            } catch ( Exception $e ) {
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&error=' . urlencode( $e->getMessage() ) ) );
                exit;
            }
        }
    }

    /**
     * Handle Dropbox authorization revocation
     */
    public function handle_dropbox_revoke() {
        // Check if user can manage options
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'uni-cpo' ) );
        }
        try {
            $settings = $this->get_settings();
            $app_key = ( isset( $settings['dropbox_app_key'] ) ? $settings['dropbox_app_key'] : '' );
            $app_secret = ( isset( $settings['dropbox_app_secret'] ) ? $settings['dropbox_app_secret'] : '' );
            $access_token = get_option( 'uni_cpo_dropbox_access_token', '' );
            if ( !empty( $app_key ) && !empty( $app_secret ) && !empty( $access_token ) ) {
                // Load Dropbox SDK via composer autoloader
                $this->load_dropbox_sdk();
                // Create Dropbox app instance
                $app = new \Kunnu\Dropbox\DropboxApp($app_key, $app_secret, $access_token);
                $dropbox = new \Kunnu\Dropbox\Dropbox($app);
                $authHelper = $dropbox->getAuthHelper();
                // Revoke access token
                $authHelper->revokeAccessToken();
            }
        } catch ( Exception $e ) {
            // Log the error but don't fail the revocation
            error_log( 'Dropbox revocation error: ' . $e->getMessage() );
        }
        // Clear stored tokens regardless of API call success
        delete_option( 'uni_cpo_dropbox_access_token' );
        delete_option( 'uni_cpo_dropbox_refresh_token' );
        delete_option( 'uni_cpo_dropbox_expires_at' );
        delete_option( 'uni_cpo_dropbox_account_id' );
        wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&success=revoked' ) );
        exit;
    }

    /**
     * Handle Google Drive authorization
     */
    public function handle_gdrive_authorize() {
        if ( $this->is_pro() ) {
            uni_cpo_log_cloud_operation( "=== GOOGLE DRIVE AUTHORIZATION STARTED ===", 'info' );
            // Check if user can manage options
            if ( !current_user_can( 'manage_options' ) ) {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Authorization denied - user lacks manage_options capability", 'error' );
                wp_die( __( 'Unauthorized', 'uni-cpo' ) );
            }
            // Check if we have the necessary credentials
            $settings = $this->get_settings();
            $client_id = ( isset( $settings['gdrive_client_id'] ) ? $settings['gdrive_client_id'] : '' );
            $client_secret = ( isset( $settings['gdrive_client_secret'] ) ? $settings['gdrive_client_secret'] : '' );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Client ID configured: " . (( !empty( $client_id ) ? 'YES' : 'NO' )), 'info' );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Client Secret configured: " . (( !empty( $client_secret ) ? 'YES' : 'NO' )), 'info' );
            if ( empty( $client_id ) || empty( $client_secret ) ) {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Missing credentials - redirecting with error", 'error' );
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&error=gdrive_missing_credentials' ) );
                exit;
            }
            try {
                // Initialize autoloader
                $this->init_autoloader();
                // Check if Google\Auth\OAuth2 class is available
                if ( !class_exists( 'Google\\Auth\\OAuth2' ) ) {
                    throw new Exception('Google Auth library is not installed. Please run composer install.');
                }
                $redirect_uri = admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&gdrive_callback=1' );
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Redirect URI: " . $redirect_uri, 'info' );
                // Generate and store state for CSRF protection
                $state = wp_generate_password( 32, false );
                set_transient( 'uni_cpo_gdrive_oauth_state', $state, 300 );
                // 5 minutes
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Generated state token: " . $state, 'info' );
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: State token stored in transient (expires in 300 seconds)", 'info' );
                // Build authorization URL manually
                $auth_params = [
                    'client_id'     => $client_id,
                    'redirect_uri'  => $redirect_uri,
                    'response_type' => 'code',
                    'scope'         => 'https://www.googleapis.com/auth/drive.file',
                    'access_type'   => 'offline',
                    'prompt'        => 'consent',
                    'state'         => $state,
                ];
                $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $auth_params );
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Redirecting to Google authorization URL", 'info' );
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Auth URL: " . $authUrl, 'info' );
                wp_redirect( $authUrl );
                exit;
            } catch ( Exception $e ) {
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&error=' . urlencode( $e->getMessage() ) ) );
                exit;
            }
        }
    }

    /**
     * Handle Google Drive OAuth callback
     */
    public function handle_gdrive_callback() {
        if ( $this->is_pro() ) {
            // Only process on settings page with callback parameter
            if ( !is_admin() || !isset( $_GET['page'] ) || $_GET['page'] !== 'uni-cpo-settings' || !isset( $_GET['gdrive_callback'] ) ) {
                return;
            }
            uni_cpo_log_cloud_operation( "=== GOOGLE DRIVE CALLBACK RECEIVED ===", 'info' );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Request URI: " . $_SERVER['REQUEST_URI'], 'info' );
            // Log all GET parameters (sanitized)
            $get_params = array_map( 'sanitize_text_field', $_GET );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: GET parameters: " . json_encode( $get_params ), 'info' );
            // Check if user can manage options
            if ( !current_user_can( 'manage_options' ) ) {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Authorization denied - user lacks manage_options capability", 'error' );
                wp_die( __( 'Unauthorized', 'uni-cpo' ) );
            }
            // Check for authorization code
            if ( !isset( $_GET['code'] ) ) {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: No authorization code received", 'error' );
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&error=gdrive_no_code' ) );
                exit;
            }
            $code = sanitize_text_field( $_GET['code'] );
            $state = ( isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '' );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Authorization code received (length: " . strlen( $code ) . ")", 'info' );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: State from Google: " . $state, 'info' );
            // Verify CSRF state
            $stored_state = get_transient( 'uni_cpo_gdrive_oauth_state' );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Stored state from transient: " . (( $stored_state ? $stored_state : 'NULL/EMPTY' )), 'info' );
            delete_transient( 'uni_cpo_gdrive_oauth_state' );
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Transient deleted", 'info' );
            // Detailed state comparison
            if ( empty( $stored_state ) ) {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: State validation FAILED - stored state is empty (token expired or not found)", 'error' );
            } elseif ( $stored_state !== $state ) {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: State validation FAILED - states don't match", 'error' );
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Expected: " . $stored_state, 'error' );
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Received: " . $state, 'error' );
            } else {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: State validation SUCCESS", 'info' );
            }
            if ( empty( $stored_state ) || $stored_state !== $state ) {
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&error=gdrive_invalid_state' ) );
                exit;
            }
            try {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Starting token exchange", 'info' );
                $settings = $this->get_settings();
                $client_id = $settings['gdrive_client_id'];
                $client_secret = $settings['gdrive_client_secret'];
                // Initialize autoloader
                $this->init_autoloader();
                // Check if Google\Auth\OAuth2 class is available
                if ( !class_exists( 'Google\\Auth\\OAuth2' ) ) {
                    throw new Exception('Google Auth library is not installed. Please run composer install.');
                }
                $redirect_uri = admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&gdrive_callback=1' );
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Using redirect URI: " . $redirect_uri, 'info' );
                // Initialize OAuth2 client
                $oauth2 = new \Google\Auth\OAuth2([
                    'clientId'           => $client_id,
                    'clientSecret'       => $client_secret,
                    'authorizationUri'   => 'https://accounts.google.com/o/oauth2/v2/auth',
                    'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
                    'redirectUri'        => $redirect_uri,
                    'scope'              => 'https://www.googleapis.com/auth/drive.file',
                ]);
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Exchanging authorization code for access token", 'info' );
                // Set authorization code
                $oauth2->setCode( $code );
                // Exchange code for access token
                $token = $oauth2->fetchAuthToken();
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Token response received: " . json_encode( array_keys( $token ) ), 'info' );
                if ( isset( $token['error'] ) ) {
                    uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Token exchange error: " . (( isset( $token['error_description'] ) ? $token['error_description'] : $token['error'] )), 'error' );
                    throw new Exception('Token exchange failed: ' . (( isset( $token['error_description'] ) ? $token['error_description'] : $token['error'] )));
                }
                // Add created timestamp
                $token['created'] = time();
                // Store tokens
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Storing access token", 'info' );
                update_option( 'uni_cpo_gdrive_access_token', json_encode( $token ) );
                if ( isset( $token['refresh_token'] ) ) {
                    uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: Storing refresh token", 'info' );
                    update_option( 'uni_cpo_gdrive_refresh_token', $token['refresh_token'] );
                } else {
                    uni_cpo_log_cloud_operation( "GOOGLE DRIVE CALLBACK: No refresh token in response", 'warning' );
                }
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: OAuth authorization successful", 'info' );
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&success=gdrive_authorized' ) );
                exit;
            } catch ( Exception $e ) {
                uni_cpo_log_cloud_operation( "GOOGLE DRIVE: OAuth authorization failed: " . $e->getMessage(), 'error' );
                wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&error=' . urlencode( $e->getMessage() ) ) );
                exit;
            }
        }
    }

    /**
     * Handle Google Drive authorization revocation
     */
    public function handle_gdrive_revoke() {
        // Check if user can manage options
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'uni-cpo' ) );
        }
        try {
            $access_token = get_option( 'uni_cpo_gdrive_access_token', '' );
            if ( !empty( $access_token ) ) {
                $token_data = json_decode( $access_token, true );
                if ( isset( $token_data['access_token'] ) ) {
                    // Revoke token with Google using direct HTTP request
                    $revoke_url = 'https://oauth2.googleapis.com/revoke?token=' . urlencode( $token_data['access_token'] );
                    $response = wp_remote_post( $revoke_url );
                    if ( !is_wp_error( $response ) ) {
                        uni_cpo_log_cloud_operation( "GOOGLE DRIVE: OAuth token revoked with Google", 'info' );
                    } else {
                        uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Token revocation request failed: " . $response->get_error_message(), 'warning' );
                    }
                }
            }
        } catch ( Exception $e ) {
            uni_cpo_log_cloud_operation( "GOOGLE DRIVE: Token revocation failed: " . $e->getMessage(), 'warning' );
        }
        // Clear stored tokens regardless of revocation success
        delete_option( 'uni_cpo_gdrive_access_token' );
        delete_option( 'uni_cpo_gdrive_refresh_token' );
        delete_option( 'uni_cpo_gdrive_upload_folder_id' );
        uni_cpo_log_cloud_operation( "GOOGLE DRIVE: OAuth tokens cleared locally", 'info' );
        wp_redirect( admin_url( 'admin.php?page=uni-cpo-settings&tab=file_uploads&success=gdrive_revoked' ) );
        exit;
    }

}
