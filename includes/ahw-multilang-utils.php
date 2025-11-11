<?php
namespace AkkaMultilang;

use \Akka_headless_wp_resolvers as Resolvers;
use \Akka_headless_wp_content as Content;
use \Akka_headless_wp_utils as Utils;

class MultiLangUtils
{
  static $blog_taxonomy_terms = [];

  public static function multilang_post_types() {
    return apply_filters('ahw_multilang_post_types', ['post', 'page']);
  }

  public static function multilang_taxonomies() {
    return apply_filters('ahw_multilang_taxonomies', ['category', 'post_tag']);
  }

  public static function multilang_post_field_names() {
    return apply_filters('ahw_multilang_post_field_names', []);
  }

  public static function get_translation($post_id, $to_lang)
  {
    $url = null;
    if ($post_id) {
      $from_blog = $to_lang == 'en' ? 1 : 2;
      $to_blog = $to_lang == 'en' ? 2 : 1;
      switch_to_blog($from_blog);
      $language_posts = self::get_language_posts($post_id);
      if (Resolvers::resolve_field($language_posts, $to_blog)) {
        switch_to_blog($to_blog);
        $translation_post = Content::get_akka_post($language_posts[$to_blog]);
        if ($translation_post && $translation_post['post_status'] == 'publish') {
          return [
            'url' => $translation_post['permalink'],
          ];
        }
      }
    }
    if ($to_lang == 'en') {
      return [
        'url' => $url ?? '/en',
      ];
    }
    return [
      'url' => $url ?? '/',
    ];
  }

  public static function get_language_posts($post_id)
  {
    $current_blog_id = get_current_blog_id();
    $language_posts = [];
    $language_posts_ids = get_post_meta($post_id, '_akka_language_posts', true);
    if (!$language_posts_ids) {
      $language_posts_ids = [];
    }
    foreach (get_sites() as $blog) {
      if ($blog->blog_id != get_current_blog_id()) {
        $language_post = null;
        $language_post_id = Resolvers::resolve_field($language_posts_ids, $blog->blog_id);
        if ($language_post_id) {
          switch_to_blog($blog->blog_id);
          $language_posts[$blog->blog_id] = get_post($language_post_id);
          switch_to_blog($current_blog_id);
        }
      }
    }
    return $language_posts;
  }

  public static function get_language_term_ids($term_id)
  {
    $current_blog_id = get_current_blog_id();
    $language_terms = [];
    $language_term_ids = get_term_meta($term_id, '_akka_language_terms', true);
    if (!$language_term_ids) {
      $language_term_ids = [];
    }
    return $language_term_ids;
  }

  public static function post_translations($post_id, $post_type, $blog_list)
  {
    $language_posts = self::get_language_posts($post_id);
    $post_translations = '';
    foreach ($blog_list as $blog) {
      if ($blog->blog_id == get_current_blog_id()) {
        $post_translations .=
          '<div style="display:flex;align-items:center;gap:6px;font-size:0.85em;">' .
          self::blog_icon($blog) .
          ' ' .
          __('This page', 'akka-multilang') .
          '</div>';
      }
    }
    foreach ($blog_list as $blog) {
      $post_translations .= '<div>';
      if ($blog->blog_id != get_current_blog_id()) {
        $language_post = Resolvers::resolve_field($language_posts, $blog->blog_id);
        if ($language_post) {
          $post_translations .=
            '<a style="display:flex;align-items:center;gap:6px;font-size:0.85em;" href="' .
            $blog->path .
            'wp-admin/post.php?post=' .
            $language_post->ID .
            '&action=edit" title="' .
            $language_post->post_title .
            '">' .
            self::blog_icon($blog) .
            ' ' .
            $language_post->post_title .
            '</a>';
        } else {
          $post_translations .=
            '<a style="display:flex;align-items:center;gap:6px;font-size:0.85em" style="opacity:0.25" href="' .
            $blog->path .
            'wp-admin/post-new.php?post_type=' .
            $post_type .
            '&akka_translate_blog=' .
            get_current_blog_id() .
            '&akka_translate_post=' .
            $post_id .
            '">' .
            self::blog_icon($blog) .
            ' ' .
            __('Create translation', 'akka-multilang') .
            '</a>';
        }
      }
      $post_translations .= '</div>';
    }
    return $post_translations;
  }

