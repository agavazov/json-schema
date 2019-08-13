<?php
declare(strict_types=1);

require __DIR__ . './../vendor/autoload.php';

use \FrontLayer\JsonSchema\Formats;
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

// Initialize formats & register custom format
$formats = new Formats();

$formats->registerFormat('objectId', 'string', function (string $input): bool {
    return (bool)preg_match('/^[a-f\d]{24}$/i', $input);
});

// Initialize schema
$schema = new Schema($jsonSchema);

$formats->registerFormat('objectId', 'string', function (string $input): bool {
    return (bool)preg_match('/^[a-f\d]{24}$/i', $input);
});

// Initialize validator
$validator = new Validator($formats, Validator::MODE_CAST);

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
