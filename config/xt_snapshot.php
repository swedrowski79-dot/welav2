<?php

return [
    'snapshot' => [
        'page_size' => 500,
        'write_batch_size' => 500,
        'sources' => [
            'products' => [
                'table' => 'xt_products',
                'fields' => [
                    'products_id',
                    'external_id',
                    'products_model',
                    'products_ean',
                    'products_quantity',
                    'products_price',
                    'products_weight',
                    'products_status',
                    'products_master_flag',
                    'products_master_model',
                    'products_image',
                    'last_modified',
                ],
            ],
            'categories' => [
                'table' => 'xt_categories',
                'fields' => [
                    'categories_id',
                    'external_id',
                    'parent_id',
                    'categories_level',
                    'categories_image',
                    'categories_master_image',
                    'categories_status',
                    'last_modified',
                ],
            ],
            'media' => [
                'table' => 'xt_media',
                'fields' => [
                    'id',
                    'external_id',
                    'file',
                    'type',
                    'class',
                    'status',
                    'last_modified',
                ],
            ],
            'media_links' => [
                'table' => 'xt_media_link',
                'fields' => [
                    'ml_id',
                    'm_id',
                    'link_id',
                    'class',
                    'type',
                    'sort_order',
                ],
            ],
        ],
    ],
];
