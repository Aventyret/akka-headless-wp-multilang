<?php
/*
Plugin Name: Akka Headless WP – Multilang
Plugin URI: https://github.com/aventyret/akka-wp/blob/main/plugins/akka-headless-wp-multilang
Description: Multilang plugin for Akka
Author: Mediakooperativet, Äventyret
Author URI: https://aventyret.com
Version: 3.0.0
*/

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)){
    die('Invalid URL');
}

if (defined('AKKA_HEADLESS_WP_MULTILANG'))
{
    die('Invalid plugin access');
}

define('AKKA_HEADLESS_WP_MULTILANG',  __FILE__ );
define('AKKA_HEADLESS_WP_MULTILANG_DIR', plugin_dir_path( __FILE__ ));
define('AKKA_HEADLESS_WP_MULTILANG_URI', plugin_dir_url( __FILE__ ));
define('AKKA_HEADLESS_WP_MULTILANG_VER', "3.0.0");

require_once(AKKA_HEADLESS_WP_MULTILANG_DIR . 'includes/akka-multilang-utils.php');
require_once(AKKA_HEADLESS_WP_MULTILANG_DIR . 'includes/akka-multilang-admin.php');
require_once(AKKA_HEADLESS_WP_MULTILANG_DIR . 'public/akka-multilang-hooks.php');
