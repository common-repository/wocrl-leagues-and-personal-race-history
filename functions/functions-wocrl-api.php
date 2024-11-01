<?php
/**
 * Created by PhpStorm.
 * User: Steph
 * Date: 06/01/2020
 * Time: 15:51
 */

add_action( 'admin_menu', 'wocrl_plugin_page' );
function wocrl_plugin_page() {
    add_options_page(
        'WOCRL Options',
        'WOCRL',
        'manage_options',
        'wocrl-options',
        'wocrl_plugin_page_callback'
    );
}

function wocrl_plugin_page_callback(){
    $wocrl_api_settings = get_option('wocrl_api_settings');
    ?>
    <style>
        table.form-table .form-radio {
            margin-left: 5px;
        }
    </style>
    <div class="wrap" id="aubergine-options">
        <h1>WOCRL Leagues and Personal Race History</h1>
        <table class="form-table">
            <tbody>
            <form id="site-logo-uploader" method="post" action="">
                <tr>
                    <th>Instructions</th>
                    <td>
                        <p>
                            1) Obtain your API key and enter it in the settings below
                        </p>
                        <p>
                            2) Simply copy and paste one of the following shortcodes into the content of a page to output the correlating league table straight from WOCRL:
                            <ul>
                            <li>- Trophy Hunters League = <strong>[trophy_hunters_league]</strong></li>
                            <li>- Fun Runners League = <strong>[fun_runners_league]</strong></li>
                            <li>- Community League = <strong>[community_league]</strong></li>
                            <li>- Race Directors League = <strong>[race_directors_league]</strong></li>
                            <li>- WOCRL Championship League = <strong>[wocrl_championship_league]</strong></li>
                            <li>- Personal Trophy Hunters History = <strong>[personal_trophy_hunters_history]</strong></li>
                            <li>- Personal Fun Runners History = <strong>[personal_fun_runners_history]</strong></li>
                            <li>- Personal Fun Runners Data = <strong>[personal_fun_runners_data]</strong></li>
                            <li>- Personal WOCRL Championship Data = <strong>[personal_wocrl_championship_data]</strong></li>
                            </ul>
                            If the current logged-in user's email matches the email of their active WOCRL membership, they will be able to filter the data on the tables and see their own personal race history.
                        </p>
                        <br />
                        Data from the API is also accessible in JSON format: <a href="https://wocrl.org/api-reference/">API Reference</a>
                    </td>
                </tr>
                <tr>
                    <th>API Key</th>
                    <td>
                        <input type="text" name="wocrl_api_key" value="<?php echo (isset($wocrl_api_settings['wocrl_api_key'])) ? $wocrl_api_settings['wocrl_api_key'] : ''; ?>"><br />
                        <small>Obtain your API key from <a href="mailto:hq@wocrl.org">hq@wocrl.org</a> if you haven't already got one.</small>
                    </td>
                </tr>
                <tr>
                    <th>CSS</th>
                    <td>
                        <p>
                            <input type="checkbox" name="wocrl_exclude_css" value="1" <?php echo (isset($wocrl_api_settings['wocrl_exclude_css']) && $wocrl_api_settings['wocrl_exclude_css'] == 1) ? 'checked' : ''; ?>>
                            <label for="wocrl_exclude_css">Turn off basic styling (e.g. style the tables using the site’s main stylesheet)</label>
                        </p>
                        <p>
                            <input type="checkbox" name="wocrl_exclude_fontawesome" value="1" <?php echo (isset($wocrl_api_settings['wocrl_exclude_fontawesome']) && $wocrl_api_settings['wocrl_exclude_fontawesome'] == 1) ? 'checked' : ''; ?>>
                            <label for="wocrl_exclude_fontawesome">Turn off font awesome (e.g. if it’s already included in the site)</label>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>JS</th>
                    <td>
                        <p>
                            <input type="checkbox" name="wocrl_exclude_tablesorter_js" value="1" <?php echo (isset($wocrl_api_settings['wocrl_exclude_tablesorter_js']) && $wocrl_api_settings['wocrl_exclude_tablesorter_js'] == 1) ? 'checked' : ''; ?>>
                            <label for="wocrl_exclude_tablesorter_js">Turn off 'Table Sorter' script (e.g. if it’s already included in the site)</label>
                        </p>
                        <p>
                            <input type="checkbox" name="wocrl_script_footer" value="1" <?php echo (isset($wocrl_api_settings['wocrl_script_footer']) && $wocrl_api_settings['wocrl_script_footer'] == 1) ? 'checked' : ''; ?>>
                            <label for="wocrl_script_footer">Include all scripts in footer (scripts depends on main jQuery library being loaded first, some theme setups move the location of this script to the footer)</label>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                    </th>
                    <td>
                        <input type="submit" name="submit" value="Save" class="button button-primary" />
                        <input type="hidden" name="save_wocrl_settings" value="save_wocrl_settings" />
                    </td>
                </tr>
            </form>
            </tbody>
        </table>
        <table class="form-table">
            <tr>
                <th>FAQS</th>
                <td>
                    <p>
                        <strong>FAQ - No leagues are showing on my web page</strong><br />
                        1) Make sure you have got and entered an active API Key (email <a href="mailto:@hq@wocrl.org">hq@wocrl.org</a> to get yours).<br />
                        2) If your theme uses a pagebuilder (such as Divi Builder or WP Bakery), try switching to the classic editor instead<br />
                        <br />
                    </p>
                    <p>
                        <strong>FAQ - The tables are working, but they look odd</strong><br />
                        The tables have basic styling, you can add extra styling via your own website's stylesheet (speak to your web developer,<br />
                        they will know how to do it). To style the tables completely your own way, there is the option to turn off the default styling in the settings<br />
                        <br />
                    </p>
                    <p>
                        <strong>FAQ - The table columns won't sort</strong><br />
                        The plugin includes tablesorter.js, a common table sorting script, but your theme or another plugin probably has a clashing script.<br />
                        Try using the settings option to load scripts in the footer. There is a settings option to turn off the script completely in the settings,<br />
                        if you're already using tablesorter.js in your website
                    </p>
                </td>
            </tr>
        </table>
    </div>
    <?php

    save_wocrl_api_settings();
}

