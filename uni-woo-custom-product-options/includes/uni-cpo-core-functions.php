<?php

/**
 * Uni Cpo Core Functions
 *
 * General core functions available on both the front-end and admin.
 *
 * @author        MooMoo
 * @category    Core
 * @package    UniCpo/Functions
 * @version     4.0.0
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}
// Include core functions (available in both admin and frontend).
include 'uni-cpo-functions.php';
include 'uni-cpo-formatting-functions.php';
/**
 * Display an Uni Cpo help tip.
 *
 * @param string $tip Help tip text
 * @param bool $allow_html Allow sanitized HTML if true or escape
 *
 * @return string
 * @since  4.0.0
 *
 */
function uni_cpo_help_tip(  $tip, $allow_html = false, $args = array()  ) {
    if ( $allow_html ) {
        $tip = uni_cpo_sanitize_tooltip( $tip );
    } else {
        $tip = esc_attr( $tip );
    }
    if ( isset( $args['type'] ) && 'warning' === $args['type'] ) {
        $css_class = 'uni-cpo-tooltip-warning';
    } else {
        $css_class = 'uni-cpo-tooltip';
    }
    return '<span
                class="' . $css_class . '"
                data-tip="' . $tip . '">
                </span>';
}

/**
 * Conditional function that check if Cart page use Cart Blocks
 *
 * @return boolean
 * @since  4.0.0
 *
 */
function uni_cpo_is_checkout_block() {
    if ( class_exists( 'WC_Blocks_Utils' ) ) {
        return WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'cart' ), 'woocommerce/cart' );
    } else {
        $post = get_post( get_option( 'woocommerce_checkout_page_id' ) );
        return false !== strpos( $post->post_content, '<!-- wp:woocommerce/checkout' );
    }
}

/**
 * Serialize and encode
 *
 * @return    string
 *
 * @access    public
 * @since     4.0.0
 */
function uni_cpo_encode(  $value  ) {
    $func = 'base64' . '_encode';
    return $func( maybe_serialize( $value ) );
}

/**
 * Decode and unserialize
 *
 * @return    array
 *
 * @access    public
 * @since     4.0.0
 */
function uni_cpo_decode(  $value  ) {
    $func = 'base64' . '_decode';
    $value = $func( $value );
    return maybe_unserialize( $value );
}

/**
 * Get values of modules from multidimensional array
 *
 * @return array
 * @since  4.0.0
 *
 */
function uni_cpo_get_mod_values(  $content  ) {
    $new_content = array();
    if ( is_array( $content ) ) {
        foreach ( $content as $key => $value ) {
            if ( 'content' === $key || 'html' === $key ) {
                $new_content[] = $value;
            }
            if ( is_array( $value ) ) {
                $new_content = array_merge( $new_content, uni_cpo_get_mod_values( $value ) );
            }
        }
    }
    return $new_content;
}

/**
 * @param $data
 * @return array
 * @throws \Exception|array
 */
function uni_cpo_save_modal(  $data  ) {
    $post_id = ( !empty( $data['pid'] ) ? absint( $data['pid'] ) : 0 );
    $model_obj_type = ( !empty( $data['obj_type'] ) ? uni_cpo_clean( $data['obj_type'] ) : '' );
    $model_type = ( !empty( $data['type'] ) ? uni_cpo_clean( $data['type'] ) : '' );
    if ( !$model_type ) {
        throw new Exception(__( 'Invalid model type', 'uni-cpo' ));
    }
    if ( !$model_obj_type ) {
        throw new Exception(__( 'Invalid builder model object type', 'uni-cpo' ));
    }
    if ( $post_id > 0 ) {
        $model = uni_cpo_get_model( $model_obj_type, $post_id );
        if ( is_object( $model ) && 'trash' === $model->get_status() ) {
            wp_delete_post( $post_id, true );
            $post_id = 0;
            $model = uni_cpo_get_model( $model_obj_type, $post_id, $model_type );
        } elseif ( !is_object( $model ) ) {
            $post_id = 0;
            $model = uni_cpo_get_model( $model_obj_type, $post_id, $model_type );
        }
    } else {
        $model = uni_cpo_get_model( $model_obj_type, $post_id, $model_type );
    }
    if ( !$model ) {
        throw new Exception(__( 'Invalid model', 'uni-cpo' ));
    }
    if ( 'option' === $model_obj_type ) {
        $cpo_general = $data['settings']['cpo_general'];
        $slug_being_saved = ( !empty( $cpo_general['main']['cpo_slug'] ) ? uni_cpo_clean( $cpo_general['main']['cpo_slug'] ) : sanitize_title_with_dashes( uniqid( 'option_' ) ) );
        if ( empty( $model->get_slug() ) ) {
            // slug is empty, it is a new option
            $slug_check_result = uni_cpo_get_unique_slug( $slug_being_saved );
        } elseif ( !empty( $model->get_slug() ) ) {
            if ( UniCpo()->get_var_slug() . $slug_being_saved !== $model->get_slug() ) {
                // looks like slug is going to be changed, so let's check its uniqueness
                $slug_check_result = uni_cpo_get_unique_slug( $slug_being_saved );
            } else {
                $slug_check_result = array(
                    'unique' => true,
                    'slug'   => $model->get_slug(),
                );
            }
        }
        if ( !isset( $slug_check_result ) ) {
            throw new Exception(__( 'Something went wrong', 'uni-cpo' ));
        }
        if ( $slug_check_result['unique'] && $slug_check_result['slug'] ) {
            unset($data['settings']['general']['status']);
            $data['settings']['cpo_general']['main']['cpo_slug'] = '';
            $props = array(
                'slug' => $slug_check_result['slug'],
            );
            foreach ( $data['settings'] as $data_name => $data_data ) {
                $data_name = uni_cpo_clean( $data_name );
                $data_data = uni_cpo_get_settings_data_sanitized( $data_data, $data_name );
                $props[$data_name] = $data_data;
            }
            $model->set_props( $props );
            $model->save();
            $model_data = $model->formatted_model_data();
            return [
                'success' => true,
                'data'    => $model_data,
            ];
        } elseif ( !$slug_check_result['unique'] && $slug_check_result['slug'] ) {
            return [
                'success' => false,
                'data'    => array(
                    'errorData' => $slug_check_result,
                ),
            ];
        }
    } elseif ( 'module' === $model_obj_type ) {
        throw new Exception(__( 'Unsupported element type', 'uni-cpo' ));
    }
}

//
function uni_cpo_get_modules_by_type(  $data, $post_type = 'uni_cpo_option'  ) {
    $query = new WP_Query(array(
        'post_type'      => $post_type,
        'meta_query'     => array(array(
            'key'     => '_module_type',
            'value'   => $data['type'],
            'compare' => '=',
        )),
        'posts_per_page' => -1,
        'post__not_in'   => ( !empty( $data['exclude_id'] ) ? array($data['exclude_id']) : array() ),
    ));
    if ( !empty( $query->posts ) ) {
        return $query->posts;
    } else {
        return false;
    }
}

//
function uni_cpo_is_slug_exists(  $slug, $post_type = 'uni_cpo_option'  ) {
    $query = new WP_Query(array(
        'name'      => $slug,
        'post_type' => $post_type,
    ));
    if ( !empty( $query->posts ) ) {
        return true;
    } else {
        return false;
    }
}

//
function uni_cpo_get_post_by_slug(  $slug, $post_type = 'uni_cpo_option'  ) {
    $query = new WP_Query(array(
        'name'      => $slug,
        'post_type' => $post_type,
    ));
    if ( !empty( $query->posts ) ) {
        return $query->posts[0];
    }
    return null;
}

//
function uni_cpo_get_posts_by_slugs(  $slugs, $post_type = 'uni_cpo_option'  ) {
    $query = new WP_Query(array(
        'post_name__in'  => $slugs,
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'orderby'        => 'post_name__in',
    ));
    if ( !empty( $query->posts ) ) {
        return $query->posts;
    }
    return null;
}

//
function uni_cpo_get_posts_by_ids(  $ids, $post_type = 'uni_cpo_option'  ) {
    $query = new WP_Query(array(
        'post__in'       => $ids,
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'orderby'        => 'post__in',
    ));
    if ( !empty( $query->posts ) ) {
        return $query->posts;
    }
    return null;
}

//
function uni_cpo_get_posts_slugs(  $post_type = 'uni_cpo_option'  ) {
    $query = new WP_Query(array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
    ));
    if ( !empty( $query->posts ) ) {
        $slugs_list = wp_list_pluck( $query->posts, 'post_name' );
        return $slugs_list;
    }
    return [];
}

//
function uni_cpo_truncate_post_slug(  $slug, $length = 200  ) {
    if ( strlen( $slug ) > $length ) {
        $decoded_slug = urldecode( $slug );
        if ( $decoded_slug === $slug ) {
            $slug = substr( $slug, 0, $length );
        } else {
            $slug = utf8_uri_encode( $decoded_slug, $length );
        }
    }
    return rtrim( $slug, '-' );
}

//
function uni_cpo_get_unique_slug(  $slug  ) {
    if ( empty( $slug ) ) {
        return array(
            'unique' => false,
            'slug'   => false,
        );
    }
    $suffix = 2;
    $existed_slugs = uni_cpo_get_posts_slugs();
    $reserved_slugs = uni_cpo_get_reserved_option_slugs();
    $prohibited_slugs = array_merge( $existed_slugs, $reserved_slugs );
    $is_slug_valid = ( !in_array( UniCpo()->get_var_slug() . $slug, $prohibited_slugs ) ? true : false );
    if ( $is_slug_valid ) {
        return array(
            'unique' => true,
            'slug'   => $slug,
        );
    } else {
        do {
            $alt_slug = uni_cpo_truncate_post_slug( $slug, 200 - (strlen( $suffix ) + 1) ) . "_{$suffix}";
            $is_slug_valid = ( !in_array( UniCpo()->get_var_slug() . $alt_slug, $prohibited_slugs ) ? true : false );
            $suffix++;
        } while ( !$is_slug_valid );
        return array(
            'unique' => false,
            'slug'   => $alt_slug,
        );
    }
}

function uni_cpo_get_similar_modules(  $data  ) {
    $items = array();
    if ( 'option' === $data['obj_type'] ) {
        $posts = uni_cpo_get_modules_by_type( array(
            'type'       => $data['type'],
            'exclude_id' => $data['pid'],
        ) );
        if ( !empty( $posts ) ) {
            foreach ( $posts as $post ) {
                $module = uni_cpo_get_option( $post->ID );
                $items[$module->get_id()] = $module->get_slug();
            }
        }
    } elseif ( 'module' === $data['obj_type'] ) {
        $posts = uni_cpo_get_modules_by_type( array(
            'type'       => $data['type'],
            'exclude_id' => $data['pid'],
        ), 'uni_module' );
        // TODO
    }
    return $items;
}

function uni_cpo_get_module_for_sync(  $data  ) {
    if ( 'option' === $data['obj_type'] ) {
        $module = uni_cpo_get_option( $data['pid'] );
        if ( $module ) {
            return $module->formatted_model_data();
        }
    } elseif ( 'module' === $data['obj_type'] ) {
        // TODO
    }
    return false;
}

