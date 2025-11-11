<?php
namespace AkkaMultilang;

use \Akka_headless_wp_resolvers as Resolvers;
use \Akka_headless_wp_content as Content;
use \Akka_headless_wp_utils as Utils;

class MultiLangAdmin
{
  public static function hooks()
  {
    if (!is_admin()) {
      return;
    }

    $blog_list = get_sites();

    add_action('admin_init', function () {
      if (Utils::getQueryParam('akka_translate_blog') && Utils::getQueryParam('akka_translate_post')) {
        if (!current_user_can('edit_posts')) {
          return;
        }

        $new_post_id = self::translate_post(
          Utils::getQueryParam('akka_translate_blog'),
          Utils::getQueryParam('akka_translate_post')
        );
        if ($new_post_id) {
          wp_redirect(admin_url('post.php?post=' . $new_post_id . '&action=edit'));
          exit();
        }
      }
      if (
        Utils::getQueryParam('akka_translate_blog') &&
        Utils::getQueryParam('akka_translate_taxonomy') &&
        Utils::getQueryParam('akka_translate_term')
      ) {
        if (!current_user_can('edit_posts')) {
          return;
        }

        $new_term_id = self::translate_term(
          Utils::getQueryParam('akka_translate_blog'),
          Utils::getQueryParam('akka_translate_taxonomy'),
          Utils::getQueryParam('akka_translate_term')
        );
        if ($new_term_id) {
          wp_redirect(
            admin_url(
              'term.php?taxonomy=' .
                Utils::getQueryParam('akka_translate_taxonomy') .
                '&tag_ID=' .
                $new_term_id .
                '&post_type=' .
                resolvers::resolve_field($_GET, 'post_type')
            )
          );
          exit();
        }
      }
    });

    foreach (MultiLangUtils::multilang_post_types() as $post_type) {
      add_action('add_meta_boxes', function () use ($post_type, $blog_list) {
        add_meta_box(
          'akka_language',
          __('Translations', 'akka-multilang'),
          function () use ($post_type, $blog_list) {
            global $post;
            if (!$post) {
              return;
            }
            echo '<div class="components-panel__row akka-ai-row">
  <div class="components-base-control">
    <div class="components-base-control__field">
      ' .
              self::post_translations($post->ID, $post_type, $blog_list) .
              '
    </div>
  </div>
</div>';
          },
          $post_type,
          'side',
          'high'
        );
      });

      add_filter('manage_' . $post_type . '_posts_columns', function ($defaults) {
        $defaults['akka_languages'] = __('Translations', 'akka-multilang');
        return $defaults;
      });

      add_action(
        'manage_' . $post_type . '_posts_custom_column',
        function ($column_name, $post_id) use ($post_type, $blog_list) {
          if ($column_name != 'akka_languages') {
            return;
          }
          echo self::post_translations($post_id, $post_type, $blog_list);
        },
        10,
        2
      );
    }

    foreach (MultiLangUtils::multilang_taxonomies() as $taxonomy) {
      add_filter('manage_edit-' . $taxonomy . '_columns', function ($defaults) {
        $defaults['akka_languages'] = __('Translations', 'akka-multilang');
        return $defaults;
      });

      add_action(
        'manage_' . $taxonomy . '_custom_column',
        function ($content, $column_name, $term_id) use ($taxonomy, $blog_list) {
          if ($column_name != 'akka_languages') {
            return;
          }
          echo self::term_translations($term_id, $taxonomy, $blog_list);
        },
        10,
        3
      );
    }
  }

