# JSON Schema Draft 7 for PHP 7.2+

[![Build Status](https://travis-ci.org/frontlayer/json-schema.svg?branch=master)](https://travis-ci.org/frontlayer/json-schema)
[![Latest Stable Version](https://poser.pugx.org/frontlayer/json-schema/v/stable.png)](https://packagist.org/packages/frontlayer/json-schema)
[![Total Downloads](https://poser.pugx.org/frontlayer/json-schema/downloads.png)](https://packagist.org/packages/frontlayer/json-schema)

Validate `JSON` Structures against a given `Schema`.

Supports almost all official [JSON Schema Draft 7](https://github.com/json-schema-org/JSON-Schema-Test-Suite/tree/master/tests/draft7) tests _* Almost all because some of them cannot be validated by PHP_

## Intro
- PHP strict code
    - stdObjects instead of associative arrays
    - Strict mode `declare(strict_types=1);`
    - Return types `: void, : bool, : string, ...`
    - Method arguments types `bool $isSomething, string $someValue`
- More than 750 tests
- Support data casts e.g. for data from query/post/url paths
- Register custom formats
- Clean code
- Well documented

## What remains to be done
- Grammatically correct comments and exceptions
- Complete: ref, refRemote, allOf, anyOf, oneOf, not, default, definitions, if/then/else
- Add phpunit

## Installation

### Composer

```bash
composer require frontlayer/json-schema
```

## How to start

### Simple Example

```php
$data = 10;
$jsonSchema = '{
    "type": "integer",
    "minimum": 10
}';

$validator = new \FrontLayer\JsonSchema\Validator();

try {
    $validator->validate($data, json_decode($jsonSchema));
} catch (\Exception $e) {
    print 'FAIL: ' . $e->getMessage();
    die(1);
}

print 'SUCCESS';
```

### Cast
```php
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
```

### Register Custom Format
```php
use \FrontLayer\JsonSchema\Validator;
use \FrontLayer\JsonSchema\ValidationException;
use \FrontLayer\JsonSchema\SchemaException;

// Prepare data & schema
$data = '507f191e810c19729de860ea';

$jsonSchema = (object)[
    'type' => 'string',
    'format' => 'objectId'
];

// Initialize validator
$validator = new Validator();

// Register custom format
$validator->registerFormat('objectId', 'string', function (string $input): bool {
    return (bool)preg_match('/^[a-f\d]{24}$/i', $input);
});

// Validate and catch the problems
try {
    $validator->validate($data, $jsonSchema, Validator::MODE_CAST);
} catch (ValidationException $e) {
    print 'Validation Problem: ' . $e->getMessage();
    die(1);
} catch (SchemaException $e) {
    print 'Schema Structure Problem: ' . $e->getMessage();
    die(1);
} catch (\Exception $e) {
    print 'General Problem: ' . $e->getMessage();
    die(1);
}

print 'SUCCESS';
```

## Test the project with more than 750 tests

```bash
composer run test
```
