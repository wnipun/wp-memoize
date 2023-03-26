Simple disk based caching library for WordPress builds that use roots.io Bedrock.

# Installation

Memoize require >= 8.1

```bash
composer require wnipun/wp-memoize
```
# Usage

WP_Query results can be cached using the following code.

```php
use wnipun\Memoize\Memoize;

$bulletin = Memoize::query([
    'post_type' => 'bulletin',
    'posts_per_page' => 3
  ])
  ->withFields([
    'bulletin_agency',
    'bulletin_thumbnail'
  ])
  ->withTaxonomies([
    'bulletin_category'
  ])
  ->cache();
```
- **query** - Accepts WP_Query arguments
- **withFields** - Eager load ACF fields
- **withTaxonomies** - Eager load taxonomies

# Clear cache

There are two methods available to clear cache.

```php
  // Clear everything
  Memoize::clear();

  // Clear provided post types
  Memoize::clear('bulletin', 'news');
```
