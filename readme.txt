=== WOCRL Leagues and Personal Race History ===
Contributors: Aubergine262
Tags: wocrl
Requires at least: 4
Tested up to: 5.3.2
Stable tag: 1.2

Easily show WOCRL league and personal race data on your WordPress website using shortcodes. API Key required - contact hq@wocrl.org

== Description ==
= Easily show World Obstacle Course Racing League leagues and personal race data on your website =
Simply copy and paste one of the shortcodes into the content of a page to output the correlating league table straight from WOCRL

= Simple to install and get running =
* 5 different league outputs
* 4 personal race history outputs (for logged-in WordPress users who have an active WOCRL membership with the same email address)
* Disable default styles to add your own styles, if required

= WOCRL Leagues and Personal Race History is a free plugin for WordPress =
Our plugin is 100% GPL and available from the WordPress repository. API Key required - contact hq@wocrl.org

== Installation ==

= Download, Install and Activate! =
1. Go to Plugins > Add New to install WOCRL Leagues and Personal Race History, or
2. Download the latest version of the plugin.
3. Unzip the downloaded file to your computer.
4. Upload the /wocrl-api/ directory to the /wp-content/plugins/ directory of your site.
5. Activate the plugin through the 'Plugins' menu in WordPress.

= Complete the Initial Plugin Setup =
Go to Settings > WOCRL in the WordPress admin for a step-by-step initial setup, including:

1. API Key: Obtain your API Key from hq@wocrl.org, enter and Save
2. Styles & Scripts: Choose to turn off CSS and Javascript files, if you prefer to use your own

= Shortcodes available =
Simply copy and paste one of the following shortcodes into the content of a page to output the correlating league table straight from WOCRL:
* Trophy Hunters League = [trophy_hunters_league]
* Fun Runners League = [fun_runners_league]
* Community League = [community_league]
* Race Directors League = [race_directors_league]
* WOCRL Championship League = [wocrl_championship_league]
* Personal Trophy Hunters History = [personal_trophy_hunters_history]
* Personal Fun Runners History = [personal_fun_runners_history]
* Personal WOCRL Championship Data = [personal_wocrl_championship_data]
* Personal Fun Runners Data = [personal_fun_runners_data]

== Frequently Asked Questions ==

= No leagues are showing on my web page =
1) Make sure you have got and entered an active API Key (email hq@wocrl.org to get yours).
2) If your theme uses a pagebuilder (such as Divi Builder or WP Bakery), try switching to the classic editor instead

= The tables are working, but they look odd =
The tables have basic styling, you can add extra styling via your own website's stylesheet (speak to your web developer, they will know how to do it). To style the tables completely your own way, there is the option to turn off the default styling in the settings

= The table columns won't sort =
The plugin includes tablesorter.js, a common table sorting script, but your theme or another plugin probably has a clashing script. Try using the settings option to load scripts in the footer. There is a settings option to turn off the script completely in the settings,
if you're already using tablesorter.js in your website

= A user can't see their Personal Trophy Hunters History or Personal Fun Runners History =
The plugin cross references the user's WordPress login email and their WOCRL login email. If these don't match, no personal race history will be shown. Users should update their login email address at either WOCRL.org or on your website. An active WOCRL membership is required.

= I can't filter the league data =
Filters are available for league tables when the user is logged in to your website, and is an active WOCRL member. The plugin cross references the user's WordPress login email and their WOCRL login email. If these don't match, no filtering functionality is available. Users should update their login email address at either WOCRL.org or on your website. An active WOCRL membership is required.