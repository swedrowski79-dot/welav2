<?php

return [
    'mirror' => [
        'xt_products' => [
            'source_table' => 'xt_products',
            'mirror_table' => 'xt_mirror_products',
            'key' => 'products_id',
            'fields' => [
                'products_id', 'external_id', 'products_model', 'products_ean', 'products_quantity',
                'products_price', 'products_weight', 'products_status', 'products_master_flag',
                'products_master_model', 'products_image', 'last_modified',
            ],
            'index_by' => 'external_id',
        ],

        'xt_products_description' => [
            'source_table' => 'xt_products_description',
            'mirror_table' => 'xt_mirror_products_description',
            'key' => ['products_id', 'language_code'],
            'fields' => [
                'products_id', 'language_code', 'products_name', 'products_description',
                'products_short_description', 'products_store_id',
            ],
        ],

        'xt_categories' => [
            'source_table' => 'xt_categories',
            'mirror_table' => 'xt_mirror_categories',
            'key' => 'categories_id',
            'fields' => [
                'categories_id', 'external_id', 'parent_id', 'categories_level',
                'categories_image', 'categories_master_image', 'categories_status', 'last_modified',
            ],
            'index_by' => 'external_id',
        ],

        'xt_categories_description' => [
            'source_table' => 'xt_categories_description',
            'mirror_table' => 'xt_mirror_categories_description',
            'key' => ['categories_id', 'language_code'],
            'fields' => [
                'categories_id', 'language_code', 'categories_name',
                'categories_heading_title', 'categories_description', 'categories_store_id',
            ],
        ],

        'xt_products_to_categories' => [
            'source_table' => 'xt_products_to_categories',
            'mirror_table' => 'xt_mirror_products_to_categories',
            'key' => ['products_id', 'categories_id'],
            'fields' => ['products_id', 'categories_id', 'master_link', 'store_id'],
        ],

        'xt_media' => [
            'source_table' => 'xt_media',
            'mirror_table' => 'xt_mirror_media',
            'key' => 'id',
            'fields' => [
                'id', 'external_id', 'file', 'type', 'class', 'status', 'date_added', 'last_modified',
            ],
            'index_by' => 'external_id',
        ],

        'xt_media_link' => [
            'source_table' => 'xt_media_link',
            'mirror_table' => 'xt_mirror_media_link',
            'key' => 'ml_id',
            'fields' => ['ml_id', 'm_id', 'link_id', 'class', 'type', 'sort_order'],
        ],

        'xt_plg_products_attributes' => [
            'source_table' => 'xt_plg_products_attributes',
            'mirror_table' => 'xt_mirror_plg_products_attributes',
            'key' => 'attributes_id',
            'fields' => [
                'attributes_id', 'attributes_parent', 'attributes_model', 'sort_order', 'status',
            ],
        ],

        'xt_plg_products_attributes_description' => [
            'source_table' => 'xt_plg_products_attributes_description',
            'mirror_table' => 'xt_mirror_plg_products_attributes_description',
            'key' => ['attributes_id', 'language_code'],
            'fields' => ['attributes_id', 'language_code', 'attributes_name', 'attributes_desc'],
        ],

        'xt_plg_products_to_attributes' => [
            'source_table' => 'xt_plg_products_to_attributes',
            'mirror_table' => 'xt_mirror_plg_products_to_attributes',
            'key' => ['products_id', 'attributes_id'],
            'fields' => ['products_id', 'attributes_id', 'attributes_parent_id'],
        ],

        'xt_seo_url' => [
            'source_table' => 'xt_seo_url',
            'mirror_table' => 'xt_mirror_seo_url',
            'key' => ['link_type', 'link_id', 'language_code', 'store_id'],
            'fields' => [
                'url_md5', 'url_text', 'language_code', 'link_type', 'link_id',
                'meta_title', 'meta_description', 'meta_keywords', 'store_id',
            ],
        ],
    ],
];
