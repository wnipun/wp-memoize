<?php

namespace wnipun\Memoize;

use WP_Query;

class Memoize
{

    private static $instance = null;

    private $args = [];

    private $withFields = [];

    private $withTaxonomies = [];

    /**
     * WordPress Query
     *
     * @param array $args
     * @return object
     */
    public static function query(array $args): object
    {
        self::$instance = new self;
        self::$instance->args = $args;

        return self::$instance;
    }

    /**
     * ACF Fields to eager load
     *
     * @param array $fields
     * @return self
     */
    public function withFields(array $fields): self
    {
        self::$instance->withFields = $fields;
        return self::$instance;
    }

    /**
     * Taxonomies to eager load
     *
     * @param array $taxonomies
     * @return self
     */
    public function withTaxonomies(array $taxonomies): self
    {
        self::$instance->withTaxonomies = $taxonomies;
        return self::$instance;
    }

    /**
     * Cache query and return WP_Query result
     *
     * @return object
     */
    public function cache(): object
    {
        return self::$instance->saveToFile(self::$instance->args);
    }

    /**
     * Clear cache
     *
     * @param string ...$postTypes
     * @return bool
     */
    public static function clear(string ...$postTypes): bool
    {

        $directory = str_replace("/wp", "", ABSPATH) . "app/uploads/memoize/";
        $directoryItems = scandir($directory);

        if (empty($postTypes)) {

            foreach ($directoryItems as $item) {
                if (strpos($item, '.json') !== false) {
                    unlink($directory . $item);
                }
            }

            return true;
        }

        foreach ($postTypes as $postType) {
            $files = array_filter($directoryItems, function($item) use ($postType) {
                if (strpos($item, $postType . '_') !== false) {
                    return $item;
                }
            });

            $file = array_values($files);

            if (!empty($file)) {
                unlink($directory . $file[0]);
            }
        }

        return true;
    }

    /**
     * Save WP_Query result to .json file if the query data
     * is not already cached. Otherwise, return cached data.
     *
     * @param array $args
     * @return Object
     */
    protected function saveToFile(array $args): Object
    {
        $key = crc32(json_encode($args));
        $signature = $args['post_type']? $args['post_type'] . '_' . $key : 'global_' . $key;
        $filePath = str_replace("/wp", "", ABSPATH) . "app/uploads/memoize/{$signature}.json";

        if (file_exists($filePath)) {
            $handle = fopen($filePath, 'r');
            $data = fread($handle, filesize($filePath));
            $json = json_decode($data, true);

            if ($json[$signature] ?? false) {
                return (Object) $json[$signature];
            }
        }

        $wpQuery = (new WP_Query($args));

        $json = json_encode([
            $signature => (object) [
                'posts' => $wpQuery->posts,
                'fields' => $this->getFields($wpQuery),
                'taxonomies' => $this->getTaxonomies($wpQuery)
            ]
        ]);

        return $this->createFile($signature, $filePath, $json);
    }

    /**
     * Return taxonomies for loaded posts
     *
     * @param WP_Query $wpQuery
     * @return array
     */
    protected function getTaxonomies(WP_Query $wpQuery): array
    {
        $taxonomies = [];

        if (!empty(self::$instance->withTaxonomies)) {
            foreach (self::$instance->withTaxonomies as $taxonomy) {

                if ($wpQuery->post_count > 0) {
                    $with = [];

                    foreach ($wpQuery->posts as $post) {
                        $with[$post->ID] = wp_get_post_terms($post->ID, $taxonomy);
                    }

                    $taxonomies[$taxonomy] = $with;
                }
            }
        }

        return $taxonomies;
    }

    /**
     * Return ACF fields for loaded posts
     *
     * @param WP_Query $wpQuery
     * @return array
     */
    protected function getFields(WP_Query $wpQuery): array
    {
        $fields = [];

        if (!empty(self::$instance->withFields)) {
            foreach (self::$instance->withFields as $field) {

                if ($wpQuery->post_count > 0) {
                    $with = [];

                    foreach ($wpQuery->posts as $post) {
                        $with[$post->ID] = get_field($field, $post->ID);
                    }

                    $fields[$field] = $with;
                }

            }
        }

        return $fields;
    }

    /**
     * Create file using a unique signature and given data
     *
     * @param string $signature
     * @param string $filePath
     * @param string $data
     * @param string $mode
     * @return Object
     */
    protected function createFile(string $signature, string $filePath, string $data, string $mode='w'): Object
    {
        $handle = fopen($filePath, $mode);
        fwrite($handle, $data);
        fclose($handle);

        return (object) json_decode($data, true)[$signature];
    }
}
