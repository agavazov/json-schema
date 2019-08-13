<?php
declare(strict_types=1);

require __DIR__ . './../vendor/autoload.php';

$data = 10;
$jsonSchema = '{
    "type": "integer",
    "minimum": 10
}';

$formats = new \FrontLayer\JsonSchema\Formats();
$schema = new \FrontLayer\JsonSchema\Schema(json_decode($jsonSchema), $formats);
$validator = new \FrontLayer\JsonSchema\Validator($formats);

try {
    $validator->validate($data, $schema);
} catch (\Exception $e) {
    print 'FAIL: ' . $e->getMessage();
    die(1);
}

print 'SUCCESS';
