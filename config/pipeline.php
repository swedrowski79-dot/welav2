<?php

return [
    'pipeline' => [
        'run_order' => [
            'import_raw_afs_articles',
            'import_raw_afs_categories',
            'import_raw_afs_documents',
            'import_raw_extra_articles',
            'import_raw_extra_categories',

            'normalize_afs_articles',
            'normalize_afs_categories',
            'normalize_afs_documents',
            'normalize_extra_articles',
            'normalize_extra_categories',

            'merge_stage_products',
            'merge_stage_categories',
            'merge_stage_documents',

            'expand_stage_attributes',
            'expand_stage_media',

            'mirror_xt_products',
            'mirror_xt_categories',
            'mirror_xt_media',
            'mirror_xt_attributes',
            'mirror_xt_seo',
            'mirror_xt_relations',

            'calculate_product_deltas',
            'calculate_category_deltas',
            'calculate_media_deltas',
            'calculate_attribute_deltas',
            'calculate_document_deltas',
            'calculate_seo_deltas',

            'write_xt_categories',
            'write_xt_categories_description',
            'rebuild_nested_sets',
            'write_xt_products',
            'write_xt_products_description',
            'write_xt_products_to_categories',
            'write_xt_media',
            'write_xt_media_link_images',
            'write_xt_media_documents',
            'write_xt_media_link_documents',
            'write_xt_seo_url_categories',
            'write_xt_seo_url_products',

            'finalize_sync_status',
        ],
    ],
];
