<?php
namespace AkkaMultilang;

use \Akka_headless_wp_resolvers as Resolvers;
use \Akka_headless_wp_content as Content;
use \Akka_headless_wp_utils as Utils;

add_action('rest_api_init', function () {
  register_rest_route(AKKA_API_BASE, '/multilang/translation', [
    'methods' => 'GET',
    'callback' => function () {
      $to_lang = Utils::getQueryParam('to_lang', 'sv');
      $post_id = Utils::getQueryParam('post_id', null);
      return MultiLangUtils::get_translation($post_id, $to_lang);
    },
    'permission_callback' => 'Akka_headless_wp_content::can_get_content',
  ]);
});

add_filter('network-media-library/post_types', function ($post_types) {
  return array_merge($post_types, MultiLangUtils::multilang_post_types());
});

add_filter('network-media-library/site_id', function ($site_id) {
  return 1;
});

add_action('init', function () {
  /**
   * Add translations text domain
   */
  load_plugin_textdomain('akka-multilang', false, WP_LANG_DIR . '/plugins');
});
