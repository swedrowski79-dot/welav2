<?php
return [
    'normalize' => [
        'afs.articles' => [
            'fields' => [
                'afs_artikel_id'  => 'Artikel',
                'sku'             => 'Artikelnummer',
                'name'            => 'Bezeichnung',
                'description'     => 'Langtext',
                'short_text'      => 'Werbetext1',
                'ean'             => 'EANNummer',
                'stock'           => 'Bestand',
                'price'           => 'VK3',
                'weight'          => 'Bruttogewicht',
                'category_afs_id' => 'Warengruppe',
                'category_name'   => 'Warengruppen',
                'tax_rate'        => 'Umsatzsteuer',
                'min_qty'         => 'Zusatzfeld01',
                'variant_flag'    => 'Zusatzfeld07',
                'unit'            => 'Einheit',
                'online_flag'     => 'Internet',
            ],
            'calculated' => [
                'product_type' => 'calc:product_type_from_variant_flag',
                'is_master'    => 'calc:is_master',
                'is_slave'     => 'calc:is_slave',
                'is_standard'  => 'calc:is_standard',
                'master_sku'   => 'calc:master_sku',
            ],
        ],

        'afs.categories' => [
            'fields' => [
                'afs_wg_id'     => 'Warengruppe',
                'parent_afs_id' => 'Anhang',
                'level'         => 'Ebene',
                'name'          => 'Bezeichnung',
                'description'   => 'Beschreibung',
                'image'         => 'Bild',
                'header_image'  => 'Bild_gross',
                'online_flag'   => 'Internet',
            ],
            'calculated' => [
                'online_flag' => 'calc:afs_category_online_flag',
            ],
        ],

        'extra.article_translations' => [
            'fields' => [
                'row_id'              => 'id',
                'afs_artikel_id'      => 'artikel_id',
                'sku'                 => 'article_number',
                'master_sku'          => 'master_article_number',
                'language_code'       => 'language',
                'name'                => 'article_name',
                'description'         => 'description',
                'technical_data_html' => 'technical_data_html',
                'attribute_name1'     => 'attribute_name1',
                'attribute_name2'     => 'attribute_name2',
                'attribute_name3'     => 'attribute_name3',
                'attribute_name4'     => 'attribute_name4',
                'attribute_value1'    => 'attribute_value1',
                'attribute_value2'    => 'attribute_value2',
                'attribute_value3'    => 'attribute_value3',
                'attribute_value4'    => 'attribute_value4',
                'meta_title'          => 'meta_title',
                'meta_description'    => 'meta_description',
                'is_master'           => 'is_master',
                'source_directory'    => 'source_directory',
            ],
            'calculated' => [
                'language_code_normalized' => 'calc:normalize_language_code',
            ],
        ],

        'extra.category_translations' => [
            'fields' => [
                'row_id'           => 'id',
                'afs_wg_id'        => 'warengruppen_id',
                'original_name'    => 'original_name',
                'language_code'    => 'language',
                'name'             => 'translated_name',
                'meta_title'       => 'meta_title',
                'meta_description' => 'meta_description',
            ],
            'calculated' => [
                'language_code_normalized' => 'calc:normalize_language_code',
            ],
        ],
    ],
];