add_option( 'wocrl_api_settings', '', '', 'yes' );
function save_wocrl_api_settings(){
    if(isset($_POST['save_wocrl_settings']) && $_POST['save_wocrl_settings'] == 'save_wocrl_settings'){

       if(isset($_POST['wocrl_api_key'])){
           $wocrl_api_key = sanitize_text_field($_POST['wocrl_api_key']);
       }

        if(isset($_POST['wocrl_exclude_css'])){
            $wocrl_exclude_css = ($_POST['wocrl_exclude_css'] == true) ? true : false;
        }

        if(isset($_POST['wocrl_exclude_tablesorter_js'])){
            $wocrl_exclude_tablesorter_js = ($_POST['wocrl_exclude_tablesorter_js'] == true) ? true : false;
        }


        if(isset($_POST['wocrl_exclude_fontawesome'])){
            $wocrl_exclude_fontawesome = ($_POST['wocrl_exclude_fontawesome'] == true) ? true : false;
        }

        $options = array(
            'wocrl_api_key' => $wocrl_api_key,
            'wocrl_exclude_css' => $wocrl_exclude_css,
            'wocrl_exclude_tablesorter_js' => $wocrl_exclude_tablesorter_js,
            'wocrl_exclude_fontawesome' => $wocrl_exclude_fontawesome
        );
        update_option( 'wocrl_api_settings', $options );
        //header('Location: '.$_SERVER['REQUEST_URI']);
        echo '<script type="text/javascript">location.reload(true);</script>';
        //header('location:'.currentUrl().'&sent=true');
    }
}