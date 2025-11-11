# Akka Headless WP Multilang

This plugin enables multilang when running Akka headless Wordpress.

## Installation

Add this repository to you composer.json:

```

  "repositories": [
    ...
    {
      "type": "vcs",
      "url": "https://github.com/Aventyret/akka-headless-wp-multilang.git"
    }
```

Install the plugin:

```
composer require aventyret/akka-headless-wp-multilang
```

Activate the plugin in your wp admin.

## Documentation

Configure what post types you want to have translatable:

```
add_filter('ahw_multilang_post_types', function($post_types) {
  return [
    'post',
    'page',
    'product'
  ];
});
```

Configure what taxonomies you want to have translatable:

```
add_filter('ahw_multilang_taxonomies', function($post_types) {
  return [
    'category',
    'post_tag',
    'country'
  ];
});
```

Configure what ACF field names that are posts that you want to have translatable:

```
add_filter('ahw_multilang_post_field_names', function($field_names) {
  return [
    'related_post'
  ];
});
```
