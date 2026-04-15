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
            'products_to_categories' => [
                'table' => 'xt_products_to_categories',
                'fields' => [
                    'products_id',
                    'categories_id',
                    'master_link',
                    'store_id',
                ],
            ],
            'products_description' => [
                'table' => 'xt_products_description',
                'fields' => [
                    'products_id',
                    'language_code',
                    'products_name',
                    'products_description',
                    'products_short_description',
                    'products_store_id',
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
            'product_attributes' => [
                'table' => 'xt_plg_products_attributes',
                'fields' => [
                    'attributes_id',
                    'attributes_parent',
                    'attributes_model',
                    'sort_order',
                    'status',
                ],
            ],
            'product_attribute_descriptions' => [
                'table' => 'xt_plg_products_attributes_description',
                'fields' => [
                    'attributes_id',
                    'language_code',
                    'attributes_name',
                    'attributes_desc',
                ],
            ],
            'products_to_attributes' => [
                'table' => 'xt_plg_products_to_attributes',
                'fields' => [
                    'products_id',
                    'attributes_id',
                    'attributes_parent_id',
                ],
            ],
            'seo_urls' => [
                'table' => 'xt_seo_url',
                'fields' => [
                    'url_md5',
                    'url_text',
                    'language_code',
                    'link_type',
                    'link_id',
                    'meta_title',
                    'meta_description',
                    'meta_keywords',
                    'store_id',
                ],
            ],
        ],
    ],
];
