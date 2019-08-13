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

$formats = new \FrontLayer\JsonSchema\Formats();
$schema = new \FrontLayer\JsonSchema\Schema($jsonSchema, $formats);
$validator = new \FrontLayer\JsonSchema\Validator($formats, \FrontLayer\JsonSchema\Validator::MODE_CAST);
$newData = $validator->validate($data, $schema);
var_dump($newData);
