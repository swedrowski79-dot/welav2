<?php

return [
    'expand' => [
        'insert_batch_size' => 500,
        'product_attributes_from_translations' => [
            'source' => 'stage_product_translations',
            'target' => 'stage_attribute_translations',
            'slots' => [
                ['name' => 'attribute_name1', 'value' => 'attribute_value1', 'sort' => 1],
                ['name' => 'attribute_name2', 'value' => 'attribute_value2', 'sort' => 2],
                ['name' => 'attribute_name3', 'value' => 'attribute_value3', 'sort' => 3],
                ['name' => 'attribute_name4', 'value' => 'attribute_value4', 'sort' => 4],
            ],
        ],
    ],
];
