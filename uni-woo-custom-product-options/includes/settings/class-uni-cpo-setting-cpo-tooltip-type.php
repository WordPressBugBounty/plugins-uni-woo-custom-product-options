<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
* Uni_Cpo_Setting_Cpo_Tooltip_Type class
*
*/

class Uni_Cpo_Setting_Cpo_Tooltip_Type extends Uni_Cpo_Setting implements Uni_Cpo_Setting_Interface {

	/**
	 * Init
	 *
	 */
	public function __construct() {
		$this->setting_key  = 'cpo_tooltip_type';
		$this->setting_data = array(
			'title'   => __( 'Type of tooltip', 'uni-cpo' ),
			'custom_attributes' => array(
				'data-uni-constrainer' => 'yes'
			),
			'options' => array(
				'classic' => __( 'Classic', 'uni-cpo' ),
				'lightbox' => __( 'Lightbox', 'uni-cpo' ),
				'popup' => __( 'Popup Maker', 'uni-cpo' )
			),
			'js_var'  => 'data'
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
				<?php echo $this->generate_field_label_html() ?>
                <div class="uni-modal-row-second">
					<?php echo $this->generate_select_html() ?>
                </div>
            </div>
        </script>
		<?php
	}

}
