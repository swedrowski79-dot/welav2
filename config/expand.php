<?php

return [
    'expand' => [
        'insert_batch_size' => 500,
        'product_attributes_from_translations' => [
            'mode' => 'attribute_rows',
            'source' => 'raw_extra_attribute_translations',
            'target' => 'stage_attribute_translations',
        ],
        'product_media_from_articles' => [
            'mode' => 'media_slots',
            'source' => 'raw_afs_articles',
            'target' => 'stage_product_media',
            'slots' => [
                ['source' => 'image_1', 'slot' => 'image_1', 'sort' => 1],
                ['source' => 'image_2', 'slot' => 'image_2', 'sort' => 2],
                ['source' => 'image_3', 'slot' => 'image_3', 'sort' => 3],
                ['source' => 'image_4', 'slot' => 'image_4', 'sort' => 4],
                ['source' => 'image_5', 'slot' => 'image_5', 'sort' => 5],
                ['source' => 'image_6', 'slot' => 'image_6', 'sort' => 6],
                ['source' => 'image_7', 'slot' => 'image_7', 'sort' => 7],
                ['source' => 'image_8', 'slot' => 'image_8', 'sort' => 8],
                ['source' => 'image_9', 'slot' => 'image_9', 'sort' => 9],
                ['source' => 'image_10', 'slot' => 'image_10', 'sort' => 10],
            ],
        ],
    ],
];
