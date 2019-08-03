<?php
declare(strict_types=1);

require __DIR__ . './../vendor/autoload.php';

$data = (object)[
    'stringTest' => 123, // Integer > String
    'jsonStringTest' => '{"key": "value"}', // JSON string > PHP Object
    'integerTest' => '456', // String > Integer
    'numberTest' => '10.10', // String > Float/Double
    'booleanTest' => 'TRUE' // String > Boolean
];

$jsonSchema = (object)[
    'type' => 'object',
    'properties' => (object)[
        'stringTest' => (object)[
            'type' => 'string'
        ],
        'jsonStringTest' => (object)[
            'type' => 'object'
        ],
        'integerTest' => (object)[
            'type' => 'integer'
        ],
        'numberTest' => (object)[
            'type' => 'number'
        ],
        'booleanTest' => (object)[
            'type' => 'boolean'
        ]
    ]
];


$validator = new \FrontLayer\JsonSchema\Validator();
$newData = $validator->validate($data, $jsonSchema, \FrontLayer\JsonSchema\Validator::MODE_CAST);
var_dump($newData);
