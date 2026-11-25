<?php
namespace Akka;

add_action('rest_api_init', function () {
  register_rest_route(AKKA_API_BASE, '/multilang/translation', [
    'methods' => 'GET',
    'callback' => function () {
      $to_lang = Utils::get_query_param('lang', 'sv');
      $post_id = Utils::get_query_param('post_id', null);
      return MultiLangUtils::get_translation($post_id, $to_lang);
    },
    'permission_callback' => 'Akka\Router::require_proxy_header',
  ]);
});

add_filter('akka_post_single', function ($post_data) {
  if (in_array(Resolvers::resolve_field($post_data, 'post_type'), MultiLangUtils::multilang_post_types())) {
    $post_data['translations'] = [];
    foreach(MultiLangUtils::get_language_posts($post_data['post_id']) as $translation) {
      $post_data['translations'][] = $translation;
    }
  }
  return $post_data;
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