function uni_cpo_get_similar_products_ids(  $data  ) {
    $query = new WP_Query(array(
        'post_status'    => ['publish', 'draft'],
        'post_type'      => 'product',
        'tax_query'      => array(array(
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => 'simple',
        )),
        'meta_query'     => array(array(
            'key'   => '_cpo_enable',
            'value' => 'on',
        )),
        'posts_per_page' => 500,
        'post__not_in'   => ( !empty( $data['pid'] ) ? array($data['pid']) : array() ),
    ));
    if ( !empty( $query->posts ) ) {
        return $query->posts;
    } else {
        return false;
    }
}

function uni_cpo_get_settings_data_sanitized(  $data_data, $data_name  ) {
    $original_data = $data_data;
    $data_data = uni_cpo_clean( $data_data );
    return apply_filters(
        'uni_cpo_filter_settings_data',
        $data_data,
        $original_data,
        $data_name
    );
}

add_filter(
    'uni_cpo_filter_settings_data',
    'uni_cpo_filter_settings_data_func',
    10,
    3
);
function uni_cpo_filter_settings_data_func(  $data_data, $original_data, $data_name  ) {
    if ( 'general' === $data_name ) {
        $data_data['main']['content'] = ( !empty( $original_data['main']['content'] ) ? uni_cpo_sanitize_text( $original_data['main']['content'] ) : '' );
    }
    if ( 'cpo_general' === $data_name ) {
        $data_data['advanced']['cpo_tooltip'] = ( !empty( $original_data['advanced']['cpo_tooltip'] ) ? uni_cpo_sanitize_text( stripslashes_deep( $original_data['advanced']['cpo_tooltip'] ) ) : '' );
        $data_data['main']['cpo_notice_text'] = ( !empty( $original_data['main']['cpo_notice_text'] ) ? html_entity_decode( uni_cpo_sanitize_text( $original_data['main']['cpo_notice_text'] ) ) : '' );
    }
    if ( 'cpo_rules' === $data_name ) {
        $data_data['data'] = ( !empty( $original_data['data'] ) ? $original_data['data'] : '' );
    }
    if ( 'cpo_validation' === $data_name ) {
        $data_data['main'] = ( !empty( $original_data['main'] ) ? $original_data['main'] : '' );
        $data_data['logic'] = ( !empty( $original_data['logic'] ) ? $original_data['logic'] : '' );
    }
    return $data_data;
}

function uni_cpo_option_apply_changes_walk(  $v, $k, $d  ) {
    if ( is_array( $v ) && !empty( $v ) && isset( $d[1][$k] ) ) {
        array_walk( $v, 'uni_cpo_option_apply_changes_walk', array(&$d[0][$k], $d[1][$k]) );
    } elseif ( !is_array( $v ) && isset( $d[1][$v] ) ) {
        $d[0][$v] = $d[1][$v];
    } elseif ( !is_array( $v ) ) {
        if ( isset( $d[0][$v] ) ) {
            $d[0][$v] = array();
        } else {
            $d[0] = [
                $v => array(),
            ];
        }
    }
}

//////////////////////////////////////////////////////////////////////////////////////
// Calculation functions
//////////////////////////////////////////////////////////////////////////////////////
function uni_cpo_process_formula_with_non_option_vars(  &$variables, $product_data, &$formatted_vars  ) {
    if ( is_array( $product_data['nov_data']['nov'] ) && !empty( $product_data['nov_data']['nov'] ) ) {
        if ( isset( $product_data['nov_data']['nov']['<%row-count%>'] ) ) {
            return $variables;
        }
        if ( isset( $product_data['nov_data']['nov'][0] ) ) {
            $nov = $product_data['nov_data']['nov'][0];
        } elseif ( isset( $product_data['nov_data']['nov'][1] ) ) {
            $nov = $product_data['nov_data']['nov'][1];
        }
        $var_name = '{' . UniCpo()->get_nov_slug() . $nov['slug'] . '}';
        $formula = 0;
        if ( isset( $nov['roles'] ) && 'on' === $product_data['nov_data']['wholesale_enable'] && (!isset( $nov['matrix']['enable'] ) || 'on' !== $nov['matrix']['enable']) ) {
            $formula = uni_cpo_get_role_based_nov_formula( $nov );
        } elseif ( isset( $nov['matrix']['enable'] ) && 'on' === $nov['matrix']['enable'] ) {
        } else {
            $formula = ( isset( $nov['formula'] ) ? $nov['formula'] : '' );
        }
        $formula = uni_cpo_process_formula_with_vars( $formula, $variables );
        $nov_val = apply_filters(
            'uni_cpo_nov_variable_value',
            uni_cpo_calculate_formula( $formula ),
            $product_data,
            $variables,
            $nov['slug']
        );
        $variables[$var_name] = $nov_val;
        $formatted_vars[UniCpo()->get_nov_slug() . $nov['slug']] = $nov_val;
        array_splice( $product_data['nov_data']['nov'], 0, 1 );
        uni_cpo_process_formula_with_non_option_vars( $variables, $product_data, $formatted_vars );
    }
    return $variables;
}

function uni_cpo_get_role_based_nov_formula(  $nov  ) {
    $price_for_role = '';
    $current_user = wp_get_current_user();
    if ( current_user_can( 'edit_shop_orders' ) ) {
        $plugin_settings = UniCpo()->get_settings();
        $price_for_role = $plugin_settings['order_edit_role'];
        if ( 'uni_cpo_own_role' === $price_for_role ) {
            $price_for_role = ( isset( $current_user->roles ) && isset( $current_user->roles[0] ) ? $current_user->roles[0] : '' );
        }
    } else {
        $price_for_role = ( isset( $current_user->roles ) && isset( $current_user->roles[0] ) ? $current_user->roles[0] : '' );
    }
    if ( empty( $price_for_role ) ) {
        return $nov['formula'];
    } else {
        if ( in_array( $price_for_role, $nov['roles'] ) ) {
            return $nov[$price_for_role]['formula'];
        } else {
            return $nov['formula'];
        }
    }
}

function uni_cpo_process_formula_scheme(  $variables, $product_data, $purpose = 'price'  ) {
    if ( 'price' === $purpose ) {
        $scheme_data = $product_data['formula_data']['formula_scheme'];
    } elseif ( 'weight' === $purpose ) {
        $scheme_data = $product_data['weight_data']['weight_scheme'];
    } elseif ( 'shipping_class' === $purpose ) {
        $scheme_data = $product_data['shipping_class_data']['shipping_class_scheme'];
    } elseif ( 'option_rules' === $purpose ) {
        $scheme_data = $product_data;
    }
    // let's inject a special variables to be used in formula logic
    $variables['currency'] = get_woocommerce_currency();
    $variables = apply_filters( 'uni_cpo_variables_for_formula_logic', $variables );
    if ( !isset( $scheme_data ) ) {
        return false;
    }
    foreach ( $scheme_data as $scheme_key => $scheme_item ) {
        $formula_block = ( isset( $scheme_item['formula'] ) ? $scheme_item['formula'] : '' );
        $rules_block = json_decode( $scheme_item['rule'], true );
        $block_condition = $rules_block['condition'];
        $is_passed_block = false;
        $block_rules_count = ( is_array( $rules_block['rules'] ) ? count( $rules_block['rules'] ) : 0 );
        if ( $block_rules_count > 1 ) {
            $check_for_1 = array();
            $check_for_2 = array();
            foreach ( $rules_block['rules'] as $rule_key => $rule_item ) {
                $check_for_3 = array();
                if ( isset( $rule_item['rules'] ) ) {
                    $rule_1_condition = $rule_item['condition'];
                    foreach ( $rule_item['rules'] as $rule_2_key => $rule_2_item ) {
                        $check_for_3[] = uni_cpo_formula_condition_check( $rule_2_item, $variables );
                    }
                    if ( false === in_array( false, $check_for_3, true ) && 'AND' === $rule_1_condition ) {
                        $is_passed_2 = true;
                    } elseif ( false !== in_array( true, $check_for_3, true ) && 'OR' === $rule_1_condition ) {
                        $is_passed_2 = true;
                    } else {
                        $is_passed_2 = false;
                    }
                    array_push( $check_for_2, $is_passed_2 );
                } else {
                    $check_for_1[] = uni_cpo_formula_condition_check( $rule_item, $variables );
                }
            }
            $check_for_1 = array_merge( $check_for_1, $check_for_2 );
            if ( false === in_array( false, $check_for_1, true ) && 'AND' === $block_condition ) {
                $is_passed_block = true;
            } elseif ( false !== in_array( true, $check_for_1, true ) && 'OR' === $block_condition ) {
                $is_passed_block = true;
            } else {
                $is_passed_block = false;
            }
        } else {
            if ( is_array( $rules_block['rules'] ) ) {
                foreach ( $rules_block['rules'] as $rule_key => $rule_item ) {
                    $is_passed_block = uni_cpo_formula_condition_check( $rule_item, $variables );
                }
            }
        }
        if ( $is_passed_block ) {
            return $formula_block;
        }
    }
    return false;
}