  private static function translate_post($from_blog, $from_post_id)
  {
    $current_blog_id = get_current_blog_id();
    switch_to_blog($from_blog);
    // Check for existing translation
    $existing_translations = self::get_language_posts($from_post_id);
    if (Resolvers::resolve_field($existing_translations, $current_blog_id)) {
      switch_to_blog($current_blog_id);
      return $existing_translations[$current_blog_id]->ID;
    }
    // TODO: Copy images in blocks (section-block, cover-block, image-slider-block, core/image)
    // TODO: Copy media in block (audio-player-block)
    // TODO: Copy videos and image in acf
    $p = get_post($from_post_id);
    $new_post = [
      'post_title' => $p->post_title,
      'post_content' => $p->post_content,
      'post_status' => 'draft',
      'post_excerpt' => $p->post_excerpt,
      'post_author' => get_current_user_id(),
      'post_type' => $p->post_type,
      'meta_input' => [
        '_akka_language_posts' => [
          $from_blog => $from_post_id,
        ],
      ],
    ];
    $medias = [];
    $thumnail_image_id = get_post_thumbnail_id($p->ID);
    if ($thumnail_image_id) {
      // $medias[] = [
      //   'post' => get_post($thumnail_image_id),
      //   'meta' => [
      //     '_wp_attached_file' => get_post_meta($thumnail_image_id, '_wp_attached_file', true),
      //     '_wp_attachment_metadata' => get_post_meta($thumnail_image_id, '_wp_attachment_metadata', true),
      //   ],
      // ];
    }
    $terms = wp_get_post_terms($p->ID, MultiLangUtils::multilang_taxonomies());
    $terms = array_reduce(
      $terms,
      function ($taxonomy_terms, $term) use ($current_blog_id) {
        if (!isset($taxonomy_terms[$term->taxonomy])) {
          $taxonomy_terms[$term->taxonomy] = [];
        }
        $taxonomy_terms[$term->taxonomy][] = [
          'old_term_id' => $term->term_id,
          'new_term_id' =>
            $term->term_id == 1
              ? 1
              : Resolvers::resolve_field(self::get_language_term_ids($term->term_id), $current_blog_id),
        ];
        return $taxonomy_terms;
      },
      []
    );
    $fields = get_fields($from_post_id);
    switch_to_blog($current_blog_id);
    // NOTE: This is not needed when media libraries are synced between langs
    // $new_media = [];
    // foreach ($medias as $media) {
    //   $new_media[$media['post']->ID] = self::get_and_ensure_media_id($media);
    // }
    if ($thumnail_image_id) {
      $new_post['meta_input'] = array_merge($new_post['meta_input'], [
        '_thumbnail_id' => $thumnail_image_id,
      ]);
      // NOTE: This is not needed when media libraries are synced between langs
      // $new_post['meta_input'] = array_merge($new_post['meta_input'], [
      //   '_thumbnail_id' => $new_media[$thumnail_image_id],
      // ]);
    }
    if (!empty($terms)) {
      $new_post['tax_input'] = [];
      foreach ($terms as $taxonomy => $terms) {
        $taxonomy_terms = self::get_blog_taxonomy_term_ids($current_blog_id, $taxonomy);
        $new_post['tax_input'][$taxonomy] = array_map(function ($term_data) use (
          $from_blog,
          $taxonomy,
          $taxonomy_terms
        ) {
          // Check that saved term translation still exists
          if ($term_data['new_term_id'] && in_array((int) $term_data['new_term_id'], $taxonomy_terms)) {
            return $term_data['new_term_id'];
          }
          return self::translate_term($from_blog, $taxonomy, $term_data['old_term_id']);
        }, $terms);
      }
    }
    $new_post_id = wp_insert_post($new_post);

    switch_to_blog($from_blog);
    update_post_meta($from_post_id, '_akka_language_posts', [
      $current_blog_id => $new_post_id,
    ]);
    switch_to_blog($current_blog_id);

    if (!empty($fields)) {
      foreach ($fields as $field_name => $field_value) {
        if (in_array($field_name, [MultiLangUtils::multilang_post_field_names()]) && !empty($field_value)) {
          $field_value = array_map(function ($post_id) use ($from_blog) {
            return self::translate_post($from_blog, $post_id);
          }, $field_value);
        }
        update_field($field_name, $field_value, $new_post_id);
      }
    }

    return $new_post_id;
  }

  private static function translate_term($from_blog, $from_taxonomy, $from_term_id)
  {
    $current_blog_id = get_current_blog_id();
    switch_to_blog($from_blog);
    $t = get_term($from_term_id);
    switch_to_blog($current_blog_id);
    $new_term = wp_insert_term($t->name, $from_taxonomy, [
      'description' => $t->description,
      'slug' => $t->slug,
    ]);
    if (is_wp_error($new_term)) {
      $existing_term = get_term_by('name', $t->name, $from_taxonomy);
      $new_term = [
        'term_id' => $existing_term->term_id,
      ];
    }
    switch_to_blog($from_blog);
    update_term_meta($from_term_id, '_akka_language_terms', [
      $current_blog_id => $new_term['term_id'],
    ]);
    switch_to_blog($current_blog_id);
    update_term_meta($new_term['term_id'], '_akka_language_terms', [
      $from_blog => $from_term_id,
    ]);
    return $new_term['term_id'];
  }
}

MultiLangAdmin::hooks();
