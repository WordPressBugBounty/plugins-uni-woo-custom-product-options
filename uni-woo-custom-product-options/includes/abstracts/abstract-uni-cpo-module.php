<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
*   Uni_Cpo_Module Abstract class
*
*/

class Uni_Cpo_Module extends Uni_Cpo_Data {

	/**
	 * This is the name of this object type.
	 * @var string
	 */
	protected $object_type = 'module';

	/**
	 * Post type.
	 * @var string
	 */
	protected $post_type = 'uni_module';

	/**
	 * Cache group.
	 * @var string
	 */
	protected $cache_group = 'modules';

	/**
	 * Stores module data.
	 *
	 * @var array
	 */
	protected $data = array(
		'width_type' => 'auto',
		'width'       => array(
			'value' => '',
			'unit'  => 'px'
		),
		'color'      => '',
		'text_align' => '',
		'font_family'    => '',
		'font_style'     => '',
		'font_weight'    => '',
		'font_size'      => array(
			'value' => 0,
			'unit'  => 'px'
		),
		'letter_spacing' => '',
		'line_height'    => '',
		'cpo_conditional' => array(),
	);

	/**
	 * Get the module if ID is passed, otherwise the module is new and empty.
	 * This class should NOT be instantiated, but the uni_cpo_get_module() function
	 * should be used.
	 *
	 * @param int|Uni_Cpo_Module|object $module Module to init.
	 */
	public function __construct( $module = 0 ) {
		parent::__construct( $module );
		if ( is_numeric( $module ) && $module > 0 ) {
			$this->set_id( $module );
		} elseif ( $module instanceof self ) {
			$this->set_id( absint( $module->get_id() ) );
		} elseif ( ! empty( $module->ID ) ) {
			$this->set_id( absint( $module->ID ) );
		} else {
			$this->set_object_read( true );
		}

		$this->data_store = Uni_Cpo_Data_Store::load( 'module' );
		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Get internal type. Should return string and *should be overridden* by child classes.
	 *
	 * @return string
	 */
	public static function get_type(){
		return '';
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	|
	| Methods for getting data from the module object.
	*/

	/**
	 * Get module slug.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_slug( $context = 'view' ) {
		return $this->get_prop( 'name', $context );
	}

	public function get_cpo_conditional( $context = 'view' ) {
		return $this->get_prop( 'cpo_conditional', $context );
	}

	// TODO add more getters


	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	|
	| Functions for setting module data. These should not update anything in the
	| database itself and should only change what is stored in the class
	| object.
	*/

	/**
	 * Set module slug.
	 *
	 * @param string $slug Module slug.
	 */
	public function set_slug( $slug ) {
		$this->set_prop( 'slug', $slug );
	}

	public function set_cpo_conditional( $value ) {
		$this->set_prop( 'cpo_conditional', $value );
	}

	// TODO add more setters

	/*
	|--------------------------------------------------------------------------
	| Other Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Save data (either create or update depending on if we are working on an existing module).
	 *
	 */
	public function save() {
		if ( $this->data_store ) {
			// Trigger action before saving to the DB. Use a pointer to adjust object props before save.
			do_action( 'uni_cpo_before_' . $this->object_type . '_object_save', $this, $this->data_store );

			if ( $this->get_id() ) {
				$this->data_store->update( $this );
			} else {
				$this->data_store->create( $this );
			}
			return $this->get_id();
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Other Actions
	|--------------------------------------------------------------------------
	*/

	public static function template( $data ){}

	public static function get_css( $data ){}

	public static function conditional_rules( $data ) {
		$id        = $data['id'];

        if (!empty($data['settings']['cpo_conditional'])) {
            $rules_data = $data['settings']['cpo_conditional']['main'];
            $is_enabled = ('yes' === $rules_data['cpo_is_fc']) ? true : false;
        } else {
            $is_enabled = false;
        }

		if ( ! $is_enabled ) {
			return;
		}

		$is_hidden = ( 'hide' === $rules_data['cpo_fc_default'] ) ? true : false;
		$scheme    = stripslashes_deep( stripslashes_deep( $rules_data['cpo_fc_scheme'] ) );
		$scheme    = json_decode( $scheme, true );

		if ( is_array( $scheme ) && ! empty( $scheme ) ) {
			$condition = uni_cpo_option_js_condition_prepare( $scheme );

			$slide_down   = '$uni_row_' . esc_attr( $id ) . '.slideDown(300).addClass("cpo-visible-field");' . "\n";
			$slide_up     = '$uni_row_' . esc_attr( $id ) . '.slideUp(300).removeClass("cpo-visible-field");' . "\n";
			$add_class    = '$uni_row_' . esc_attr( $id ) . '_fields.each(function( index ) {' . "\n";
			$add_class    .= '$(this).addClass( extraClass );' . "\n";
			$add_class    .= '});' . "\n";
			$remove_class = '$uni_row_' . esc_attr( $id ) . '_fields.each(function( index ) {' . "\n";
			$remove_class .= '$(this).removeClass( extraClass );' . "\n";
			$remove_class .= '});' . "\n";

			$final_statement = 'if ' . $condition . ' {' . "\n";
			if ( $is_hidden ) {
				$final_statement .= $slide_down;
				$final_statement .= $remove_class;
			} else {
				$final_statement .= $slide_up;
				$final_statement .= $add_class;
			}
			$final_statement .= '} else {' . "\n";
			if ( $is_hidden ) {
				$final_statement .= $slide_up;
				$final_statement .= $add_class;
			} else {
				$final_statement .= $slide_down;
				$final_statement .= $remove_class;
			}
			$final_statement .= '}' . "\n";

			?>
			<script>
                jQuery(document).ready(function($) {
                    'use strict';

                    $(document.body).on('uni_cpo_options_data_ajax_success', function() {
						<?php echo "uni_row_" . esc_attr( $id ) ?>_fields_conditional_func(unicpo.formatted_vars);
                    });
                    $(document.body).on('uni_cpo_options_data_for_conditional', function(e, fields) {
                        var variables = $.extend({}, unicpo.formatted_vars, fields);
						<?php echo "uni_row_" . esc_attr( $id ) ?>_fields_conditional_func(variables);
                    });

                    function <?php echo "uni_row_" . esc_attr( $id ) ?>_fields_conditional_func(formData) {
                        try {
                            var $uni_row_<?php echo esc_attr( $id ) ?>        = $('#uni_row_<?php echo esc_attr( $id ) ?>');
                            var $uni_row_<?php echo esc_attr( $id ) ?>_fields = $uni_row_<?php echo esc_attr( $id ) ?>.find('input, select, textarea');
                            var extraClass = 'uni-cpo-excluded-field';

			                <?php
			                if ( $is_hidden ) {
				                $is_hidden_html = 'if ( ! $uni_row_' . esc_attr( $id ) . '.hasClass("cpo-visible-field") ) {' . "\n";
				                $is_hidden_html .= '$uni_row_' . $id . '.hide();' . "\n";
				                $is_hidden_html .= $add_class;
				                $is_hidden_html .= '}' . "\n";
				                echo $is_hidden_html;
			                }
			                ?>

			                <?php echo $final_statement; ?>
                        } catch (e) {
                            console.error(e);
                        }
                    }
                });
			</script>
			<?php
		}
	}

	public static function get_custom_attribute_html( $attributes = array() ) {
		$custom_attributes = array();

		if ( ! empty( $attributes ) && is_array( $attributes ) ) {
			foreach ( $attributes as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		return implode( ' ', $custom_attributes );
	}
}
