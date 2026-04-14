<?php

return [
    'languages' => [
        [
            'code' => 'de',
            'store_id' => 1,
            'is_default' => true,
            'fallback_chain' => ['de'],
        ],
        [
            'code' => 'en',
            'store_id' => 1,
            'is_default' => false,
            'fallback_chain' => ['en', 'de'],
        ],
        [
            'code' => 'fr',
            'store_id' => 1,
            'is_default' => false,
            'fallback_chain' => ['fr', 'de'],
        ],
        [
            'code' => 'nl',
            'store_id' => 1,
            'is_default' => false,
            'fallback_chain' => ['nl', 'de'],
        ],
    ],

    'seo' => [
        'language_prefix_mode' => 'prefix',
        'prefixes' => [
            'de' => 'de',
            'en' => 'en',
            'fr' => 'fr',
            'nl' => 'nl',
        ],
    ],
];
