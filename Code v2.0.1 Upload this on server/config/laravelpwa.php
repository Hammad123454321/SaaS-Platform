<?php

return [
    'name' => env('APP_NAME', 'Taskify'),

    'manifest' => [
        'name' => env('APP_NAME', 'Taskify'),
        'short_name' => 'Taskify',
        'start_url' => '/home',
        'background_color' => '#ffffff',
        'description' => 'Taskify helps you manage tasks efficiently and collaboratively.',
        'theme_color' => '#000000',
        'display' => 'standalone',
        'orientation' => 'any',
        'status_bar' => 'black',

        'icons' => [

            [
                'path' => '/storage/images/icons/logo-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any'
            ]
        ],
        'splash' => [
            '640x1136' => '/images/icons/splash-640x1136.png',
            '750x1334' => '/images/icons/splash-750x1334.png',
            '828x1792' => '/images/icons/splash-828x1792.png',
            '1125x2436' => '/images/icons/splash-1125x2436.png',
            '1242x2208' => '/images/icons/splash-1242x2208.png',
            '1242x2688' => '/images/icons/splash-1242x2688.png',
            '1536x2048' => '/images/icons/splash-1536x2048.png',
            '1668x2224' => '/images/icons/splash-1668x2224.png',
            '1668x2388' => '/images/icons/splash-1668x2388.png',
            '2048x2732' => '/images/icons/splash-2048x2732.png',
        ],

        'custom' => [
            // 'screenshots' => [
            //     [
            //         'src' => '/storage/images/screenshots/screen-desktop.png',
            //         'sizes' => '1920x980',
            //         'type' => 'image/png',
            //         'form_factor' => 'wide',
            //         'label' => 'Taskify dashboard'
            //     ],
            // ]
        ],


    ]
];
