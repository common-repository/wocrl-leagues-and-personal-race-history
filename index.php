<?php
/* 
Plugin Name: WOCRL Leagues and Personal Race History
Plugin URI: https://wocrl.org/
Description: Easily show WOCRL league and personal race data on your WordPress website using shortcodes. API Key required - contact hq@wocrl.org
Author: WOCRL
Version: 1.2
Author URI: https://wocrl.org/
*/  

include 'functions/functions-wocrl-api.php';
include 'vendor/autoload.php';
include 'classes/wocrl-api.class.php';

function wocrl_api_enqueue_script() {
    $wocrl_api_settings = get_option('wocrl_api_settings');

    $in_footer = true;
    $version = date('U');

    if(!$wocrl_api_settings['wocrl_exclude_tablesorter_js']) {
        wp_enqueue_script('jquery-tablesorter-js', plugin_dir_url(__FILE__) . 'js/jquery.tablesorter.min.js', array('jquery'), $version, $in_footer);
    }

    wp_enqueue_script( 'wocrl-api-js', plugin_dir_url( __FILE__ ) . 'js/wocrl-api.js', array('jquery'), $version, $in_footer);

    if(!$wocrl_api_settings['wocrl_exclude_css']) {
        wp_enqueue_style('wocrl-api-css', plugin_dir_url(__FILE__) . 'css/wocrl-api.css', array(), $version);
    }

    if(!$wocrl_api_settings['wocrl_exclude_fontawesome']) {
        wp_enqueue_style('wocrl-fontawesome-css', plugin_dir_url(__FILE__) . 'css/font-awesome.min.css', array(), $version);
    }
}
add_action('wp_enqueue_scripts', 'wocrl_api_enqueue_script');

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'wocrl_add_plugin_page_settings_link');
function wocrl_add_plugin_page_settings_link( $links ) {
    $links[] = '<a href="' .
        admin_url( 'options-general.php?page=wocrl-options' ) .
        '">' . __('Settings') . '</a>';
    return $links;
}

/* Register activation hook. */
register_activation_hook( __FILE__, 'wocrl_admin_notice_activation_hook' );

/**
 * Runs only when the plugin is activated.
 * @since 0.1.0
 */
function wocrl_admin_notice_activation_hook() {

    /* Create transient data */
    set_transient( 'wocrl-activation-admin-notice', true, 5 );
}


/* Add admin notice */
add_action( 'admin_notices', 'wocrl_activation_admin_notice' );


/**
 * Admin Notice on Activation.
 * @since 0.1.0
 */
function wocrl_activation_admin_notice(){

    /* Check transient, if available display notice */
    if( get_transient( 'wocrl-activation-admin-notice' ) ){
        ?>
        <div class="updated notice is-dismissible">
            <h4 style="margin-bottom: 0px;margin-top: .5em;">WOCRL Leagues and Personal Race History</h4>
            <p>Please enter your API key via the settings page <a href="<?php echo get_site_url(); ?>/wp-admin/options-general.php?page=wocrl-options">here</a></p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient( 'wocrl-activation-admin-notice' );
    }
}