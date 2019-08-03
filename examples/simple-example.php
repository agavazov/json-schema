<?php
declare(strict_types=1);

require __DIR__ . './../vendor/autoload.php';

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
