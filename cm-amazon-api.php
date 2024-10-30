<?php
CMTooltipGlossaryAmazonSupportAPI::init();
register_activation_hook(CMTTAS_PLUGIN_FILE, array('CMTooltipGlossaryAmazonSupportAPI', '_install'));

class CMTooltipGlossaryAmazonSupportAPI
{
    const TABLENAME = 'glossary_database_cache';
    const AMAZON_CACHE_GROUP = 'cmtt_amazon_cache';
    const AMAZON_ENABLED_KEY = 'cmtt_tooltip3RD_AmazonEnabled';
    const AMAZON_AFFILIATE_CODE_POST_KEY = 'cmtt_tooltip3RD_AmazonApiKey';
    const AMAZON_CATEGORY_POST_KEY = 'cmtt_tooltip3RD_AmazonCategories';
    const AMAZON_CATEGORY_META_KEY = '_cmtt_amazon_category';
    const AMAZON_SIZE_POST_KEY = 'cmtt_tooltip3RD_AmazonSizes';
    const AMAZON_SIZE_META_KEY = '_cmtt_amazon_size';
    const AMAZON_SEARCH_POST_KEY = 'cmtt_tooltip3RD_AmazonSearchKey';
    const AMAZON_SEARCH_META_KEY = '_cmtt_amazon_search';
    const AMAZON_DISABLED_META_KEY = '_cmtt_amazon_disabled';

    public static $tableExists = false;
    public static $calledClassName;
    protected static $viewsPath = NULL;

    public static function _install($networkwide)
    {
        global $wpdb;

        if( function_exists('is_multisite') && is_multisite() )
        {
            /*
             * Check if it is a network activation - if so, run the activation function for each blog id
             */
            if( $networkwide )
            {
                /*
                 * Get all blog ids
                 */
                $blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM {$wpdb->blogs}"));
                foreach($blogids as $blog_id)
                {
                    switch_to_blog($blog_id);
                    self::__install();
                }
                restore_current_blog();
                return;
            }
        }

        self::__install();
    }

    private static function __install()
    {
//        global $wpdb;
//        $sql = "CREATE TABLE {$wpdb->prefix}" . self::TABLENAME . " (
//                id INT(11) NOT NULL AUTO_INCREMENT,
//                term VARCHAR(64) NOT NULL,
//                thesaurus TEXT NULL,
//                amazon TEXT NULL,
//                translate_title TEXT NULL,
//                translate_content TEXT NULL,
//                UNIQUE KEY  (id)
//              )
//              CHARACTER SET utf8 COLLATE utf8_general_ci;";
//        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//        dbDelta($sql);
//        $sql = "DELETE FROM " . $wpdb->prefix . self::TABLENAME . "";
//        $wpdb->query($sql);
        return;
    }

    public static function init()
    {
        if( empty(self::$calledClassName) )
        {
            self::$calledClassName = __CLASS__;
        }

        self::$viewsPath = CMTTAS_PLUGIN_DIR . 'views/';

//        add_action('add_meta_boxes', array(self::$calledClassName, 'registerBoxes'));
//        add_action('save_post', array(self::$calledClassName, 'savePostdata'));
//        add_action('update_post', array(self::$calledClassName, 'savePostdata'));

        add_filter('cmtt_thirdparty_option_names', array(self::$calledClassName, 'addOptionNames'));
//        add_filter('cmtt_add_properties_metabox', array(self::$calledClassName, 'addToExcludeMetabox'));
        add_filter('cmtt_tooltip_content_add', array(self::$calledClassName, 'addWidgetToTooltipContent'), 10, 2);

        add_filter('cmtt-settings-tabs-array', array(self::$calledClassName, 'addSettingsTab'));

        /*
         * Tooltips have to be clickable for this extension to have sense
         */
        update_option('cmtt_tooltipIsClickable', 1);
    }

    /**
     * This function setups the basic options for the plugin
     */
    public static function setupBasicOptions()
    {
        update_option(self::AMAZON_ENABLED_KEY, 1);
        update_option(self::AMAZON_AFFILIATE_CODE_POST_KEY, '');
        update_option(self::AMAZON_CATEGORY_POST_KEY, '');
        update_option(self::AMAZON_SIZE_POST_KEY, '120x150');
        update_option(self::AMAZON_SEARCH_POST_KEY, '');
    }

