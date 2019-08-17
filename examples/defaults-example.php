<?php
declare(strict_types=1);

require __DIR__ . './../vendor/autoload.php';

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