  public static function term_translations($term_id, $taxonomy, $blog_list)
  {
    $language_term_ids = self::get_language_term_ids($term_id);
    $term_translations = '';
    $taxonomy_terms = [];
    $current_blog_id = get_current_blog_id();
    foreach ($blog_list as $blog) {
      if ($blog->blog_id == $current_blog_id) {
        $term_translations .=
          '<div style="display:flex;align-items:center;gap:6px;font-size:0.85em;">' .
          self::blog_icon($blog) .
          ' ' .
          __('This term', 'akka-multilang') .
          '</div>';
      }
    }
    foreach ($blog_list as $blog) {
      if ($blog->blog_id != get_current_blog_id()) {
        $language_term_id = $term_id == 1 ? 1 : Resolvers::resolve_field($language_term_ids, $blog->blog_id);
        // Check that saved term translation still exists
        // TODO: FIX THIS FOR TRANSLATED TERMS!
        $taxonomy_terms = self::get_blog_taxonomy_term_ids($blog->blog_id, $taxonomy);
        if (!in_array($language_term_id, $taxonomy_terms)) {
          $language_term_id = null;
        }
        if ($language_term_id) {
          $term_translations .=
            '<a style="display:flex;align-items:center;gap:6px;font-size:0.85em;" href="' .
            $blog->path .
            'wp-admin/term.php?taxonomy=' .
            $taxonomy .
            '&tag_ID=' .
            $language_term_id .
            '&post_type=' .
            resolvers::resolve_field($_GET, 'post_type') .
            '&action=edit" title="' .
            __('Go to translation', 'akka-multilang') .
            '">' .
            self::blog_icon($blog) .
            ' ' .
            __('Go to translation', 'akka-multilang') .
            '</a>';
        } else {
          $term_translations .=
            '<a style="display:flex;align-items:center;gap:6px;font-size:0.85em;" href="' .
            $blog->path .
            'wp-admin/edit-tags.php?taxonomy=' .
            $taxonomy .
            '&akka_translate_taxonomy=' .
            $taxonomy .
            '&akka_translate_blog=' .
            get_current_blog_id() .
            '&akka_translate_term=' .
            $term_id .
            '&post_type=' .
            resolvers::resolve_field($_GET, 'post_type') .
            '">' .
            self::blog_icon($blog) .
            ' ' .
            __('Create translation', 'akka-multilang') .
            '</a>';
        }
      }
    }

    return $term_translations;
  }

  public static function get_blog_taxonomy_term_ids($blog_id, $taxonomy)
  {
    if (!isset(self::$blog_taxonomy_terms[$blog_id])) {
      self::$blog_taxonomy_terms[$blog_id] = [];
    }
    if (!isset(self::$blog_taxonomy_terms[$blog_id][$taxonomy])) {
      $current_blog_id = get_current_blog_id();
      switch_to_blog($blog_id);
      self::$blog_taxonomy_terms[$blog_id][$taxonomy] = get_terms([
        'taxonomy' => $taxonomy,
        'fields' => 'ids',
        'hide_empty' => false,
      ]);
      switch_to_blog($current_blog_id);
    }
    return self::$blog_taxonomy_terms[$blog_id][$taxonomy];
  }

  public static function blog_icon($blog)
  {
    $path = $blog->path;
    $path = $path ? str_replace('/', '', $path) : '';
    if ($path == 'en') {
      return '<svg style="flex:none;" width="20px" height="20px" viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="iconify iconify--twemoji" preserveAspectRatio="xMidYMid meet"><path fill="#00247D" d="M0 9.059V13h5.628zM4.664 31H13v-5.837zM23 25.164V31h8.335zM0 23v3.941L5.63 23zM31.337 5H23v5.837zM36 26.942V23h-5.631zM36 13V9.059L30.371 13zM13 5H4.664L13 10.837z"></path><path fill="#CF1B2B" d="M25.14 23l9.712 6.801a3.977 3.977 0 0 0 .99-1.749L28.627 23H25.14zM13 23h-2.141l-9.711 6.8c.521.53 1.189.909 1.938 1.085L13 23.943V23zm10-10h2.141l9.711-6.8a3.988 3.988 0 0 0-1.937-1.085L23 12.057V13zm-12.141 0L1.148 6.2a3.994 3.994 0 0 0-.991 1.749L7.372 13h3.487z"></path><path fill="#EEE" d="M36 21H21v10h2v-5.836L31.335 31H32a3.99 3.99 0 0 0 2.852-1.199L25.14 23h3.487l7.215 5.052c.093-.337.158-.686.158-1.052v-.058L30.369 23H36v-2zM0 21v2h5.63L0 26.941V27c0 1.091.439 2.078 1.148 2.8l9.711-6.8H13v.943l-9.914 6.941c.294.07.598.116.914.116h.664L13 25.163V31h2V21H0zM36 9a3.983 3.983 0 0 0-1.148-2.8L25.141 13H23v-.943l9.915-6.942A4.001 4.001 0 0 0 32 5h-.663L23 10.837V5h-2v10h15v-2h-5.629L36 9.059V9zM13 5v5.837L4.664 5H4a3.985 3.985 0 0 0-2.852 1.2l9.711 6.8H7.372L.157 7.949A3.968 3.968 0 0 0 0 9v.059L5.628 13H0v2h15V5h-2z"></path><path fill="#CF1B2B" d="M21 15V5h-6v10H0v6h15v10h6V21h15v-6z"></path></svg>';
    }
    return '<svg width="20px" height="20px" viewBox="0 -4 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_503_4527)">
<rect width="28" height="20" rx="2" fill="white"/>
<mask id="mask0_503_4527" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="28" height="20">
<rect width="28" height="20" rx="2" fill="white"/>
</mask>
<g mask="url(#mask0_503_4527)">
<rect width="28" height="20" fill="#157CBB"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 12H8V20H12V12H28V8H12V0H8V8H0V12Z" fill="#FFD34D"/>
</g>
</g>
<defs>
<clipPath id="clip0_503_4527">
<rect width="28" height="20" rx="2" fill="white"/>
</clipPath>
</defs>
</svg>';
}
