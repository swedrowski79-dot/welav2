<?php

return [
    'delta' => [
        'products' => [
            'stage_table' => 'stage_products',
            'xt_table' => 'xt_products',
            'identity' => [
                'stage_field' => 'afs_artikel_id',
                'xt_lookup_field' => 'external_id',
                'xt_id_field' => 'products_id',
            ],
            'compare' => [
                'products_model'        => 'sku',
                'products_ean'          => 'ean',
                'products_quantity'     => 'stock',
                'products_price'        => 'price',
                'products_weight'       => 'weight',
                'products_status'       => 'online_flag',
                'products_master_flag'  => 'is_master',
                'products_master_model' => 'master_sku',
            ],
        ],

        'categories' => [
            'stage_table' => 'stage_categories',
            'xt_table' => 'xt_categories',
            'identity' => [
                'stage_field' => 'afs_wg_id',
                'xt_lookup_field' => 'external_id',
                'xt_id_field' => 'categories_id',
            ],
            'compare' => [
                'categories_image'        => 'image',
                'categories_master_image' => 'header_image',
                'categories_status'       => 'online_flag',
            ],
        ],

        'products_description' => [
            'type' => 'multilang_child',
            'stage_table' => 'stage_products',
            'xt_table' => 'xt_products_description',
            'languages' => ['de', 'en', 'fr', 'nl'],
            'identity' => [
                'stage_field' => 'afs_artikel_id',
                'xt_entity_lookup' => 'xt_products.external_id',
                'xt_entity_id_field' => 'xt_products.products_id',
            ],
            'compare_by_language' => [
                'de' => [
                    'products_name' => 'name_de',
                    'products_description' => 'description_de',
                    'products_short_description' => 'short_description_de',
                ],
                'en' => [
                    'products_name' => 'name_de',
                    'products_description' => 'description_en',
                    'products_short_description' => 'short_description_en',
                ],
                'fr' => [
                    'products_name' => 'name_de',
                    'products_description' => 'description_fr',
                    'products_short_description' => 'short_description_fr',
                ],
                'nl' => [
                    'products_name' => 'name_de',
                    'products_description' => 'description_nl',
                    'products_short_description' => 'short_description_nl',
                ],
            ],
        ],

        'seo_products' => [
            'type' => 'seo',
            'stage_table' => 'stage_products',
            'xt_table' => 'xt_seo_url',
            'languages' => ['de', 'en', 'fr', 'nl'],
            'identity' => [
                'link_type' => 1,
                'link_id' => 'ref:xt_products.products_id by external_id=stage.afs_artikel_id',
                'store_id' => 1,
            ],
            'compare_by_language' => [
                'de' => [
                    'meta_title' => 'meta_title_de',
                    'meta_description' => 'meta_description_de',
                ],
                'en' => [
                    'meta_title' => 'meta_title_en',
                    'meta_description' => 'meta_description_en',
                ],
                'fr' => [
                    'meta_title' => 'meta_title_fr',
                    'meta_description' => 'meta_description_fr',
                ],
                'nl' => [
                    'meta_title' => 'meta_title_nl',
                    'meta_description' => 'meta_description_nl',
                ],
            ],
            'preserve_existing_url' => true,
        ],

        'seo_categories' => [
            'type' => 'seo',
            'stage_table' => 'stage_categories',
            'xt_table' => 'xt_seo_url',
            'languages' => ['de', 'en', 'fr', 'nl'],
            'identity' => [
                'link_type' => 2,
                'link_id' => 'ref:xt_categories.categories_id by external_id=stage.afs_wg_id',
                'store_id' => 1,
            ],
            'compare_by_language' => [
                'de' => [
                    'meta_title' => 'meta_title_de',
                    'meta_description' => 'meta_description_de',
                ],
                'en' => [
                    'meta_title' => 'meta_title_en',
                    'meta_description' => 'meta_description_en',
                ],
                'fr' => [
                    'meta_title' => 'meta_title_fr',
                    'meta_description' => 'meta_description_fr',
                ],
                'nl' => [
                    'meta_title' => 'meta_title_nl',
                    'meta_description' => 'meta_description_nl',
                ],
            ],
            'preserve_existing_url' => true,
        ],
    ],
];
