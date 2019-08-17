# JSON Schema Draft 7 for PHP 7.2+

[![Build Status](https://travis-ci.org/frontlayer/json-schema.svg?branch=master)](https://travis-ci.org/frontlayer/json-schema)
[![Latest Stable Version](https://poser.pugx.org/frontlayer/json-schema/v/stable.png)](https://packagist.org/packages/frontlayer/json-schema)
[![Total Downloads](https://poser.pugx.org/frontlayer/json-schema/downloads.png)](https://packagist.org/packages/frontlayer/json-schema)

Validate `JSON` Structures against a given `Schema`.

Supports **all** official [JSON Schema Draft 7](https://github.com/json-schema-org/JSON-Schema-Test-Suite/tree/master/tests/draft7) tests

_* Without two for bignum because PHP cannot validate them_

## Intro
- PHP strict code
    - stdObjects instead of associative arrays
    - Strict mode `declare(strict_types=1);`
    - Return types `: void, : bool, : string, ...`
    - Method arguments types `bool $isSomething, string $someValue`
- More than 2300 tests
- Tests for OpenAPI with [Official OpenAPI Schema (Draft 4)](https://raw.githubusercontent.com/OAI/OpenAPI-Specification/master/schemas/v3.0/schema.json) and [PetStore Specification](https://github.com/OAI/OpenAPI-Specification/blob/master/examples/v3.0/petstore.yaml) 
- Support data casts e.g. for data from query/post/url paths
- Register custom formats
- Apply defaults in complex structures like `if`/`then`/`else`, `oneOf`/`allOf`/`anyOf`
- Clean code
- Well documented

## What remains to be done `@todo`
- Complete: `refRemote` and few tests with `ref`
- Add versions for composer
- Grammatically correct comments and exceptions

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

$schema = new \FrontLayer\JsonSchema\Schema(json_decode($jsonSchema));
$validator = new \FrontLayer\JsonSchema\Validator();

try {
    $validator->validate($data, $schema);
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

$schema = new \FrontLayer\JsonSchema\Schema($jsonSchema);
$validator = new \FrontLayer\JsonSchema\Validator(\FrontLayer\JsonSchema\Validator::MODE_CAST);
$newData = $validator->validate($data, $schema);
var_dump($newData);
```

### Register Custom Format
```php
use \FrontLayer\JsonSchema\Schema;
use \FrontLayer\JsonSchema\Validator;
use \FrontLayer\JsonSchema\ValidationException;
use \FrontLayer\JsonSchema\SchemaException;

// Prepare data & schema
$data = '507f191e810c19729de860ea';

$jsonSchema = (object)[
    'type' => 'string',
    'format' => 'objectId'
];

// Initialize
$schema = new Schema($jsonSchema);
$validator = new Validator(Validator::MODE_CAST);

// Register new format
$validator->registerFormat('objectId', function (string $input): bool {
    return (bool)preg_match('/^[a-f\d]{24}$/i', $input);
});

// Validate and catch the problems
try {
    $validator->validate($data, $schema);
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

### Build whole week directly from the defaults in the different cases
```php
$data = (object)[
    'week' => (object)[]
];

$jsonSchema = '
{
    "properties": {
        "week": {
            "properties": {
                "day1": {
                    "default": "Monday"
                }
            },
            "if": {
                "type": "object"
            },
            "then": {
                "default": {
                    "day2": "Thursday"
                }
            },
            "allOf": [
                {
                    "properties": {
                        "day2": {
                            "const": "Thursday"
                        }
                    },
                    "default": {
                        "day3": "Wednesday"
                    }
                },
                {
                    "properties": {
                        "day3": {
                            "const": "Wednesday"
                        }
                    },
                    "default": {
                        "day4": "Tuesday"
                    }
                }
            ],
            "anyOf": [
                {
                    "properties": {
                        "day4": {
                            "const": "Fail"
                        }
                    },
                    "default": {
                        "day5": "Fail"
                    }
                },
                {
                    "properties": {
                        "day4": {
                            "const": "Tuesday"
                        }
                    },
                    "default": {
                        "day5": "Friday",
                        "day6": "Saturday"
                    }
                }
            ],
            "oneOf": [
                {
                    "type": "boolean",
                    "default": {
                        "day3": "Fail"
                    }
                },
                {
                    "properties": {
                        "day6": {
                            "const": "Saturday"
                        }
                    },
                    "default": {
                        "day7": "Sunday"
                    }
                }
            ]
        }
    }
}
';

$schema = new \FrontLayer\JsonSchema\Schema(json_decode($jsonSchema));
$validator = new \FrontLayer\JsonSchema\Validator(\FrontLayer\JsonSchema\Validator::MODE_APPLY_DEFAULTS);

$newData = (array)$validator->validate($data, $schema)->week;
ksort($newData);
var_dump(implode('; ', $newData)); // Monday;Thursday;Wednesday;Tuesday;Friday;Saturday;Sunday
```

## Validation modes
| Flag | Description |
|------|-------------|
| `Validator::MODE_CAST` | Cast the data to the specific format |
| `Validator::MODE_REMOVE_ADDITIONALS` | Remove additional properties & additional items if they are not set to TRUE/FALSE |
| `Validator::MODE_APPLY_DEFAULTS` | Apply default values defined in the schema |

You can combine multiple flags with the bitwise operator `^`
```php
$validator->validate($data, $schema, Validator::MODE_CAST ^ Validator::MODE_REMOVE_ADDITIONALS ^ Validator::MODE_APPLY_DEFAULTS)
```

## Test the project with all official & custom tests

```bash
composer run test
```
