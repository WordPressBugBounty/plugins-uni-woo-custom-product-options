<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/*
* Uni_Cpo_Setting_Align_Items class
*
*/

class Uni_Cpo_Setting_Align_Items extends Uni_Cpo_Setting implements Uni_Cpo_Setting_Interface
{

    /**
     * Init
     *
     */
    public function __construct()
    {
        $this->setting_key = 'align_items';
        $this->setting_data = array(
            'title' => __('Align Items', 'uni-cpo'),
            'type' => 'text',
            'options' => array(
                'start' => __('start'),
                'end' => __('end'),
                'center' => __('center'),
                'inherit' => __('inherit'),
                'unset' => __('unset')
            ),
            'js_var' => 'data'
        );
        add_action('wp_footer', array($this, 'js_template'), 10);
    }

    /**
     * A template for the module
     *
     * @return string
     * @since 1.0
     */
    public function js_template()
    {
        ?>
        <script id="js-builderius-setting-<?php echo $this->setting_key; ?>-tmpl" type="text/template">
            <div class="uni-modal-row uni-clear" data-uni-constrained="select[name=display]"
                 data-uni-constvalue="flex|inline-flex">
                <?php echo $this->generate_field_label_html() ?>
                <div class="uni-modal-row-second">
                    <?php echo $this->generate_select_html() ?>
                </div>
            </div>
        </script>
        <?php
    }

}
