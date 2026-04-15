<?php

return [
    'merge' => [
        'insert_batch_size' => 250,

        'stage_products' => [
            'base' => 'raw_afs_articles',
            'fields' => [
                'afs_artikel_id' => ['from' => ['raw_afs_articles.afs_artikel_id']],
                'sku' => ['from' => ['raw_afs_articles.sku']],
                'name_default' => ['from' => ['raw_afs_articles.name']],
                'description_default' => ['from' => ['raw_afs_articles.description']],
                'short_description_default' => ['from' => ['raw_afs_articles.short_text']],
                'ean' => ['from' => ['raw_afs_articles.ean']],
                'stock' => ['from' => ['raw_afs_articles.stock']],
                'price' => ['from' => ['raw_afs_articles.price']],
                'weight' => ['from' => ['raw_afs_articles.weight']],
                'category_afs_id' => ['from' => ['raw_afs_articles.category_afs_id']],
                'category_name' => ['from' => ['raw_afs_articles.category_name']],
                'tax_rate' => ['from' => ['raw_afs_articles.tax_rate']],
                'unit' => ['from' => ['raw_afs_articles.unit']],
                'min_qty' => ['from' => ['raw_afs_articles.min_qty']],
                'variant_flag' => ['from' => ['raw_afs_articles.variant_flag']],
                'product_type' => ['from' => ['raw_afs_articles.product_type']],
                'is_master' => ['from' => ['raw_afs_articles.is_master']],
                'is_slave' => ['from' => ['raw_afs_articles.is_slave']],
                'is_standard' => ['from' => ['raw_afs_articles.is_standard']],
                'master_sku' => ['from' => ['raw_afs_articles.master_sku']],
                'online_flag' => ['from' => ['raw_afs_articles.online_flag']],
            ],
        ],

        'stage_product_translations' => [
            'base' => 'raw_extra_article_translations',
            'match' => [
                'raw_afs_articles' => [
                    'local' => 'afs_artikel_id',
                    'foreign' => 'afs_artikel_id',
                    'mode' => 'left',
                ],
            ],
            'fields' => [
                'afs_artikel_id' => ['from' => ['raw_extra_article_translations.afs_artikel_id']],
                'sku' => ['from' => ['raw_extra_article_translations.sku', 'raw_afs_articles.sku'], 'strategy' => 'first_not_empty'],
                'master_sku' => ['from' => ['raw_extra_article_translations.master_sku', 'raw_afs_articles.master_sku'], 'strategy' => 'first_not_empty'],
                'language_code' => ['from' => ['raw_extra_article_translations.language_code_normalized']],
                'name' => ['from' => ['raw_extra_article_translations.name', 'raw_afs_articles.name'], 'strategy' => 'first_not_empty'],
                'description' => ['from' => ['raw_extra_article_translations.description', 'raw_afs_articles.description'], 'strategy' => 'first_not_empty'],
                'technical_data_html' => ['from' => ['raw_extra_article_translations.technical_data_html']],
                'short_description' => ['from' => ['raw_afs_articles.short_text'], 'strategy' => 'first_not_empty'],
                'meta_title' => [
                    'from' => [
                        'raw_extra_article_translations.meta_title',
                        'raw_extra_article_translations.name',
                        'raw_afs_articles.name',
                    ],
                    'strategy' => 'first_not_empty',
                ],
                'meta_description' => [
                    'from' => [
                        'raw_extra_article_translations.meta_description',
                        'raw_extra_article_translations.description',
                        'raw_afs_articles.short_text',
                        'raw_afs_articles.description',
                    ],
                    'strategy' => 'first_not_empty',
                ],
                'product_type' => ['from' => ['raw_afs_articles.product_type']],
                'attribute_name1' => ['from' => ['raw_extra_article_translations.attribute_name1']],
                'attribute_name2' => ['from' => ['raw_extra_article_translations.attribute_name2']],
                'attribute_name3' => ['from' => ['raw_extra_article_translations.attribute_name3']],
                'attribute_name4' => ['from' => ['raw_extra_article_translations.attribute_name4']],
                'attribute_value1' => ['from' => ['raw_extra_article_translations.attribute_value1']],
                'attribute_value2' => ['from' => ['raw_extra_article_translations.attribute_value2']],
                'attribute_value3' => ['from' => ['raw_extra_article_translations.attribute_value3']],
                'attribute_value4' => ['from' => ['raw_extra_article_translations.attribute_value4']],
                'source_directory' => ['from' => ['raw_extra_article_translations.source_directory']],
            ],
        ],

        'stage_product_documents' => [
            'base' => 'raw_afs_documents',
            'required_fields' => ['afs_artikel_id', 'file_name'],
            'fields' => [
                'afs_document_id' => ['from' => ['raw_afs_documents.afs_document_id']],
                'afs_artikel_id' => ['from' => ['raw_afs_documents.afs_artikel_id']],
                'title' => ['from' => ['raw_afs_documents.title']],
                'file_name' => ['from' => ['raw_afs_documents.file_name']],
                'path' => ['from' => ['raw_afs_documents.file_name', 'raw_afs_documents.path'], 'strategy' => 'first_not_empty'],
                'source_path' => ['from' => ['raw_afs_documents.path']],
                'document_type' => ['from' => ['raw_afs_documents.document_type']],
                'sort_order' => ['from' => ['raw_afs_documents.sort_order', 'raw_afs_documents.afs_document_id'], 'strategy' => 'first_not_empty'],
                'position' => ['from' => ['raw_afs_documents.sort_order', 'raw_afs_documents.afs_document_id'], 'strategy' => 'first_not_empty'],
            ],
        ],

        'stage_categories' => [
            'base' => 'raw_afs_categories',
            'fields' => [
                'afs_wg_id' => ['from' => ['raw_afs_categories.afs_wg_id']],
                'parent_afs_id' => ['from' => ['raw_afs_categories.parent_afs_id']],
                'level' => ['from' => ['raw_afs_categories.level']],
                'name_default' => ['from' => ['raw_afs_categories.name']],
                'description_default' => ['from' => ['raw_afs_categories.description']],
                'image' => ['from' => ['raw_afs_categories.image']],
                'header_image' => ['from' => ['raw_afs_categories.header_image']],
                'online_flag' => ['from' => ['raw_afs_categories.online_flag']],
            ],
        ],

        'stage_category_translations' => [
            'base' => 'raw_extra_category_translations',
            'match' => [
                'raw_afs_categories' => [
                    'local' => 'afs_wg_id',
                    'foreign' => 'afs_wg_id',
                    'mode' => 'left',
                ],
            ],
            'fields' => [
                'afs_wg_id' => ['from' => ['raw_extra_category_translations.afs_wg_id']],
                'language_code' => ['from' => ['raw_extra_category_translations.language_code_normalized']],
                'original_name' => ['from' => ['raw_extra_category_translations.original_name']],
                'name' => ['from' => ['raw_extra_category_translations.name', 'raw_afs_categories.name'], 'strategy' => 'first_not_empty'],
                'description' => ['from' => ['raw_afs_categories.description']],
                'meta_title' => [
                    'from' => [
                        'raw_extra_category_translations.meta_title',
                        'raw_extra_category_translations.name',
                        'raw_afs_categories.name',
                    ],
                    'strategy' => 'first_not_empty',
                ],
                'meta_description' => [
                    'from' => [
                        'raw_extra_category_translations.meta_description',
                        'raw_afs_categories.description',
                    ],
                    'strategy' => 'first_not_empty',
                ],
            ],
        ],
    ],
];
