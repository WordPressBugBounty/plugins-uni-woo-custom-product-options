<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
* Uni_Cpo_Setting_Display class
*
*/

class Uni_Cpo_Setting_Display extends Uni_Cpo_Setting implements Uni_Cpo_Setting_Interface {

	/**
	 * Init
	 *
	 */
	public function __construct() {
		$this->setting_key  = 'display';
		$this->setting_data = array(
			'title'             => __( 'Display', 'uni-cpo' ),
			'type'              => 'text',
			'custom_attributes' => array(
				'data-uni-constrainer' => 'yes'
			),
			'options'           => array(
				'block'   => __( 'block' ),
				'inline-block' => __( 'inline-block' ),
                'inline' => __( 'inline' ),
                'flex'   => __( 'flex' ),
                'inline-flex' => __( 'inline-flex' )
			),
			'js_var'            => 'data'
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