// formula condition check
function uni_cpo_formula_condition_check(  $rule, $variables  ) {
    $var_name = $rule['id'];
    $rule_value = $rule['value'];
    $rule_type = $rule['type'];
    $is_passed = false;
    switch ( $rule['operator'] ) {
        case 'less':
            if ( isset( $variables[$var_name] ) ) {
                if ( 'date' === $rule_type ) {
                    $rule_date = new DateTime($rule_value);
                    $chosen_date = new DateTime($variables[$var_name]);
                    if ( $chosen_date < $rule_date ) {
                        $is_passed = true;
                    }
                } else {
                    if ( floatval( $variables[$var_name] ) < floatval( $rule_value ) ) {
                        $is_passed = true;
                    }
                }
            }
            break;
        case 'less_or_equal':
            if ( isset( $variables[$var_name] ) ) {
                if ( 'date' === $rule_type ) {
                    $rule_date = new DateTime($rule_value);
                    $chosen_date = new DateTime($variables[$var_name]);
                    if ( $chosen_date <= $rule_date ) {
                        $is_passed = true;
                    }
                } else {
                    if ( floatval( $variables[$var_name] ) <= floatval( $rule_value ) ) {
                        $is_passed = true;
                    }
                }
            }
            break;
        case 'equal':
            if ( isset( $variables[$var_name] ) && !is_array( $variables[$var_name] ) ) {
                if ( in_array( $rule_type, array('double', 'integer') ) ) {
                    $is_passed = floatval( $variables[$var_name] ) === floatval( $rule_value );
                } else {
                    $is_passed = $variables[$var_name] === $rule_value;
                }
            } elseif ( isset( $variables[$var_name] ) && is_array( $variables[$var_name] ) ) {
                foreach ( $variables[$var_name] as $value ) {
                    if ( $value === $rule_value ) {
                        $is_passed = true;
                        break;
                    }
                }
            }
            break;
        case 'not_equal':
            if ( isset( $variables[$var_name] ) && !is_array( $variables[$var_name] ) ) {
                if ( in_array( $rule_type, array('double', 'integer') ) ) {
                    $is_passed = floatval( $variables[$var_name] ) !== floatval( $rule_value );
                } else {
                    $is_passed = $variables[$var_name] !== $rule_value;
                }
            } elseif ( isset( $variables[$var_name] ) && is_array( $variables[$var_name] ) ) {
                foreach ( $variables[$var_name] as $value ) {
                    if ( $value !== $rule_value ) {
                        $is_passed = true;
                        break;
                    }
                }
            }
            break;
        case 'greater_or_equal':
            if ( isset( $variables[$var_name] ) ) {
                if ( 'date' === $rule_type ) {
                    $rule_date = new DateTime($rule_value);
                    $chosen_date = new DateTime($variables[$var_name]);
                    if ( $chosen_date >= $rule_date ) {
                        $is_passed = true;
                    }
                } else {
                    if ( floatval( $variables[$var_name] ) >= floatval( $rule_value ) ) {
                        $is_passed = true;
                    }
                }
            }
            break;
        case 'greater':
            if ( isset( $variables[$var_name] ) ) {
                if ( 'date' === $rule_type ) {
                    $rule_date = new DateTime($rule_value);
                    $chosen_date = new DateTime($variables[$var_name]);
                    if ( $chosen_date > $rule_date ) {
                        $is_passed = true;
                    }
                } else {
                    if ( floatval( $variables[$var_name] ) > floatval( $rule_value ) ) {
                        $is_passed = true;
                    }
                }
            }
            break;
        case 'is_empty':
            if ( !isset( $variables[$var_name] ) || empty( $variables[$var_name] ) ) {
                $is_passed = true;
            }
            break;
        case 'is_not_empty':
            if ( !empty( $variables[$var_name] ) ) {
                $is_passed = true;
            }
            break;
        case 'between':
            if ( isset( $variables[$var_name] ) ) {
                if ( 'date' === $rule_type ) {
                    $rule_startdate = new DateTime($rule_value[0]);
                    $rule_enddate = new DateTime($rule_value[1]);
                    $chosen_date = new DateTime($variables[$var_name]);
                    if ( $rule_startdate <= $chosen_date && $chosen_date <= $rule_enddate ) {
                        $is_passed = true;
                    }
                } else {
                    $is_passed = floatval( $rule_value[0] ) <= floatval( $variables[$var_name] ) && floatval( $variables[$var_name] ) <= floatval( $rule_value[1] );
                }
            }
            break;
        case 'not_between':
            if ( isset( $variables[$var_name] ) ) {
                if ( 'date' === $rule_type ) {
                    $rule_startdate = new DateTime($rule_value[0]);
                    $rule_enddate = new DateTime($rule_value[1]);
                    $chosen_date = new DateTime($variables[$var_name]);
                    if ( $rule_startdate >= $chosen_date || $chosen_date >= $rule_enddate ) {
                        $is_passed = true;
                    }
                } else {
                    $is_passed = floatval( $rule_value[0] ) >= floatval( $variables[$var_name] ) || floatval( $variables[$var_name] ) >= floatval( $rule_value[1] );
                }
            }
            break;
    }
    return $is_passed;
}

//
function uni_cpo_process_formula_with_vars(  $main_formula, $variables = array()  ) {
    $main_formula = preg_replace( '/\\s+/', '', $main_formula );
    if ( !empty( $variables ) ) {
        foreach ( $variables as $k => $v ) {
            if ( is_array( $v ) ) {
                if ( !empty( $v ) ) {
                    foreach ( $v as $k_child => $v_child ) {
                        $search = "/({$k_child})/";
                        $main_formula = preg_replace( $search, $v_child, $main_formula );
                    }
                }
            } else {
                $search = "/({$k})/";
                $main_formula = preg_replace( $search, $v, $main_formula );
            }
        }
        $pattern = "/{([^}]*)}/";
        $main_formula = preg_replace( $pattern, '0', $main_formula );
    } else {
        $pattern = "/{([^}]*)}/";
        $main_formula = preg_replace( $pattern, '0', $main_formula );
    }
    return $main_formula;
}

//
function uni_cpo_calculate_formula(  $main_formula = ''  ) {
    if ( !empty( $main_formula ) && 'disable' !== $main_formula ) {
        // change the all unused variables to zero, so formula calculation will not fail
        $pattern = "/{([^}]*)}/";
        $main_formula = preg_replace( $pattern, '0', $main_formula );
        // calculate
        $m = new EvalMath();
        $m->suppress_errors = true;
        $calc_price = $m->evaluate( $main_formula );
        $calc_price = ( !is_infinite( $calc_price ) && !is_nan( $calc_price ) ? $calc_price : 0 );
        return floatval( $calc_price );
    } else {
        return 0;
    }
}

//
function uni_cpo_option_js_condition_prepare(  $scheme  ) {
    if ( empty( $scheme['condition'] ) ) {
        return;
    }
    $condition_operator = $scheme['condition'];
    $operator = ( 'AND' === $condition_operator ? '&&' : '||' );
    $rules = $scheme['rules'];
    $rules_count = ( is_array( $rules ) ? count( $rules ) : 0 );
    if ( $rules_count > 1 ) {
        foreach ( $rules as $rule ) {
            if ( isset( $rule['rules'] ) ) {
                $statements[] = uni_cpo_option_js_condition_prepare( $rule );
            } else {
                $statements[] = uni_cpo_option_js_condition( $rule );
            }
        }
        $condition = '(' . implode( " {$operator} ", $statements ) . ')';
    } else {
        if ( is_array( $rules ) ) {
            foreach ( $rules as $rule ) {
                if ( isset( $rule['rules'] ) ) {
                    $statement = uni_cpo_option_js_condition_prepare( $rule );
                } else {
                    $statement = uni_cpo_option_js_condition( $rule );
                }
            }
            $condition = '(' . $statement . ')';
        }
    }
    return $condition;
}

// option condition js builder
function uni_cpo_option_js_condition(  $rule  ) {
    $cpo_var = 'formData';
    switch ( $rule['operator'] ) {
        case 'less':
            $statement = "UniCpo.isProp({$cpo_var}, '{$rule['id']}') && {$cpo_var}.{$rule['id']} < {$rule['value']}";
            break;
        case 'less_or_equal':
            $statement = "UniCpo.isProp({$cpo_var}, '{$rule['id']}') && {$cpo_var}.{$rule['id']} <= {$rule['value']}";
            break;
        case 'equal':
            $statement = "UniCpo.isProp({$cpo_var}, '{$rule['id']}') && ({$cpo_var}.{$rule['id']}.constructor === Array ? {$cpo_var}.{$rule['id']}.indexOf('{$rule['value']}') !== -1 : (window.UniCpo.isNumber('{$rule['value']}') ? parseFloat({$cpo_var}.{$rule['id']}) === parseFloat('{$rule['value']}') : {$cpo_var}.{$rule['id']} === '{$rule['value']}'))";
            break;
        case 'not_equal':
            $statement = "!UniCpo.isProp({$cpo_var}, '{$rule['id']}') || (UniCpo.isProp({$cpo_var}, '{$rule['id']}') && ({$cpo_var}.{$rule['id']}.constructor === Array ? {$cpo_var}.{$rule['id']}.indexOf('{$rule['value']}') === -1 : (window.UniCpo.isNumber('{$rule['value']}') ? parseFloat({$cpo_var}.{$rule['id']}) !== parseFloat('{$rule['value']}') : {$cpo_var}.{$rule['id']} !== '{$rule['value']}')))";
            break;
        case 'greater_or_equal':
            $statement = "UniCpo.isProp({$cpo_var}, '{$rule['id']}') && {$cpo_var}.{$rule['id']} >= {$rule['value']}";
            break;
        case 'greater':
            $statement = "UniCpo.isProp({$cpo_var}, '{$rule['id']}') && {$cpo_var}.{$rule['id']} > {$rule['value']}";
            break;
        case 'is_empty':
            $statement = "(R.isNil({$cpo_var}.{$rule['id']}) || R.isEmpty({$cpo_var}.{$rule['id']}))";
            break;
        case 'is_not_empty':
            $statement = "!(R.isNil({$cpo_var}.{$rule['id']}) || R.isEmpty({$cpo_var}.{$rule['id']}))";
            break;
        case 'between':
            if ( $rule['type'] === 'date' ) {
                $statement = "(UniCpo.isProp({$cpo_var}, '{$rule['id']}') && UniCpo.isDateBetween('{$rule['value'][0]}', '{$rule['value'][1]}', {$cpo_var}.{$rule['id']}))";
            } else {
                $statement = "(UniCpo.isProp({$cpo_var}, '{$rule['id']}') && {$cpo_var}.{$rule['id']} >= {$rule['value'][0]} && {$cpo_var}.{$rule['id']} <= {$rule['value'][1]})";
            }
            break;
        case 'not_between':
            if ( $rule['type'] === 'date' ) {
                $statement = "(UniCpo.isProp({$cpo_var}, '{$rule['id']}') && !UniCpo.isDateBetween('{$rule['value'][0]}', '{$rule['value'][1]}', {$cpo_var}.{$rule['id']}))";
            } else {
                $statement = "(UniCpo.isProp({$cpo_var}, '{$rule['id']}') && ({$cpo_var}.{$rule['id']} <= {$rule['value'][0]} || {$cpo_var}.{$rule['id']} >= {$rule['value'][1]}))";
            }
            break;
    }
    return $statement;
}

//////////////////////////////////////////////////////////////////////////////////////
// WC related functions and hooks
//////////////////////////////////////////////////////////////////////////////////////
/**
 * Format the price with a currency symbol. Adapted from wc_price()
 *
 * @param $price
 * @param array $args
 *
 * @return string
 */
function uni_cpo_price(  $price, $args = array()  ) {
    $defaults = array(
        'ex_tax_label'       => false,
        'currency'           => '',
        'decimal_separator'  => wc_get_price_decimal_separator(),
        'thousand_separator' => wc_get_price_thousand_separator(),
        'decimals'           => wc_get_price_decimals(),
        'price_format'       => get_woocommerce_price_format(),
    );
    $data = apply_filters( 'wc_price_args', wp_parse_args( $args, $defaults ) );
    $negative = $price < 0;
    $price = apply_filters( 'uni_cpo_price_raw', floatval( ( $negative ? $price * -1 : $price ) ) );
    $price = apply_filters(
        'formatted_uni_cpo_price',
        number_format(
            $price,
            $data['decimals'],
            $data['decimal_separator'],
            $data['thousand_separator']
        ),
        $price,
        $data['decimals'],
        $data['decimal_separator'],
        $data['thousand_separator']
    );
    if ( apply_filters( 'uni_cpo_price_trim_zeros', false ) && $data['decimals'] > 0 ) {
        $price = wc_trim_zeros( $price );
    }
    $formatted_price = (( $negative ? '-' : '' )) . sprintf( $data['price_format'], get_woocommerce_currency_symbol( $data['currency'] ), $price );
    if ( $data['ex_tax_label'] && wc_tax_enabled() ) {
        $formatted_price .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
    }
    return apply_filters(
        'uni_cpo_price',
        $formatted_price,
        $price,
        $args
    );
}

// customers try to add a product to the cart from an archive page? let's check if it is possible to do!
add_filter(
    'woocommerce_loop_add_to_cart_link',
    'uni_cpo_add_to_cart_button',
    10,
    2
);
function uni_cpo_add_to_cart_button(  $link, $product  ) {
    $product_id = intval( $product->get_id() );
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    if ( $product->is_in_stock() ) {
        $button_text = __( 'Select options', 'uni-cpo' );
    } else {
        $button_text = __( 'Out of stock / See details', 'uni-cpo' );
    }
    if ( 'on' === $product_data['settings_data']['cpo_enable'] ) {
        $link = sprintf(
            '<a rel="nofollow" href="%s" data-quantity="%s" data-product_id="%s" data-product_sku="%s" class="%s">%s</a>',
            esc_url( get_permalink( $product_id ) ),
            esc_attr( ( isset( $quantity ) ? $quantity : 1 ) ),
            esc_attr( $product->get_id() ),
            esc_attr( $product->get_sku() ),
            esc_attr( ( isset( $class ) ? $class : 'button' ) ),
            esc_html( $button_text )
        );
    }
    return $link;
}

