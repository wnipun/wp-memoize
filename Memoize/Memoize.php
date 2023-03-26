<?php

namespace wnipun\Memoize;

use WP_Query;

class Memoize
{

    private static $instance = null;

    private $args = [];

    private $with = [];

    public static function args(array $args): object
    {
        self::$instance = new self;
        self::$instance->args = $args;

        return self::$instance;
    }

    public function with(array $with): self
    {
        self::$instance->with = $with;
        return self::$instance;
    }

    public function cache(): object
    {
        return self::$instance->saveToFile(self::$instance->args);
    }

    protected function saveToFile($args): Object
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

        $acfFieldData = [];
        if (!empty(self::$instance->with)) {
            foreach (self::$instance->with as $field) {

                if ($wpQuery->post_count > 0) {
                    $with = [];

                    foreach ($wpQuery->posts as $post) {
                        $with[$post->ID] = get_field($field, $post->ID);
                    }

                    $acfFieldData[$field] = $with;
                }

            }
        }

        $json = json_encode([
            $signature => (object) [
                'posts' => $wpQuery->posts,
                'fields' => $acfFieldData,
            ]
        ]);

        return $this->createFile($signature, $filePath, $json);
    }

    protected function createFile(string $signature, string $filePath, string $data, string $mode='w'): Object
    {
        $handle = fopen($filePath, $mode);
        fwrite($handle, $data);
        fclose($handle);

        return (object) json_decode($data, true)[$signature];
    }
}
