<?php
declare(strict_types=1);

require __DIR__ . './../vendor/autoload.php';

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
