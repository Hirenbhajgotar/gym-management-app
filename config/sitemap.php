<?php

use GuzzleHttp\RequestOptions;
use Spatie\Sitemap\Crawler\Profile;
use Spatie\Sitemap\SitemapGenerator;

return [
    /*
     * The default hostname to use
     */
    'default_url' => env('APP_URL', 'http://localhost'),

    /*
     * The route where the sitemap will be accessible from.
     * It is ignored if you use `SitemapGenerator::create()->writeToFile()`
     */
    'url' => 'sitemap.xml',

    /*
     * The generator to use.
     * Options: 'auto-crawl' or 'manual'
     */
    'generator' => 'auto-crawl',

    /*
     * The maximum number of crawled URLs.
     */
    'crawl_max_queue_size' => 100,

    /*
     * The maximum number of crawled pages.
     */
    'crawl_max_entries' => 10485760,

    /*
     * The query to use for eager loading relationships.
     */
    'crawl_eager_load' => [],

    /*
     * The maximum depth to crawl.
     */
    'crawl_max_depth' => PHP_INT_MAX,

    /*
     * If you need to change the configuration for a single job, you can do that here.
     *
     * https://docs.guzzlephp.org/en/stable/request-options.html
     */
    'guzzle' => [
        RequestOptions::TIMEOUT => 10,
        RequestOptions::CONNECT_TIMEOUT => 10,
    ],

    /*
     * The number of seconds the sitemap should be cached.
     */
    'cache_duration' => 0,

    /*
     * The profile that determines which URLs to crawl.
     */
    'crawl_profile' => Profile::class,
];
