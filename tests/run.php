<?php

require __DIR__ . './../vendor/autoload.php';

class Tests
{
    const FILTER_INCLUDE = 1;
    const FILTER_EXCLUDE = 2;

    const SHOW_ALL = 1;
    const SHOW_SUCCESS = 2;
    const SHOW_FAIL = 3;

    /**
     * Validator instance
     * @var \FrontLayer\JsonSchema\Validator
     */
    protected $validator;

    /**
     * Show only
     * @var int
     */
    protected $showOnly = self::SHOW_ALL;

    /**
     * Collected tests
     * @var array
     */
    protected $testCollections = [];

    /**
     * Filter by string search mapped with types
     * @var object
     */
    protected $filters = [];

    /**
     * Tests constructor.
     */
    public function __construct()
    {
        $this->filters = (object)[]; // @todo move it to class body when PHP is ready for this syntax

        $this->validator = new \FrontLayer\JsonSchema\Validator();
    }

    /**
     * Show only: all/fail/success
     * @param int $what
     */
    public function showOnly(int $what = self::SHOW_ALL): void
    {
        $this->showOnly = $what;
    }

    /**
     * Collect all tests
     * @param string $directory
     * @param bool $schemaOnly
     */
    public function addCollection(string $directory, bool $schemaOnly = false): void
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($rii as $file) {
            /* @var $file \SplFileInfo */

            if (substr(dirname($file->getBasename()), 0, 1) === '_') {
                continue;
            }

            if (substr($file->getBasename(), 0, 1) === '_') {
                continue;
            }

            if ($file->isDir()) {
                continue;
            }

            $this->testCollections[] = (object)[
                'file' => $file->getPathname(),
                'schemaOnly' => $schemaOnly,
                'content' => json_decode(file_get_contents($file->getPathname()))
            ];
        }
    }

    /**
     * Specify which tests to run
     * @param string $search
     * @param int $type
     */
    public function addFilter(string $search, int $type): void
    {
        $this->filters->{$type}[] = $search;
    }

    /**
     * Check is the test data match with the filters
     * @param string $testInfo
     * @return bool
     */
    protected function checkFilter(string $testInfo): bool
    {
        $success = true;

        if (!empty($this->filters->{self::FILTER_INCLUDE})) {
            $match = false;

            foreach ($this->filters->{self::FILTER_INCLUDE} as $search) {
                if (strstr($testInfo, $search) !== false) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                $success = false;
            }
        }

        if (!empty($this->filters->{self::FILTER_EXCLUDE})) {
            $match = false;

            foreach ($this->filters->{self::FILTER_EXCLUDE} as $search) {
                if (strstr($testInfo, $search) !== false) {
                    $match = true;
                    break;
                }
            }

            if ($match) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Run all tests
     */
    public function run(): void
    {
        // Run tests
        foreach ($this->testCollections as $collection) {
            foreach ($collection->content as $content) {
                $testData = empty($collection->schemaOnly);

                // Schema test
                if (!$testData) {
                    $this->testSchema($content->valid, $content->schema, $collection->file, $content->description);
                }

                // Data test
                if ($testData) {
                    $this->testSchema(true, $content->schema, $collection->file, $content->description);

                    foreach ($content->tests as $test) {
                        $this->testData($test, $content->schema, $collection->file, $content->description . '::' . $test->description);
                    }
                }
            }
        }
    }

    /**
     * Test provided data
     * @param $test
     * @param object|boolean $schema
     * @param string $file
     * @param string $description
     */
    protected function testData($test, $schema, string $file, string $description): void
    {
        if (!$this->checkFilter($file . $description)) {
            return;
        }

        $mode = 0;

        if (!empty($test->modes) && is_array($test->modes)) {
            if (in_array('CAST', $test->modes)) {
                $mode ^= \FrontLayer\JsonSchema\Validator::MODE_CAST;
            }
        }

        $newData = null;
        $exception = null;

        try {
            $newData = $this->validator->validate($test->data, $schema, $mode);
            $testResult = true;
        } catch (\FrontLayer\JsonSchema\ValidationException $exception) {
            $testResult = false;
        } catch (\Exception $exception) {
            $this->results(false, $description . ' (NON DATA EXCEPTION)', $file, $exception);
            return;
        }

        if (property_exists($test, 'expect')) {
            if (in_array(gettype($test->expect), ['object', 'array'])) {
                if ($newData != $test->expect) {
                    $testResult = false;
                }
            } else {
                if ($newData !== $test->expect) {
                    $testResult = false;
                }
            }
        }

        $this->results($testResult === $test->valid, '(DATA) ' . $description, $file, $exception);
    }

    /**
     * Test only the schema (without the data)
     * @param bool $valid
     * @param object|boolean $schema
     * @param string $file
     * @param string $description
     */
    protected function testSchema(bool $valid, $schema, string $file, string $description): void
    {
        if (!$this->checkFilter($file . $description)) {
            return;
        }

        $exception = null;

        try {
            $data = '';

            if (is_object($schema)) {
                if (property_exists($schema, 'type')) {
                    $setType = $schema->type === 'number' ? 'integer' : $schema->type;
                    @settype($data, $setType);
                }
            }

            $this->validator->validate($data, $schema);
            $testResult = true;
        } catch (\FrontLayer\JsonSchema\SchemaException $exception) {
            $testResult = false;
        } catch (\Exception $exception) {
            $this->results(true, '(SCHEMA | Valid because of "NON SCHEMA EXCEPTION") ' . $description, $file, $exception);
            return;
        }

        $this->results($testResult === $valid, '(SCHEMA) ' . $description, $file, $exception);
    }

    /**
     * Output the results
     * @param bool $success
     * @param string $description
     * @param string|null $file
     * @param Exception|null $exceptionMessage
     */
    public function results(bool $success, string $description, string $file = null, ?\Exception $exceptionMessage = null): void
    {
        if ($this->showOnly !== self::SHOW_ALL) {
            if ($this->showOnly === self::SHOW_SUCCESS && $success == !true) {
                return;
            }

            if ($this->showOnly === self::SHOW_FAIL && $success === true) {
                return;
            }
        }

        $log = '';
        if ($success) {
            $log .= 'SUCCESS: ';
        } else {
            $log .= 'FAIL: ';
        }

        $log .= $description;
        $log .= ' (' . $file . ')';

        if ($exceptionMessage) {
            $log .= ' > ' . $exceptionMessage->getMessage();
        }

        if (!$success) {
            print '<pre style="color: red;">' . $log . '</pre>';
        } else {
            print '<pre style="color: #a3d39b;">' . $log . '</pre>';
        }
    }
}

$test = new Tests();
$test->addCollection('./data', false);
$test->addCollection('./schema', true);

$test->showOnly(Tests::SHOW_FAIL);

// @todo - I can`t find documentation of this ridiculous scenario (ask for official confirmation)
$test->addFilter('minItems validation::ignores non-arrays', Tests::FILTER_EXCLUDE);
$test->addFilter('maxItems validation::ignores non-arrays', Tests::FILTER_EXCLUDE);
$test->addFilter('required validation::ignores arrays', Tests::FILTER_EXCLUDE);
$test->addFilter('required validation::ignores strings', Tests::FILTER_EXCLUDE);
$test->addFilter('required validation::ignores other non-objects', Tests::FILTER_EXCLUDE);
$test->addFilter('minLength validation::ignores non-strings', Tests::FILTER_EXCLUDE);
$test->addFilter('maxLength validation::ignores non-strings', Tests::FILTER_EXCLUDE);
$test->addFilter('minProperties validation::ignores arrays', Tests::FILTER_EXCLUDE);
$test->addFilter('minProperties validation::ignores strings', Tests::FILTER_EXCLUDE);
$test->addFilter('minProperties validation::ignores other non-objects', Tests::FILTER_EXCLUDE);
$test->addFilter('maxProperties validation::ignores arrays', Tests::FILTER_EXCLUDE);
$test->addFilter('maxProperties validation::ignores strings', Tests::FILTER_EXCLUDE);
$test->addFilter('maxProperties validation::ignores other non-objects', Tests::FILTER_EXCLUDE);
$test->addFilter('minimum validation with signed integer::ignores non-numbers', Tests::FILTER_EXCLUDE);
$test->addFilter('minimum validation::ignores non-numbers', Tests::FILTER_EXCLUDE);
$test->addFilter('maximum validation::ignores non-numbers', Tests::FILTER_EXCLUDE);
$test->addFilter('exclusiveMinimum validation::ignores non-numbers', Tests::FILTER_EXCLUDE);
$test->addFilter('exclusiveMaximum validation::ignores non-numbers', Tests::FILTER_EXCLUDE);
$test->addFilter('by int::ignores non-numbers', Tests::FILTER_EXCLUDE);
$test->addFilter('pattern validation::ignores non-strings', Tests::FILTER_EXCLUDE);

$test->run();
