<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
* Uni_Cpo_Setting_Cpo_Postcode_Country class
*
*/

class Uni_Cpo_Setting_Cpo_Postcode_Country extends Uni_Cpo_Setting implements Uni_Cpo_Setting_Interface {

	/**
	 * Init
	 *
	 */
	public function __construct() {
		$this->setting_key  = 'cpo_postcode_country';
		$this->setting_data = array(
			'title'             => __( 'Country name', 'uni-cpo' ),
			'is_tooltip'        => true,
			'is_tooltip_warning' => true,
			'desc_tip'          => __( 'Country name for Google maps API. Example: Italy. If set - used for both origin and destination. Otherwise customer MUST input country for destination or in both fields if dual mode is on!', 'uni-cpo' ),
			//'desc_tip_warning'   => __( 'Important to save to DB if modified', 'uni-cpo' ),
			'value'             => '{{- data }}'
		);
		add_action( 'wp_footer', array( $this, 'js_template' ), 10 );
	}


	/**
	 * A template for the module
	 *
	 * @since 1.0
	 * @return string
	 */
	public function js_template() {
		?>
        <script id="js-builderius-setting-<?php echo $this->setting_key; ?>-tmpl" type="text/template">
            <div class="uni-modal-row uni-clear">
				<?php echo $this->generate_field_label_html(); ?>
                <div class="uni-modal-row-second uni-clear">
					<?php echo $this->generate_text_html(); ?>
                </div>
            </div>
        </script>
		<?php
	}

}
