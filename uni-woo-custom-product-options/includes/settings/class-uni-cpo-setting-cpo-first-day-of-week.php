<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
* Uni_Cpo_Setting_Cpo_Day_Night class
*
*/

class Uni_Cpo_Setting_Cpo_First_Day_Of_Week extends Uni_Cpo_Setting implements Uni_Cpo_Setting_Interface {

	/**
	 * Init
	 *
	 */
	public function __construct() {
		$this->setting_key  = 'cpo_first_day_of_week';
		$this->setting_data = array(
			'title'              => __( 'First day of week', 'uni-cpo' ),
			'is_tooltip'         => true,
			'is_tooltip_warning' => true,
			'desc_tip'           => __( 'Sunday - 0, Monday - 1, Tuesday - 2 and so on', 'uni-cpo' ),
			'custom_attributes'  => array(
				'data-parsley-pattern' => '^[0-6]$',
			),
            'value'              => '{{- data }}'
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
            <div class="uni-modal-row uni-clear" data-uni-constrained="input[name=cpo_is_datepicker_disabled]" data-uni-constvalue="no">
				<?php echo $this->generate_field_label_html(); ?>
                <div class="uni-modal-row-second uni-clear">
                    <div class="uni-setting-fields-wrap-2 uni-clear">
	                    <?php echo $this->generate_text_html(); ?>
                    </div>
                </div>
            </div>
        </script>
		<?php
	}
}