//
add_action( 'uni_cpo_after_render_content', 'uni_cpo_calculate_button_html', 10 );
function uni_cpo_calculate_button_html() {
    global $post;
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $post->ID );
    if ( 'on' === $product_data['settings_data']['calc_btn_enable'] ) {
        $btn_text = apply_filters( 'uni_cpo_calculate_btn_text', '<i class="fas fa-calculator" aria-hidden="true"></i>' . esc_html__( 'Calculate', 'uni-cpo' ), $post->ID );
        echo '<button type="button" class="uni-cpo-calculate-btn js-uni-cpo-calculate-btn button alt">' . $btn_text . '</button>';
    }
}

//
add_action( 'uni_cpo_after_render_content', 'uni_cpo_reset_form_btn_html', 10 );
function uni_cpo_reset_form_btn_html() {
    global $post;
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $post->ID );
    if ( 'on' === $product_data['settings_data']['reset_form_btn'] ) {
        $btn_text = apply_filters( 'uni_cpo_reset_form_btn_text', '' . esc_html__( 'Reset form', 'uni-cpo' ), $post->ID );
        echo '<button type="button" class="uni-cpo-reset-form-btn js-uni-cpo-reset-form-btn button alt">' . $btn_text . '</button>';
    }
}

add_filter(
    'woocommerce_get_price_html',
    'uni_cpo_display_custom_price_on_archives',
    10,
    2
);
function uni_cpo_display_custom_price_on_archives(  $price, $product  ) {
    if ( is_admin() ) {
        return $price;
    }
    $product_id = intval( $product->get_id() );
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    $product_post_id = 0;
    global $wp_query;
    if ( isset( $wp_query->queried_object->post_content ) && has_shortcode( $wp_query->queried_object->post_content, 'product_page' ) ) {
        if ( has_shortcode( $wp_query->queried_object->post_content, 'product_page' ) ) {
            $pattern = '\\[(\\[?)(product_page)(?![\\w-])([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)(\\]?)';
            if ( preg_match_all( '/' . $pattern . '/s', $wp_query->queried_object->post_content, $matches ) && array_key_exists( 2, $matches ) && in_array( 'product_page', $matches[2] ) ) {
                foreach ( $matches[2] as $key => $value ) {
                    if ( $value === 'product_page' ) {
                        $parsed = shortcode_parse_atts( $matches[3][$key] );
                        if ( is_array( $parsed ) ) {
                            foreach ( $parsed as $attr_name => $attr_value ) {
                                if ( $attr_name === 'id' ) {
                                    $product_post_id = intval( $attr_value );
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if ( 'on' === $product_data['settings_data']['cpo_enable'] && 'on' === $product_data['settings_data']['calc_enable'] && (is_single() && $product_id !== $wp_query->queried_object_id || is_page() && $product_id !== $product_post_id || is_tax() || is_archive() || is_single() && !is_singular( 'product' ) && isset( $wp_query->queried_object->post_content ) && !has_shortcode( $wp_query->queried_object->post_content, 'product_page' ) || is_main_query() && !in_the_loop()) ) {
        $price = uni_cpo_get_proper_price_for_archive( $product );
        return $price;
    } else {
        return $price;
    }
}

function uni_cpo_get_proper_price_for_archive(  $product  ) {
    $defaults = array(
        'decimal_separator'  => wc_get_price_decimal_separator(),
        'thousand_separator' => wc_get_price_thousand_separator(),
        'decimals'           => wc_get_price_decimals(),
        'price_format'       => get_woocommerce_price_format(),
    );
    $product_id = intval( $product->get_id() );
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    $raw_regular_price = $product->get_regular_price( 'edit' );
    $raw_sale_price = $product->get_sale_price( 'edit' );
    $display_regular_price = apply_filters( 'uni_cpo_price_regular_archive', wc_get_price_to_display( $product, array(
        'price' => $raw_regular_price,
    ) ), $product );
    $display_sale_price = apply_filters( 'uni_cpo_price_sale_archive', wc_get_price_to_display( $product, array(
        'price' => $raw_sale_price,
    ) ), $product );
    $starting_price = 0;
    $is_using_archive_tmpl = false;
    $price = wc_get_price_to_display( $product );
    $display_regular_price = number_format(
        $display_regular_price,
        $defaults['decimals'],
        $defaults['decimal_separator'],
        $defaults['thousand_separator']
    );
    $display_sale_price = number_format(
        $display_sale_price,
        $defaults['decimals'],
        $defaults['decimal_separator'],
        $defaults['thousand_separator']
    );
    $starting_price = ( !empty( $product_data['settings_data']['min_price'] ) ? floatval( $product_data['settings_data']['min_price'] ) : $price );
    $starting_price = apply_filters( 'uni_cpo_price_starting_archive', $starting_price, $product );
    $price = uni_cpo_price( $starting_price );
    if ( $product->is_taxable() && $starting_price && !($is_using_archive_tmpl && $product->is_on_sale()) ) {
        $tax_suffix = $product->get_price_suffix( $starting_price );
        $price = $price . $tax_suffix;
    }
    return $price;
}

//
function uni_cpo_get_price_for_meta() {
    global $product;
    $product_id = intval( $product->get_id() );
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    if ( 'on' === $product_data['settings_data']['cpo_enable'] && 'on' === $product_data['settings_data']['calc_enable'] ) {
        $starting_price = ( !empty( $product_data['settings_data']['min_price'] ) ? floatval( $product_data['settings_data']['min_price'] ) : 0 );
        if ( 0 === $starting_price ) {
            $price = apply_filters( 'uni_cpo_display_price_meta_tag', $starting_price, $product );
            $price = wc_get_price_to_display( $product, array(
                'price' => $price,
            ) );
        } else {
            $price = wc_get_price_to_display( $product );
        }
        return $price;
    } else {
        return wc_get_price_to_display( $product );
    }
}

//
add_action( 'woocommerce_single_product_summary', 'uni_cpo_display_price_custom_meta', 11 );
function uni_cpo_display_price_custom_meta() {
    global $product;
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product->get_id() );
    if ( 'on' === $product_data['settings_data']['cpo_enable'] && 'on' === $product_data['settings_data']['calc_enable'] && (!empty( $product_data['settings_data']['min_price'] ) || !empty( $product_data['settings_data']['starting_price'] )) ) {
        $price = uni_cpo_get_price_for_meta();
        echo '<meta itemprop="minPrice" content="' . esc_attr( $price ) . '" itemtype="http://schema.org/PriceSpecification" />';
    }
}

//
function uni_cpo_get_display_price_reversed(  $product, $price  ) {
    $tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
    $price_incl = wc_get_price_including_tax( $product, array(
        'qty'   => 1,
        'price' => $price,
    ) );
    $price_excl = wc_get_price_excluding_tax( $product, array(
        'qty'   => 1,
        'price' => $price,
    ) );
    $display_price = ( $tax_display_mode == 'incl' ? $price_excl : $price_incl );
    return $display_price;
}

// displays a new and discounted price in the cart
function uni_cpo_change_cart_item_price(  $price, $cart_item  ) {
    $product_id = $cart_item['product_id'];
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    if ( 'on' === $product_data['settings_data']['cpo_enable'] && 'on' === $product_data['settings_data']['calc_enable'] ) {
        $product = wc_get_product( $product_id );
        $price_calc = wc_get_price_to_display( $product, array(
            'qty'   => 1,
            'price' => $cart_item['_cpo_price'],
        ) );
        $cpo_price = apply_filters( 'uni_cpo_get_cart_price_calculated_raw', $price_calc, $product_data );
        $cpo_price = wc_price( $cpo_price );
        return $cpo_price;
    } else {
        return $price;
    }
}

//
add_action(
    'woocommerce_before_calculate_totals',
    'uni_cpo_before_calculate_totals',
    10,
    1
);
function uni_cpo_before_calculate_totals(  $object  ) {
    if ( method_exists( $object, 'get_cart' ) ) {
        foreach ( $object->get_cart() as $cart_item_key => $values ) {
            $product = $values['data'];
            if ( $product->is_type( 'simple' ) && !empty( $object->coupons ) ) {
                foreach ( $object->coupons as $code => $coupon ) {
                    if ( $coupon->is_valid() && ($coupon->is_valid_for_product( $product, $values ) || $coupon->is_valid_for_cart()) ) {
                        if ( isset( $values['_cpo_price'] ) ) {
                            $product->set_price( $values['_cpo_price'] );
                        }
                    }
                }
            }
        }
    }
}

// associate with order's meta
add_filter(
    'woocommerce_add_cart_item_data',
    'uni_cpo_add_cart_item_data',
    10,
    2
);
add_filter(
    'woocommerce_get_cart_item_from_session',
    'uni_cpo_get_cart_item_from_session',
    10,
    3
);
add_filter(
    'woocommerce_add_cart_item',
    'uni_cpo_add_cart_item',
    10,
    1
);
// sets uni cpo's price after all plugins ;)
add_action(
    'woocommerce_cart_loaded_from_session',
    'uni_cpo_re_calculate_price',
    99,
    1
);
// get item data to display in cart and checkout page
add_filter(
    'woocommerce_get_item_data',
    'uni_cpo_get_item_data',
    10,
    2
);
// add meta data for each order item
add_action(
    'woocommerce_checkout_create_order_line_item',
    'uni_cpo_checkout_create_order_line_item',
    10,
    4
);
// Process cloud uploads at order creation - covers both classic and block checkout
add_action(
    'woocommerce_checkout_order_created',
    'uni_cpo_process_order_cloud_uploads_by_id_single_block',
    10,
    1
);
add_action(
    'woocommerce_store_api_checkout_order_processed',
    'uni_cpo_process_order_cloud_uploads_by_id_single_block',
    10,
    1
);
// Wrapper function for block checkout hook
function uni_cpo_process_order_cloud_uploads_by_id_single_block(  $order  ) {
    uni_cpo_process_order_cloud_uploads_by_id_single( $order->get_id() );
}

// adds custom option data to the cart
function uni_cpo_add_cart_item_data(  $cart_item_data, $product_id  ) {
    $product = wc_get_product( $product_id );
    $plugin_settings = UniCpo()->get_settings();
    if ( 'simple' !== $product->get_type() ) {
        return $cart_item_data;
    }
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    if ( isset( $_GET['add-to-cart'] ) && isset( $product_data['settings_data']['cpo_enable'] ) && 'on' === $product_data['settings_data']['cpo_enable'] ) {
        throw new Exception(__( 'Sorry, this product cannot be added to the cart in this way', 'uni-cpo' ));
    }
    try {
        // YITH bundle products compatibility start
        if ( 'on' !== $product_data['settings_data']['cpo_enable'] ) {
            $product = wc_get_product( $product_id );
            $add_cart_item_data_check = $product->is_type( 'yith_bundle' ) && (!isset( $cart_item_data['cartstamp'] ) || !isset( $cart_item_data['bundled_items'] ));
            $add_cart_item_data_check = apply_filters(
                'yith_wcpb_add_cart_item_data_check',
                $add_cart_item_data_check,
                $cart_item_data,
                $product_id
            );
            if ( !$add_cart_item_data_check ) {
                return $cart_item_data;
            }
        }
        // YITH bundle products compatibility end
        $qty_field_slug = $product_data['settings_data']['qty_field'];
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'uni_cpo_add_to_cart' ) {
            $form_data = wc_clean( $_POST['data'] );
        } elseif ( isset( $cart_item_data['cpo_data'] ) ) {
            // duplicating or ordering again
            $form_data = $cart_item_data;
        } else {
            $form_data = wc_clean( $_POST );
        }
        if ( 'on' === $product_data['settings_data']['cpo_enable'] ) {
            $cart_item_data['_cpo_enable'] = ( 'on' === $product_data['settings_data']['cpo_enable'] ? true : false );
            $cart_item_data['_cart_duplicate_enable'] = ( 'on' === $product_data['settings_data']['cart_duplicate_enable'] ? true : false );
            $cart_item_data['_cart_edit_full_enable'] = ( 'on' === $product_data['settings_data']['cart_edit_full_enable'] ? true : false );
            $cart_item_data['_cart_edit_enable'] = ( 'on' === $product_data['settings_data']['cart_edit_enable'] ? true : false );
            $cart_item_data['_cpo_calc_option'] = ( 'on' === $product_data['settings_data']['calc_enable'] ? true : false );
            $cart_item_data['_cpo_cart_item_id'] = ( !empty( $form_data['cpo_cart_item_id'] ) ? $form_data['cpo_cart_item_id'] : '' );
            $cart_item_data['_cpo_product_image'] = ( $plugin_settings['change_product_image_in_cart'] === 'on' && !empty( $form_data['cpo_product_image'] ) ? $form_data['cpo_product_image'] : '' );
            if ( $plugin_settings['change_product_image_in_cart'] === 'on' && !empty( $form_data['cpo_product_layered_image'] ) ) {
                $cart_item_data['_cpo_product_image'] = uni_cpo_upload_base64_image( $form_data['cpo_product_layered_image'], 'product_' . $form_data['cpo_product_id'] . '_image_' . time() );
            }
            $cart_item_data['_cpo_data'] = ( isset( $form_data['cpo_data'] ) ? $form_data['cpo_data'] : $form_data );
            // values to be unset
            $cart_item_data = apply_filters( 'uni_cpo_raw_cart_item_data', $cart_item_data, $product_id );
            // values to be unset
            $unset_values = apply_filters(
                'uni_cpo_add_to_cart_values_to_be_unset',
                array(
                    'cpo_cart_item_id',
                    'cpo_product_id',
                    'add-to-cart',
                    'cpo_data',
                    // without slash
                    'cpo_nov',
                    // without slash
                    'cpo_product_image',
                    'cpo_product_layered_image',
                    'quantity',
                ),
                $cart_item_data,
                $product_id
            );
            if ( !empty( $unset_values ) ) {
                foreach ( $unset_values as $v ) {
                    if ( isset( $form_data[$v] ) ) {
                        unset($form_data[$v]);
                    }
                    if ( isset( $cart_item_data[$v] ) ) {
                        unset($cart_item_data[$v]);
                    }
                }
            }
            if ( true === boolval( $cart_item_data['_cpo_calc_option'] ) ) {
                if ( !empty( $cart_item_data['_cpo_data'] ) ) {
                    $posts = uni_cpo_get_posts_by_slugs( array_keys( $cart_item_data['_cpo_data'] ) );
                    if ( !empty( $posts ) ) {
                        $posts_ids = wp_list_pluck( $posts, 'ID' );
                        foreach ( $posts_ids as $post_id ) {
                            $option = uni_cpo_get_option( $post_id );
                            if ( is_object( $option ) ) {
                                if ( 'extra_cart_button' === $option->get_type() ) {
                                    $cart_item_data['_cpo_is_free_sample'] = $option->calculate( $cart_item_data['_cpo_data'] );
                                    $post_name = trim( $option->get_slug(), '{}' );
                                    unset($cart_item_data['_cpo_data'][$post_name]);
                                    continue;
                                }
                            }
                        }
                    }
                }
                $price = uni_cpo_calculate_price_in_cart( $cart_item_data, $product_id );
            } else {
                $product = wc_get_product( $product_id );
                $price = $product->get_price();
            }
            $price = wc_format_decimal( $price );
            $cart_item_data['_cpo_price'] = $price;
        }
        return $cart_item_data;
    } catch ( Exception $e ) {
        if ( $e->getMessage() ) {
            wc_add_notice( $e->getMessage(), 'error' );
        }
        return false;
    }
}

//
function uni_cpo_get_cart_item_from_session(  $session_data, $values, $key  ) {
    $product = wc_get_product( $values['product_id'] );
    $plugin_settings = UniCpo()->get_settings();
    $session_data['_cpo_calc_option'] = ( 'simple' === $product->get_type() && isset( $values['_cpo_calc_option'] ) ? boolval( $values['_cpo_calc_option'] ) : false );
    $session_data['_cpo_cart_item_id'] = ( isset( $values['_cpo_cart_item_id'] ) ? $values['_cpo_cart_item_id'] : '' );
    $session_data['_cpo_product_image'] = ( isset( $values['_cpo_product_image'] ) && $plugin_settings['change_product_image_in_cart'] === 'on' ? $values['_cpo_product_image'] : '' );
    $session_data['_cpo_data'] = ( isset( $values['_cpo_data'] ) ? $values['_cpo_data'] : '' );
    if ( isset( $session_data['_cpo_data'] ) ) {
        return uni_cpo_add_cart_item( $session_data );
    } else {
        return $session_data;
    }
}

function uni_cpo_add_cart_item(  $cart_item_data  ) {
    $plugin_settings = UniCpo()->get_settings();
    $product_id = $cart_item_data['product_id'];
    $is_calc_enabled = ( isset( $cart_item_data['_cpo_calc_option'] ) ? boolval( $cart_item_data['_cpo_calc_option'] ) : false );
    // price calc
    if ( true === $is_calc_enabled && isset( $cart_item_data['_cpo_data'] ) ) {
        $price = uni_cpo_calculate_price_in_cart( $cart_item_data, $product_id );
        $price = wc_format_decimal( $price );
        $cart_item_data['_cpo_price'] = $price;
        $cart_item_data['data']->set_price( $cart_item_data['_cpo_price'] );
        if ( function_exists( 'UniCpoCustomSku' ) ) {
            $dynamic_sku = UniCpoCustomSku()->get_sku( $product_id, $cart_item_data['_cpo_data'] );
            if ( $dynamic_sku ) {
                $enable_stock = get_post_meta( $product_id, '_uni_cpo_customsku_stock_enable', true );
                $cart_item_data['data']->set_manage_stock( $enable_stock );
                if ( $enable_stock === 'yes' ) {
                    $stock_qty = UniCpoCustomSku()->get_stock_qty( $product_id, $dynamic_sku );
                    $cart_item_data['data']->set_stock_quantity( $stock_qty );
                }
            }
        }
    }
    if ( uni_cpo_is_checkout_block() && $plugin_settings['change_product_image_in_cart'] === 'on' && isset( $cart_item_data['_cpo_product_image'] ) && !empty( $cart_item_data['_cpo_product_image'] ) ) {
        $cart_item_data['data']->set_image_id( $cart_item_data['_cpo_product_image'] );
    }
    return $cart_item_data;
}

function uni_cpo_re_calculate_price(  $cart  ) {
    foreach ( $cart->cart_contents as $cart_item_key => $cart_item_data ) {
        if ( isset( $cart_item_data['_cpo_price'] ) ) {
            WC()->cart->cart_contents[$cart_item_key]['data']->set_price( $cart_item_data['_cpo_price'] );
            if ( function_exists( 'UniCpoCustomSku' ) ) {
                $product_id = $cart_item_data['data']->get_id();
                $dynamic_sku = UniCpoCustomSku()->get_sku( $product_id, $cart_item_data['_cpo_data'] );
                if ( $dynamic_sku ) {
                    $enable_stock = get_post_meta( $product_id, '_uni_cpo_customsku_stock_enable', true );
                    WC()->cart->cart_contents[$cart_item_key]['data']->set_manage_stock( $enable_stock );
                    if ( $enable_stock === 'yes' ) {
                        $stock_qty = UniCpoCustomSku()->get_stock_qty( $product_id, $dynamic_sku );
                        WC()->cart->cart_contents[$cart_item_key]['data']->set_stock_quantity( $stock_qty );
                    }
                }
            }
        }
    }
}

//
function uni_cpo_get_item_data(  $item_data, $cart_item  ) {
    if ( !empty( $cart_item['_cpo_data'] ) ) {
        // saves an information about chosen options and their values in cart meta
        $form_data = $cart_item['_cpo_data'];
        $formatted_vars = array();
        $variables = array();
        $filtered_form_data = array_filter( $form_data, function ( $k ) use($form_data) {
            return false !== strpos( $k, UniCpo()->get_var_slug() ) && !empty( $form_data[$k] );
        }, ARRAY_FILTER_USE_KEY );
        if ( !empty( $filtered_form_data ) ) {
            $posts = uni_cpo_get_posts_by_slugs( array_keys( $filtered_form_data ) );
            if ( !empty( $posts ) ) {
                $posts_ids = wp_list_pluck( $posts, 'ID' );
                foreach ( $posts_ids as $post_id ) {
                    $option = uni_cpo_get_option( $post_id );
                    if ( is_object( $option ) ) {
                        $post_name = trim( $option->get_slug(), '{}' );
                        $display_key = uni_cpo_sanitize_label( $option->cpo_order_label() );
                        $calculate_result = $option->calculate( $filtered_form_data );
                        if ( is_array( $calculate_result ) ) {
                            foreach ( $calculate_result as $k => $v ) {
                                if ( $post_name === $k ) {
                                    // excluding special vars
                                    if ( is_array( $v['cart_meta'] ) ) {
                                        $value = implode( ', ', $v['cart_meta'] );
                                    } else {
                                        $value = $v['cart_meta'];
                                    }
                                    if ( is_array( $v['order_meta'] ) ) {
                                        $v['order_meta'] = array_map( function ( $item ) {
                                            if ( !is_numeric( $item ) ) {
                                                return esc_html__( $item );
                                            } else {
                                                return $item;
                                            }
                                        }, $v['order_meta'] );
                                        $display_value = implode( ', ', $v['order_meta'] );
                                    } else {
                                        if ( !is_numeric( $v['order_meta'] ) ) {
                                            $display_value = esc_html__( $v['order_meta'] );
                                        } else {
                                            $display_value = $v['order_meta'];
                                        }
                                    }
                                    $item_data[] = array(
                                        'name'    => $option->get_slug(),
                                        'key'     => esc_html__( $display_key ),
                                        'value'   => $value,
                                        'display' => $display_value,
                                    );
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return $item_data;
}

// adds meta info for order items
function uni_cpo_checkout_create_order_line_item(
    $item,
    $cart_item_key,
    $values,
    $order
) {
    $plugin_settings = UniCpo()->get_settings();
    if ( isset( $values['_cpo_data'] ) ) {
        $form_data = $values['_cpo_data'];
        foreach ( $form_data as $name => $value ) {
            $item->add_meta_data( '_' . $name, $value );
        }
    }
    $additional_data = apply_filters(
        'uni_cpo_additional_item_data',
        array(),
        $item,
        $cart_item_key,
        $values
    );
    if ( !empty( $values['_cpo_product_image'] ) && $plugin_settings['change_product_image_in_cart'] === 'on' ) {
        $additional_data = $additional_data + array(
            '_uni_custom_item_image' => $values['_cpo_product_image'],
        );
    }
    if ( !empty( $additional_data ) && is_array( $additional_data ) ) {
        foreach ( $additional_data as $k => $v ) {
            $item->add_meta_data( $k, $v );
        }
    }
    // Cloud uploads will be processed after order creation via woocommerce_checkout_update_order_meta hook
}

/**
 * Process cloud uploads for all order items after order is fully created (by order ID)
 * 
 * @param int   $order_id Order ID
 * @param array $data     Posted checkout data (not used)
 */
function uni_cpo_process_order_cloud_uploads_by_id(  $order_id, $data = null, $order = null  ) {
    $hook_name = current_action();
    uni_cpo_log_cloud_operation( "=== HOOK TRIGGERED: {$hook_name} for order {$order_id} ===", 'info' );
    if ( !$order ) {
        $order = wc_get_order( $order_id );
    }
    if ( !$order ) {
        uni_cpo_log_cloud_operation( "ERROR: Could not load order {$order_id}", 'error' );
        return;
    }
    uni_cpo_process_order_cloud_uploads( $order );
}

function uni_cpo_process_order_cloud_uploads_by_id_single(  $order_id  ) {
    uni_cpo_log_cloud_operation( "=== HOOK TRIGGERED: Order creation hook for order {$order_id} ===", 'info' );
    uni_cpo_process_order_cloud_uploads_by_id( $order_id );
}

/**
 * Process cloud uploads for all order items after order is fully created
 * 
 * @param WC_Order $order WooCommerce order object
 */
function uni_cpo_process_order_cloud_uploads(  $order  ) {
    $plugin_settings = UniCpo()->get_settings();
    // Check if premium features are available
    $has_premium = unicpo_fs()->can_use_premium_code__premium_only();
    uni_cpo_log_cloud_operation( "Processing order {$order->get_id()} - Premium: " . (( $has_premium ? 'YES' : 'NO' )), 'info' );
    if ( !$has_premium ) {
        uni_cpo_log_cloud_operation( "Skipping cloud upload - premium features not available", 'warning' );
        return;
    }
    // Process each order item
    $any_uploads = false;
    $order_items = $order->get_items();
    uni_cpo_log_cloud_operation( "Order {$order->get_id()} has " . count( $order_items ) . " items to process", 'info' );
    foreach ( $order_items as $item_id => $item ) {
        uni_cpo_log_cloud_operation( "Processing order item {$item_id} for order {$order->get_id()}", 'info' );
        $result = uni_cpo_process_order_item_cloud_uploads( $item, $order->get_id(), $plugin_settings );
        uni_cpo_log_cloud_operation( "Item {$item_id} processing result: " . var_export( $result, true ), 'info' );
        if ( $result ) {
            $any_uploads = true;
        }
    }
    uni_cpo_log_cloud_operation( "Finished processing all items for order {$order->get_id()}. Any uploads: " . (( $any_uploads ? 'YES' : 'NO' )), 'info' );
    // Mark as processed to prevent duplicate processing
    if ( $any_uploads ) {
        update_post_meta( $order->get_id(), '_uni_cpo_cloud_processed', current_time( 'mysql' ) );
        uni_cpo_log_cloud_operation( "Marked order {$order->get_id()} as cloud processed", 'info' );
    }
}

/**
 * Process cloud uploads for order item file upload fields
 * 
 * @param WC_Order_Item_Product $item            Order item
 * @param int                   $order_id        Order ID
 * @param array                 $plugin_settings Plugin settings
 */
function uni_cpo_process_order_item_cloud_uploads(  $item, $order_id, $plugin_settings  ) {
    uni_cpo_log_cloud_operation( "=== STARTING cloud upload process for order {$order_id} ===", 'info' );
    $file_storage = ( isset( $plugin_settings['file_storage'] ) ? $plugin_settings['file_storage'] : 'local' );
    uni_cpo_log_cloud_operation( "File storage setting: {$file_storage}", 'info' );
    // Only process if not using local storage
    if ( $file_storage === 'local' ) {
        uni_cpo_log_cloud_operation( "Skipping cloud upload - using local storage", 'info' );
        return;
    }
    // Load cloud storage factory
    require_once UNI_CPO_ABSPATH . 'includes/class-uni-cpo-cloud-storage-factory.php';
    uni_cpo_log_cloud_operation( "Loaded cloud storage factory", 'info' );
    // Initialize cloud storage factory if not already done
    if ( !did_action( 'uni_cpo_cloud_storage_factory_init' ) ) {
        uni_cpo_log_cloud_operation( "Initializing cloud storage factory", 'info' );
        Uni_Cpo_Cloud_Storage_Factory::init();
        do_action( 'uni_cpo_cloud_storage_factory_init' );
    } else {
        uni_cpo_log_cloud_operation( "Cloud storage factory already initialized", 'info' );
    }
    $provider = Uni_Cpo_Cloud_Storage_Factory::get_provider( $file_storage, $plugin_settings );
    uni_cpo_log_cloud_operation( "Retrieved provider: " . (( $provider ? get_class( $provider ) : 'null' )), 'info' );
    if ( !$provider ) {
        uni_cpo_log_cloud_operation( "ERROR: Cloud provider '{$file_storage}' not available for order {$order_id}", 'error' );
        return;
    }
    $is_configured = $provider->is_configured();
    uni_cpo_log_cloud_operation( "Provider configured: " . (( $is_configured ? 'YES' : 'NO' )), 'info' );
    if ( !$is_configured ) {
        uni_cpo_log_cloud_operation( "ERROR: Cloud provider '{$file_storage}' not configured for order {$order_id}", 'error' );
        return;
    }
    // Get all order item meta to find file upload options
    $item_meta = $item->get_meta_data();
    $file_meta_keys = [];
    // Find meta keys that start with _uni_cpo_
    foreach ( $item_meta as $meta ) {
        $key = $meta->key;
        if ( strpos( $key, '_uni_cpo_' ) === 0 ) {
            // The full option slug includes the uni_cpo_ prefix (remove leading underscore only)
            $option_slug = substr( $key, 1 );
            // Remove only the leading underscore: _uni_cpo_test_fileupload -> uni_cpo_test_fileupload
            $file_meta_keys[$option_slug] = $key;
        }
    }
    if ( empty( $file_meta_keys ) ) {
        uni_cpo_log_cloud_operation( "No UniCPO meta fields found in order item", 'info' );
        return false;
    }
    uni_cpo_log_cloud_operation( "Found UniCPO meta keys: " . implode( ', ', array_keys( $file_meta_keys ) ), 'info' );
    // Get posts by slugs to check their types
    $posts = uni_cpo_get_posts_by_slugs( array_keys( $file_meta_keys ) );
    if ( empty( $posts ) ) {
        uni_cpo_log_cloud_operation( "No UniCPO option posts found for slugs: " . implode( ', ', array_keys( $file_meta_keys ) ), 'info' );
        return false;
    }
    $posts_ids = wp_list_pluck( $posts, 'ID' );
    $file_upload_options = [];
    foreach ( $posts_ids as $post_id ) {
        $option = uni_cpo_get_option( $post_id );
        if ( !$option ) {
            continue;
        }
        $type = $option->get_type();
        if ( in_array( $type, ['file_upload', 'multi_file_upload'] ) ) {
            $slug = $option->get_slug();
            $file_upload_options[$slug] = $file_meta_keys[$slug];
            // Keep the original meta key mapping
            uni_cpo_log_cloud_operation( "Found file upload option: {$slug} (type: {$type})", 'info' );
        }
    }
    if ( empty( $file_upload_options ) ) {
        uni_cpo_log_cloud_operation( "No file upload type options found", 'info' );
        return false;
    }
    uni_cpo_log_cloud_operation( "Found " . count( $file_upload_options ) . " file upload options: " . implode( ', ', array_keys( $file_upload_options ) ), 'info' );
    $updated = false;
    try {
        // Process each file upload option
        foreach ( $file_upload_options as $option_slug => $meta_key ) {
            $value = $item->get_meta( $meta_key, true );
            if ( empty( $value ) ) {
                uni_cpo_log_cloud_operation( "No value found for file upload option {$meta_key}", 'info' );
                continue;
            }
            uni_cpo_log_cloud_operation( "Processing file upload option {$meta_key} = " . (( is_string( $value ) ? $value : json_encode( $value ) )), 'info' );
            // Parse attachment IDs
            $attachment_ids = uni_cpo_parse_attachment_ids( $value );
            if ( empty( $attachment_ids ) ) {
                uni_cpo_log_cloud_operation( "Could not parse attachment IDs from {$meta_key}: " . $value, 'warning' );
                continue;
            }
            uni_cpo_log_cloud_operation( "Processing " . count( $attachment_ids ) . " attachments for {$meta_key}: " . implode( ', ', $attachment_ids ), 'info' );
            $cloud_urls = uni_cpo_upload_attachments_to_cloud(
                $attachment_ids,
                $order_id,
                $provider,
                $meta_key
            );
            if ( !empty( $cloud_urls ) ) {
                // Update order item meta with cloud URLs instead of attachment IDs
                $new_value = ( count( $cloud_urls ) === 1 ? $cloud_urls[0] : json_encode( $cloud_urls ) );
                $item->update_meta_data( $meta_key, $new_value );
                $updated = true;
                uni_cpo_log_cloud_operation( "Updated meta {$meta_key} with cloud URLs: " . $new_value, 'info' );
            } else {
                uni_cpo_log_cloud_operation( "No cloud URLs returned for field {$meta_key}", 'warning' );
            }
        }
    } catch ( Exception $e ) {
        uni_cpo_log_cloud_operation( "ERROR processing order item cloud uploads: " . $e->getMessage(), 'error' );
        return false;
    }
    if ( $updated ) {
        $item->save_meta_data();
        uni_cpo_log_cloud_operation( "=== COMPLETED: Updated order item meta for order {$order_id} with cloud URLs ===", 'info' );
    } else {
        uni_cpo_log_cloud_operation( "=== COMPLETED: No updates made for order {$order_id} ===", 'info' );
    }
    return $updated;
}

/**
 * Get file upload option slugs from product data
 * 
 * @param array $product_data Product configuration data
 * @return array Array of file upload option slugs
 */
function uni_cpo_get_file_upload_options_from_product_data(  $product_data  ) {
    $file_upload_options = array();
    if ( !isset( $product_data['content'] ) || !is_array( $product_data['content'] ) ) {
        return $file_upload_options;
    }
    foreach ( $product_data['content'] as $row ) {
        if ( !isset( $row['columns'] ) || !is_array( $row['columns'] ) ) {
            continue;
        }
        foreach ( $row['columns'] as $column ) {
            if ( !isset( $column['options'] ) || !is_array( $column['options'] ) ) {
                continue;
            }
            foreach ( $column['options'] as $option ) {
                if ( !isset( $option['type'], $option['slug'] ) ) {
                    continue;
                }
                // Check if this is a file upload option type
                if ( in_array( $option['type'], array('file_upload', 'multi_file_upload') ) ) {
                    $file_upload_options[] = $option['slug'];
                    uni_cpo_log_cloud_operation( "Found file upload option: {$option['slug']} (type: {$option['type']})", 'info' );
                }
            }
        }
    }
    return $file_upload_options;
}

/**
 * Parse attachment IDs from order meta value
 * 
 * @param mixed $value Meta value that may contain attachment IDs
 * @return array Array of attachment IDs
 */
function uni_cpo_parse_attachment_ids(  $value  ) {
    // Handle single numeric attachment ID
    if ( is_numeric( $value ) && intval( $value ) > 0 ) {
        $attachment_id = intval( $value );
        if ( get_post_type( $attachment_id ) === 'attachment' ) {
            return array($attachment_id);
        }
    }
    // Handle array of attachment IDs
    if ( is_array( $value ) ) {
        $attachment_ids = array();
        foreach ( $value as $item ) {
            if ( is_numeric( $item ) && intval( $item ) > 0 ) {
                $attachment_id = intval( $item );
                if ( get_post_type( $attachment_id ) === 'attachment' ) {
                    $attachment_ids[] = $attachment_id;
                }
            }
        }
        return $attachment_ids;
    }
    // Handle JSON-encoded array of attachment IDs
    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( is_array( $decoded ) ) {
            return uni_cpo_parse_attachment_ids( $decoded );
            // Recursive call
        }
    }
    return array();
}

/**
 * Check if a meta field contains file upload data (attachment IDs)
 * 
 * @param string $key   Meta key
 * @param mixed  $value Meta value
 * 
 * @return bool True if this is a file upload field
 */
function uni_cpo_is_file_upload_field(  $key, $value  ) {
    uni_cpo_log_cloud_operation( "DEBUG: Checking if {$key} is file upload field", 'info' );
    // Skip non-CPO fields
    if ( strpos( $key, '_' ) !== 0 ) {
        uni_cpo_log_cloud_operation( "DEBUG: {$key} doesn't start with underscore, skipping", 'info' );
        return false;
    }
    // Handle single numeric value (attachment ID)
    if ( is_numeric( $value ) && intval( $value ) > 0 ) {
        $attachment_id = intval( $value );
        if ( get_post_type( $attachment_id ) === 'attachment' ) {
            uni_cpo_log_cloud_operation( "DEBUG: {$key} is single attachment ID: {$attachment_id}", 'info' );
            return true;
        } else {
            uni_cpo_log_cloud_operation( "DEBUG: {$key} value {$value} is numeric but not an attachment", 'info' );
            return false;
        }
    }
    // Try to decode as JSON array of numbers (attachment IDs)
    $decoded = ( is_array( $value ) ? $value : json_decode( $value, true ) );
    if ( !is_array( $decoded ) || empty( $decoded ) ) {
        uni_cpo_log_cloud_operation( "DEBUG: {$key} value is not array or empty after decoding", 'info' );
        return false;
    }
    uni_cpo_log_cloud_operation( "DEBUG: {$key} decoded as array: " . json_encode( $decoded ), 'info' );
    // Check if all values are numeric (attachment IDs)
    foreach ( $decoded as $item ) {
        if ( !is_numeric( $item ) || intval( $item ) <= 0 ) {
            uni_cpo_log_cloud_operation( "DEBUG: {$key} contains non-numeric or invalid item: {$item}", 'info' );
            return false;
        }
    }
    // Verify these are actual attachment IDs
    foreach ( $decoded as $attachment_id ) {
        if ( get_post_type( $attachment_id ) !== 'attachment' ) {
            uni_cpo_log_cloud_operation( "DEBUG: {$key} item {$attachment_id} is not an attachment", 'info' );
            return false;
        }
    }
    uni_cpo_log_cloud_operation( "DEBUG: {$key} is confirmed as file upload field", 'info' );
    return true;
}

/**
 * Upload attachments to cloud storage and delete local copies
 * 
 * @param array                           $attachment_ids Array of attachment IDs
 * @param int                             $order_id       Order ID
 * @param Uni_Cpo_Cloud_Storage_Interface $provider      Cloud provider
 * @param string                          $field_key     Field key for logging
 * 
 * @return array Array of cloud URLs
 */
function uni_cpo_upload_attachments_to_cloud(
    $attachment_ids,
    $order_id,
    $provider,
    $field_key
) {
    uni_cpo_log_cloud_operation( "Starting upload for field {$field_key} with " . count( $attachment_ids ) . " attachments", 'info' );
    $cloud_urls = array();
    foreach ( $attachment_ids as $attachment_id ) {
        $attachment_id = intval( $attachment_id );
        uni_cpo_log_cloud_operation( "Processing attachment {$attachment_id}", 'info' );
        $file_path = get_attached_file( $attachment_id );
        uni_cpo_log_cloud_operation( "File path for attachment {$attachment_id}: " . (( $file_path ?: 'NULL' )), 'info' );
        if ( !$file_path || !file_exists( $file_path ) ) {
            uni_cpo_log_cloud_operation( "ERROR: Local file not found for attachment {$attachment_id} in order {$order_id}", 'error' );
            $cloud_urls[] = $attachment_id;
            // Fallback to attachment ID
            continue;
        }
        $file_size = filesize( $file_path );
        $filename = basename( $file_path );
        $cloud_path = "/order-{$order_id}/{$filename}";
        uni_cpo_log_cloud_operation( "File details - Size: {$file_size} bytes, Name: {$filename}, Cloud path: {$cloud_path}", 'info' );
        // Upload to cloud with retry logic
        uni_cpo_log_cloud_operation( "Starting cloud upload for {$filename}", 'info' );
        $upload_result = uni_cpo_upload_with_retry(
            $provider,
            $file_path,
            $cloud_path,
            2
        );
        uni_cpo_log_cloud_operation( "Upload result: " . json_encode( $upload_result ), 'info' );
        if ( $upload_result['success'] ) {
            $cloud_urls[] = $upload_result['url'];
            uni_cpo_log_cloud_operation( "SUCCESS: Uploaded {$filename} to {$upload_result['url']}", 'info' );
            // Delete local attachment after successful upload
            uni_cpo_log_cloud_operation( "Deleting local attachment {$attachment_id}", 'info' );
            $delete_result = wp_delete_attachment( $attachment_id, true );
            if ( $delete_result ) {
                uni_cpo_log_cloud_operation( "Successfully uploaded and deleted attachment {$attachment_id} for order {$order_id}", 'info' );
            } else {
                uni_cpo_log_cloud_operation( "Uploaded attachment {$attachment_id} but failed to delete local copy for order {$order_id}", 'warning' );
            }
        } else {
            $error_msg = ( isset( $upload_result['error'] ) ? $upload_result['error'] : 'Unknown error' );
            uni_cpo_log_cloud_operation( "ERROR: Failed to upload attachment {$attachment_id} for order {$order_id}: {$error_msg}", 'error' );
            $cloud_urls[] = $attachment_id;
            // Fallback to attachment ID
        }
    }
    uni_cpo_log_cloud_operation( "Completed upload for field {$field_key}. Cloud URLs: " . json_encode( $cloud_urls ), 'info' );
    return $cloud_urls;
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
function uni_cpo_upload_with_retry(
    $provider,
    $file_path,
    $cloud_path,
    $max_retries = 2
) {
    $attempt = 1;
    $last_result = null;
    while ( $attempt <= $max_retries ) {
        $result = $provider->upload_file( $file_path, $cloud_path );
        if ( $result['success'] ) {
            if ( $attempt > 1 ) {
                uni_cpo_log_cloud_operation( "Upload succeeded on attempt {$attempt} for {$cloud_path}", 'info' );
            }
            return $result;
        }
        $last_result = $result;
        uni_cpo_log_cloud_operation( "Upload attempt {$attempt} failed for {$cloud_path}: {$result['error']}", 'warning' );
        $attempt++;
        if ( $attempt <= $max_retries ) {
            sleep( 1 );
            // Brief delay before retry
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
function uni_cpo_log_cloud_operation(  $message, $level = 'info'  ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( '[Uni CPO Cloud Storage] [%s] %s', strtoupper( $level ), $message ) );
    }
}

//
function uni_cpo_calculate_price_in_cart(  &$cart_item_data, $product_id  ) {
    try {
        $product = wc_get_product( $product_id );
        $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
        $form_data = $cart_item_data['_cpo_data'];
        $options_eval_result = array();
        $variables = array();
        $is_calc_disabled = false;
        $formatted_vars = array();
        $is_free_sample = ( isset( $cart_item_data['_cpo_is_free_sample'] ) ? $cart_item_data['_cpo_is_free_sample'] : false );
        $is_calc_weight = false;
        $is_calc_dimensions = false;
        $is_set_shipping_class = false;
        $main_formula = $product_data['formula_data']['main_formula'];
        $filtered_form_data = array_filter( $form_data, function ( $k ) use($form_data) {
            return false !== strpos( $k, UniCpo()->get_var_slug() ) && !empty( $form_data[$k] );
        }, ARRAY_FILTER_USE_KEY );
        if ( !empty( $filtered_form_data ) ) {
            $posts = uni_cpo_get_posts_by_slugs( array_keys( $filtered_form_data ) );
            if ( !empty( $posts ) ) {
                $posts_ids = wp_list_pluck( $posts, 'ID' );
                foreach ( $posts_ids as $post_id ) {
                    $option = uni_cpo_get_option( $post_id );
                    if ( is_object( $option ) ) {
                        $calculate_result = $option->calculate( $filtered_form_data );
                        if ( !empty( $calculate_result ) ) {
                            $options_eval_result[$option->get_slug()] = $calculate_result;
                        }
                    }
                }
            }
        }
        array_walk( $options_eval_result, function ( $v ) use(&$variables, &$formatted_vars) {
            foreach ( $v as $slug => $value ) {
                // prepare $variables for calculation purpose
                $variables['{' . $slug . '}'] = $value['calc'];
                // prepare $formatted_vars for conditional logic purpose
                $formatted_vars[$slug] = $value['cart_meta'];
            }
        } );
        $variables['{uni_cpo_price}'] = $product->get_price( 'edit' );
        // non option variables
        if ( 'on' === $product_data['nov_data']['nov_enable'] && !empty( $product_data['nov_data']['nov'] ) ) {
            $variables = uni_cpo_process_formula_with_non_option_vars( $variables, $product_data, $formatted_vars );
        }
        // formula conditional logic
        if ( 'on' === $product_data['formula_data']['rules_enable'] && !empty( $product_data['formula_data']['formula_scheme'] ) && is_array( $product_data['formula_data']['formula_scheme'] ) ) {
            $conditional_formula = uni_cpo_process_formula_scheme( $formatted_vars, $product_data );
            if ( $conditional_formula ) {
                $main_formula = $conditional_formula;
            }
        }
        if ( 'disable' === $main_formula || 0 === $is_free_sample ) {
            $is_calc_disabled = true;
        }
        //
        if ( !$is_calc_disabled ) {
            $main_formula = uni_cpo_process_formula_with_vars( $main_formula, $variables );
            // calculates formula
            $price_calculated = uni_cpo_calculate_formula( $main_formula );
            $price_min = $product_data['settings_data']['min_price'];
            $price_max = $product_data['settings_data']['max_price'];
            // check for min price
            if ( $price_calculated < $price_min ) {
                $price_calculated = $price_min;
            }
            // check for max price
            if ( !empty( $price_max ) && $price_calculated >= $price_max ) {
                $is_calc_disabled = true;
            }
            if ( true !== $is_calc_disabled ) {
                // filter, so 3rd party scripts can hook up
                $price_calculated = apply_filters(
                    'uni_cpo_in_cart_calculated_price',
                    $price_calculated,
                    $product,
                    $filtered_form_data
                );
                return $price_calculated;
            } else {
                return $price_max;
            }
        } else {
            return 0;
        }
    } catch ( Exception $e ) {
        return new WP_Error('cart-error', $e->getMessage());
    }
}

//
add_filter(
    'woocommerce_order_again_cart_item_data',
    'uni_cpo_woocommerce_order_again_cart_item_data',
    10,
    3
);
function uni_cpo_woocommerce_order_again_cart_item_data(  $cart_item_meta, $item, $order  ) {
    $product_id = $item->get_product_id();
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    return uni_cpo_re_add_cpo_item_data( $cart_item_meta, $item->get_meta_data(), $product_data );
}

//
function uni_cpo_re_add_cpo_item_data(
    $item_data,
    $raw_data,
    $product_data,
    $is_duplicate = false
) {
    $plugin_settings = UniCpo()->get_settings();
    if ( 'on' === $product_data['settings_data']['cpo_enable'] ) {
        $item_data['_cpo_calc_option'] = ( 'on' === $product_data['settings_data']['calc_enable'] ? true : false );
        $item_data['cpo_cart_item_id'] = current_time( 'timestamp' );
        $item_data['cpo_product_image'] = ( isset( $raw_data['_cpo_product_image'] ) && $plugin_settings['change_product_image_in_cart'] === 'on' ? $raw_data['_cpo_product_image'] : '' );
        unset($item_data['cpo_price']);
        if ( is_array( $raw_data ) ) {
            foreach ( $raw_data as $k => $v ) {
                if ( is_array( $v ) ) {
                    if ( false !== strpos( $k, '_cpo' ) ) {
                        $meta_key_new = ltrim( $k, '_' );
                        if ( false !== strpos( $k, 'uni_cpo' ) ) {
                            $item_data['_cpo_data'][$meta_key_new] = $v;
                        } else {
                            $item_data[$meta_key_new] = $v;
                        }
                    }
                } elseif ( is_a( $v, 'WC_Meta_Data' ) ) {
                    $meta_data = $v->get_data();
                    if ( false !== strpos( $meta_data['key'], '_cpo' ) ) {
                        $meta_key_new = ltrim( $meta_data['key'], '_' );
                        if ( false !== strpos( $meta_data['key'], 'uni_cpo' ) ) {
                            $item_data['_cpo_data'][$meta_key_new] = $meta_data['value'];
                        } else {
                            $item_data[$meta_key_new] = $meta_data['value'];
                        }
                    }
                    if ( '_uni_custom_item_image' === $meta_data['key'] ) {
                        $item_data['cpo_product_image'] = $meta_data['value'];
                    }
                }
            }
        }
    }
    return $item_data;
}

//
function uni_cpo_get_options_data(  $product_id, $variables = [], $formatted_vars = []  ) {
    $product_data = Uni_Cpo_Product::get_product_data_by_id( $product_id );
    $content = $product_data['content'];
    $options_data = array();
    if ( is_array( $content ) && !empty( $content ) ) {
        array_walk( $content, function ( $row, $row_key ) use(
            &$options_data,
            $variables,
            $formatted_vars,
            $product_data
        ) {
            if ( is_array( $row['columns'] ) && !empty( $row['columns'] ) ) {
                array_walk( $row['columns'], function ( $column, $column_key ) use(
                    &$options_data,
                    $row_key,
                    $variables,
                    $formatted_vars,
                    $product_data
                ) {
                    if ( is_array( $column['modules'] ) && !empty( $column['modules'] ) ) {
                        array_walk( $column['modules'], function ( $module ) use(
                            &$options_data,
                            $row_key,
                            $column_key,
                            $variables,
                            $formatted_vars,
                            $product_data
                        ) {
                            if ( !empty( $module['settings']['cpo_general']['main']['cpo_slug'] ) ) {
                                $slug = UniCpo()->get_var_slug() . $module['settings']['cpo_general']['main']['cpo_slug'];
                                $required = ( isset( $module['settings']['cpo_general']['main']['cpo_is_required'] ) ? $module['settings']['cpo_general']['main']['cpo_is_required'] === 'yes' : 'no' );
                                $label = ( isset( $module['settings']['cpo_general']['advanced']['cpo_label'] ) ? __( $module['settings']['cpo_general']['advanced']['cpo_label'] ) : '' );
                                $cartlabel = ( isset( $module['settings']['cpo_general']['advanced']['cpo_order_label'] ) ? __( $module['settings']['cpo_general']['advanced']['cpo_order_label'] ) : '' );
                                $suboptions = ( isset( $module['settings']['cpo_suboptions']['data']['cpo_radio_options'] ) ? $module['settings']['cpo_suboptions']['data']['cpo_radio_options'] : (( isset( $module['settings']['cpo_suboptions']['data']['cpo_select_options'] ) ? $module['settings']['cpo_suboptions']['data']['cpo_select_options'] : array() )) );
                                $suboptions_formatted = array();
                                $colorify_data = array();
                                $is_imagify = ( !empty( $module['settings']['cpo_general']['main']['cpo_is_imagify'] ) && 'yes' === $module['settings']['cpo_general']['main']['cpo_is_imagify'] ? true : false );
                                if ( !empty( $suboptions ) ) {
                                    foreach ( $suboptions as $suboption ) {
                                        if ( !empty( $suboption['label'] ) && !empty( $suboption['slug'] ) ) {
                                            $suboptions_formatted[$suboption['slug']]['label'] = __( $suboption['label'] );
                                            $suboptions_formatted[$suboption['slug']]['rate'] = floatVal( $suboption['rate'] );
                                            if ( !empty( $suboption['attach_id'] ) || !empty( $suboption['attach_id_r'] ) ) {
                                                $replacement_attach_id = ( !empty( $suboption['attach_id_r'] ) ? $suboption['attach_id_r'] : $suboption['attach_id'] );
                                                $replacement_attach_uri = ( !empty( $suboption['attach_uri_r'] ) ? $suboption['attach_uri_r'] : $suboption['attach_uri'] );
                                                $image_thumb = wp_get_attachment_image_src( $replacement_attach_id, 'woocommerce_single' );
                                                if ( isset( $image_thumb[0] ) ) {
                                                    $suboptions_formatted[$suboption['slug']]['imagify']['src'] = $image_thumb[0];
                                                    if ( !empty( $suboption['def'] ) ) {
                                                        $suboptions_formatted[$suboption['slug']]['imagify']['def'] = $image_thumb[0];
                                                    }
                                                } else {
                                                    $suboptions_formatted[$suboption['slug']]['imagify']['src'] = $replacement_attach_uri;
                                                }
                                            }
                                        }
                                    }
                                }
                                if ( !empty( $module['settings']['cpo_general']['main']['cpo_encoded_image'] ) && !empty( $module['settings']['cpo_general']['main']['cpo_slug'] ) ) {
                                    $colorify_data = array(
                                        'img_encoded' => $module['settings']['cpo_general']['main']['cpo_encoded_image'],
                                    );
                                }
                                $options_data[$slug] = array(
                                    'type'             => $module['type'],
                                    'required'         => $required,
                                    'label'            => $label,
                                    'cartLabel'        => $cartlabel,
                                    'suboptions'       => $suboptions_formatted,
                                    'colorify'         => $colorify_data,
                                    'is_imagify'       => $is_imagify,
                                    'is_dynamic_label' => true,
                                );
                            }
                        } );
                    }
                } );
            }
        } );
    }
    return $options_data;
}

//
function uni_cpo_get_options_data_for_frontend(  $product_id, $variables = [], $formatted_vars = []  ) {
    if ( is_singular( 'product' ) || wp_doing_ajax() ) {
        return uni_cpo_get_options_data( $product_id, $variables, $formatted_vars );
    }
}

function uni_cpo_replace_curly(
    $string,
    $formatted_vars,
    $product_data,
    $variables = array()
) {
    preg_match_all( '/{{{(\\w+)}}}/', $string, $matches );
    foreach ( $matches[0] as $index => $var_name ) {
        $stripped_var_name = trim( $var_name, '{{{' );
        $stripped_var_name = trim( $stripped_var_name, '}}}' );
        if ( !empty( $variables ) ) {
            $variables = uni_cpo_process_formula_with_non_option_vars( $variables, $product_data, $formatted_vars );
        }
        $stripped_variables = array();
        foreach ( $variables as $key => $value ) {
            $stripped_variables[trim( $key, '{}' )] = $value;
        }
        if ( isset( $stripped_variables[$stripped_var_name] ) ) {
            $string = str_replace( $var_name, $stripped_variables[$stripped_var_name], $string );
        } else {
            $string = str_replace( $var_name, '', $string );
        }
    }
    return $string;
}

//
function uni_cpo_woocommerce_add_to_cart_validation(
    $result,
    $product_id,
    $quantity,
    $variation_id,
    $variations,
    $cart_item_data
) {
    if ( !empty( $cart_item_data['_cpo_data'] ) ) {
        $cart_item_opt_keys = array_keys( $cart_item_data['_cpo_data'] );
        $options_data = uni_cpo_get_options_data( $product_id );
        $missing_opts = [];
        foreach ( $options_data as $opt_slug => $opt_data ) {
            if ( $opt_data['required'] === true && !in_array( $opt_slug, $cart_item_opt_keys ) ) {
                $missing_opts[$opt_slug] = $opt_data;
            }
        }
        if ( !empty( $missing_opts ) ) {
            return false;
        }
        $posts = uni_cpo_get_posts_by_slugs( $cart_item_opt_keys );
        if ( !empty( $posts ) ) {
            $posts_ids = wp_list_pluck( $posts, 'ID' );
            foreach ( $posts_ids as $post_id ) {
                $option = uni_cpo_get_option( $post_id );
                $option_type = $option::get_type();
                $option_slug = $option->get_slug();
                $suboptions_type = 'cpo_radio_options';
                if ( $option_type === 'select' ) {
                    $suboptions_type = 'cpo_select_options';
                }
                $suboptions_meta = get_post_meta( $post_id, '_cpo_suboptions', true );
                $suboptions = ( isset( $suboptions_meta['data'][$suboptions_type] ) ? $suboptions_meta['data'][$suboptions_type] : array() );
                if ( !empty( $suboptions ) ) {
                    $existing_slug_vals = wp_list_pluck( $suboptions, 'slug' );
                    $slug_val = ( !empty( $cart_item_data['_cpo_data'][$option_slug] ) ? $cart_item_data['_cpo_data'][$option_slug] : '' );
                    if ( !empty( $slug_val ) && !in_array( $slug_val, $existing_slug_vals ) ) {
                        $result = false;
                    }
                }
            }
        }
    }
    return $result;
}
