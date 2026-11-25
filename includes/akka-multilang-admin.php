<?php
namespace Akka;

class MultiLangAdmin
{
  public static function hooks()
  {
    if (!is_admin()) {
      return;
    }

    $blog_list = get_sites();

    add_action('admin_init', function () use ($blog_list) {
      if (Utils::get_query_param('akka_translate_blog') && Utils::get_query_param('akka_translate_post')) {
        if (!current_user_can('edit_posts')) {
          return;
        }

        $new_post_id = self::translate_post(
          Utils::get_query_param('akka_translate_blog'),
          Utils::get_query_param('akka_translate_post')
        );
        if ($new_post_id) {
          wp_redirect(admin_url('post.php?post=' . $new_post_id . '&action=edit'));
          exit();
        }
      }
      if (
        Utils::get_query_param('akka_translate_blog') &&
        Utils::get_query_param('akka_translate_taxonomy') &&
        Utils::get_query_param('akka_translate_term')
      ) {
        if (!current_user_can('edit_posts')) {
          return;
        }

        $new_term_id = self::translate_term(
          Utils::get_query_param('akka_translate_blog'),
          Utils::get_query_param('akka_translate_taxonomy'),
          Utils::get_query_param('akka_translate_term')
        );
        if ($new_term_id) {
          wp_redirect(
            admin_url(
              'term.php?taxonomy=' .
                Utils::get_query_param('akka_translate_taxonomy') .
                '&tag_ID=' .
                $new_term_id .
                '&post_type=' .
                Resolvers::resolve_field($_GET, 'post_type')
            )
          );
          exit();
        }
      }

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
                MultiLangUtils::post_translations($post->ID, $post_type, $blog_list) .
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
            echo MultiLangUtils::post_translations($post_id, $post_type, $blog_list);
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
            echo MultiLangUtils::term_translations($term_id, $taxonomy, $blog_list);
          },
          10,
          3
        );
      }
    });
  }

  private static function translate_post($from_blog, $from_post_id)
  {
    $current_blog_id = get_current_blog_id();
    switch_to_blog($from_blog);
    // Check for existing translation
    $existing_translations = MultiLangUtils::get_language_posts($from_post_id);
    if (Resolvers::resolve_field($existing_translations, $current_blog_id)) {
      switch_to_blog($current_blog_id);
      return $existing_translations[$current_blog_id]['post_id'];
    }
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
    if (!MultiLangUtils::is_media_library_synced()) {
      // TEMP: Remove images in image blocks
      $new_post['post_content'] = self::remove_images_from_blocks($new_post['post_content']);
      // TODO: Copy images in blocks (section-block, cover-block, image-slider-block, core/image)
      // TODO: Copy media in block (audio-player-block)
      // TODO: Copy videos and image in acf
    }
    $thumnail_image_id = get_post_thumbnail_id($p->ID);
    $fields = get_fields($from_post_id);
    $terms = wp_get_post_terms($p->ID, MultiLangUtils::multilang_taxonomies());
    $terms = !is_wp_error($terms) ? array_reduce(
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
              : Resolvers::resolve_field(MultiLangUtils::get_language_term_ids($term->term_id), $current_blog_id),
        ];
        return $taxonomy_terms;
      },
      []
    ) : [];
    switch_to_blog($current_blog_id);
    // TODO: translate featured images when media library is not synced
    if ($thumnail_image_id && MultiLangUtils::is_media_library_synced()) {
      $new_post['meta_input'] = array_merge($new_post['meta_input'], [
        '_thumbnail_id' => $thumnail_image_id,
      ]);
    }
    if (!empty($terms)) {
      $new_post['tax_input'] = [];
      foreach ($terms as $taxonomy => $terms) {
        $taxonomy_terms = MultiLangUtils::get_blog_taxonomy_term_ids($current_blog_id, $taxonomy);
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
        if (in_array($field_name, MultiLangUtils::multilang_post_field_names()) && !empty($field_value)) {
          if (is_numeric($field_value)) {
            $field_value = self::translate_post($from_blog, $field_value);
          } else if (!empty($field_value) && isset($field_value[0]) && is_numeric($field_value[0])) {
            $field_value = array_map(function ($post_id) use ($from_blog) {
              return self::translate_post($from_blog, $post_id);
            }, $field_value);
          }
        } else if(!empty($field_value) && isset($field_value[0]) && is_array($field_value[0])) {
          // Check for translatable fields in repeater
          $translatable_sub_fields = array_filter(array_keys($field_value[0]), function($sub_field) {
            return in_array($sub_field, MultiLangUtils::multilang_post_field_names());
          });
          if (!empty($translatable_sub_fields)) {
            $field_value = array_map(function($repeater_row) use($from_blog, $translatable_sub_fields) {
              $new_field_value = [];
              foreach($repeater_row as $sub_field_name => $sub_field_value) {
                if (in_array($sub_field_name, MultiLangUtils::multilang_post_field_names()) && $sub_field_value && is_numeric($sub_field_value)) {
                  $new_field_value[$sub_field_name] = self::translate_post($from_blog, $sub_field_value);
                } else {
                  $new_field_value[$sub_field_name] = $sub_field_value;
                }
              }
              return $new_field_value;
            }, $field_value);
          }
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

  private static function remove_images_from_blocks($post_content) {
    $block_key = '<!-- wp:image ';
    if (strpos($post_content, $block_key) === false) {
      return $post_content;
    }
    $new_post_content = '';

    foreach(explode($block_key, $post_content) as $i => $part) {
      if ($i == 0) {
        $new_post_content .= $part;
      } else if(str_starts_with($part, '-->')) {
        $new_post_content .= $block_key . $part;
      } else {
        $new_post_content .= $block_key . '-->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->';
      }
    }

    return $new_post_content;
  }
}

MultiLangAdmin::hooks();
