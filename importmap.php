<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'admin' => [
        'path' => './assets/admin/app.js',
        'entrypoint' => true,
    ],
    'web' => [
        'path' => './assets/web/app.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'stimulus-use' => [
        'version' => '0.53.1',
    ],
    'apexcharts' => [
        'version' => '7.4.0',
    ],
    'sortablejs' => [
        'version' => '1.15.7',
    ],
    'apexcharts/core' => [
        'version' => '7.4.0',
    ],
];