    /**
     * This function setups the basic options for the plugin
     */
    public static function deleteOptions()
    {
        $options = self::addOptionNames(array());
        foreach($options as $optionName)
        {
            delete_option($optionName);
        }

        /*
         * We may change this option here - if there's any other extension requiring tooltips to be clickable
         * it will reenable it anyway
         */
        update_option('cmtt_tooltipIsClickable', 0);
    }

    /**
     * Returns the list of post types for which the custom settings may be applied
     * @return type
     */
    public static function addSettingsTab($tabs)
    {
        if( !in_array('API', $tabs) )
        {
            $tabs += array('5' => 'API');
        }
        add_filter('cmmt-custom-settings-tab-content-5', array(self::$calledClassName, 'addSettingsTabContent'));
        return $tabs;
    }

    /**
     * Adds the content to the appropriate settings tab
     * @return type
     */
    public static function addSettingsTabContent($content)
    {
        ob_start();
        require_once self::$viewsPath . 'backend/amazon_settings.phtml';
        $content .= ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Returns the list of post types for which the custom settings may be applied
     * @return type
     */
    public static function getApplicablePostTypes()
    {
        return array('glossary');
    }

    /**
     * Adds the Amazon options to the saved options
     * @return string
     */
    public static function addOptionNames($option_names)
    {
        $option_names = array_merge($option_names, array(
            self::AMAZON_ENABLED_KEY,
            self::AMAZON_AFFILIATE_CODE_POST_KEY,
            self::AMAZON_CATEGORY_POST_KEY,
            self::AMAZON_SIZE_POST_KEY,
            self::AMAZON_SEARCH_POST_KEY,
                )
        );
        return $option_names;
    }

    /**
     * Register metaboxes
     */
    public static function registerBoxes()
    {
        foreach(self::getApplicablePostTypes() as $postType)
        {
            add_meta_box('cmttas-amazon-metabox', 'CM Tooltip - Amazon', array(self::$calledClassName, 'showMetaBox'), $postType, 'side', 'high');
        }
    }

    /**
     * Shows metabox containing selectbox with amazon category ID which should be advertised in the Tooltips on this page
     * @global type $post
     */
    public static function showMetaBox()
    {
        global $post;
        $selectedCategoryMetaKey = get_post_meta($post->ID, self::AMAZON_CATEGORY_META_KEY, true);
        $selectedSizeMetaKey = get_post_meta($post->ID, self::AMAZON_SIZE_META_KEY, true);

        echo self::getCategoryListSelect(self::AMAZON_CATEGORY_POST_KEY, $selectedCategoryMetaKey);
        echo self::getSizeListSelect(self::AMAZON_SIZE_POST_KEY, $selectedSizeMetaKey);
    }

    public static function addToExcludeMetabox($excluded)
    {
        $excluded = array_merge($excluded, array(
            substr(self::AMAZON_DISABLED_META_KEY, 1) => 'Don\'t show Amazon Widget for term')
        );
        return $excluded;
    }

    /**
     * Returns the HTML of the Amazon category list select
     * @param string $selectedValue - selected value of the select
     * @return string (HTML)
     */
    public static function getCategoryListSelect($selectName = self::AMAZON_CATEGORY_POST_KEY, $selectedValue = '')
    {
        ob_start();
        if( empty($selectedValue) )
        {
            $selectedValue = '0';
        }
        require 'views/backend/amazon_categories.phtml';
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Returns the HTML of the Amazon size list select
     * @param string $selectedValue - selected value of the select
     * @return string (HTML)
     */
    public static function getSizeListSelect($selectName = self::AMAZON_SIZE_POST_KEY, $selectedValue = '')
    {
        ob_start();
        if( empty($selectedValue) )
        {
            $selectedValue = '0';
        }
        require 'views/backend/amazon_sizes.phtml';
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Returns the HTML of the Amazon size list select
     * @param string $selectedValue - selected value of the select
     * @return string (HTML)
     */
    public static function getAffiltiateWidget($searchKey = '', $categoryKey = '', $widgetSize = '')
    {
        $content = '';

        if( empty($categoryKey) )
        {
            $categoryKey = get_option(CMTooltipGlossaryAmazonSupportAPI::AMAZON_CATEGORY_POST_KEY);
        }

        if( empty($searchKey) )
        {
            $searchKey = get_option(CMTooltipGlossaryAmazonSupportAPI::AMAZON_SEARCH_POST_KEY);
        }

        if( empty($widgetSize) )
        {
            $widgetSize = get_option(CMTooltipGlossaryAmazonSupportAPI::AMAZON_SIZE_POST_KEY, '120x150');
        }

        $affiliateCode = get_option(CMTooltipGlossaryAmazonSupportAPI::AMAZON_AFFILIATE_CODE_POST_KEY);

        if( !empty($affiliateCode) && !empty($searchKey) && !empty($categoryKey) && $widgetSize && is_string($widgetSize) && preg_match('/\d+x\d+/', $widgetSize) )
        {
            ob_start();
            require 'views/frontend/amazon_widget.phtml';
            $content = ob_get_contents();
            ob_end_clean();
        }

        return $content;
    }

    /**
     * Adds the Amazon widget to the tooltip content
     * @param type $glossaryItemContent
     * @param type $glossary_item
     * @return type
     */
    public static function addWidgetToTooltipContent($glossaryItemContent, $glossary_item)
    {
        $searchKey = array();
        if( self::amazon_enabled() && !get_post_meta($glossary_item->ID, self::AMAZON_DISABLED_META_KEY, true) )
        {
            $searchKey[] = get_option(CMTooltipGlossaryAmazonSupportAPI::AMAZON_SEARCH_POST_KEY);
            $termSearchKey = get_post_meta($glossary_item->ID, self::AMAZON_SEARCH_META_KEY, true);

            if( $termSearchKey )
            {
                $searchKey[] = $termSearchKey;
            }

            $searchKey[] = $glossary_item->post_title;

            $categoryKey = get_post_meta($glossary_item->ID, self::AMAZON_CATEGORY_META_KEY, true);
            $widgetSize = get_post_meta($glossary_item->ID, self::AMAZON_SIZE_META_KEY, true);

            $glossaryItemContent .= self::getAffiltiateWidget(implode(' ', array_filter($searchKey)), $categoryKey, $widgetSize);
        }

        return $glossaryItemContent;
    }

    /**
     * Saves the information form the metabox in the post's meta
     * @param type $post_id
     */
    public static function savePostdata($post_id)
    {
        $postType = isset($_POST['post_type']) ? $_POST['post_type'] : '';

        if( in_array($postType, self::getApplicablePostTypes()) )
        {
            $amazonCategory = ( isset($_POST[self::AMAZON_CATEGORY_POST_KEY])) ? $_POST[self::AMAZON_CATEGORY_POST_KEY] : '0';
            update_post_meta($post_id, self::AMAZON_CATEGORY_META_KEY, $amazonCategory);

            $amazonSize = ( isset($_POST[self::AMAZON_SIZE_POST_KEY])) ? $_POST[self::AMAZON_SIZE_POST_KEY] : '0';
            update_post_meta($post_id, self::AMAZON_SIZE_META_KEY, $amazonSize);
        }
    }

    /**
     * Saves the information form the metabox in the post's meta
     * @param type $post_id
     */
    public static function getParametersFromSize($widgetSize)
    {
        switch($widgetSize)
        {
            case "120x150":
                return array('p' => '6', 'st' => '1');
            case "120x240":
                return array('p' => '8', 'st' => '1');
            case "120x450":
                return array('p' => '10', 'st' => '1');
            case "120x600":
                return array('p' => '11', 'st' => '1');
            case "180x150":
                return array('p' => '9', 'st' => '1');
            case "300x250":
                return array('p' => '12', 'st' => '1');
            case "468x60":
                return array('p' => '13', 'st' => '1');
            case "160x600":
                return array('p' => '14', 'st' => '1');
            case "468x240":
                return array('p' => '15', 'st' => '1');
            case "468x336":
                return array('p' => '16', 'st' => '1');
            case "728x90":
                return array('p' => '48', 'st' => '1');
            case "200x200":
                return array('p' => '286', 'st' => '1');
        }
    }

    /**
     * Returns TRUE if the general setting is enabled
     * @return type
     */
    public static function amazon_enabled()
    {
        return $source_id = get_option(self::AMAZON_ENABLED_KEY, 1);
    }

    public static function amazon_show_in_tooltip()
    {
        return $source_id = get_option('cmttas_tooltip3RD_AmazonTooltip', 0);
    }

    public static function amazon_show_in_term()
    {
        return $source_id = get_option('cmttas_tooltip3RD_AmazonTerm', 0);
    }

}