<?php
declare(strict_types=1);

require __DIR__ . './../vendor/autoload.php';

class Tests
{
    const FILTER_INCLUDE = 1;
    const FILTER_EXCLUDE = 2;

    const SHOW_ALL = 1;
    const SHOW_SUCCESS = 2;
    const SHOW_FAIL = 3;

    /**
     * Count fail test
     * @var int
     */
    protected $totalFails = 0;

    /**
     * Count success test
     * @var int
     */
    protected $totalSuccess = 0;

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
     * PHP is being run as a CLI
     * @var bool
     */
    protected $isCli = true;

    /**
     * Tests constructor.
     */
    public function __construct()
    {
        $this->filters = (object)[]; // @todo move it to class body when PHP is ready for this syntax

        $this->isCli = PHP_SAPI === 'cli';
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
                    $schema = json_decode(json_encode($content->schema));
                    $this->testSchema($content->valid, $schema, $collection->file, $content->description);
                }

                // Data test
                if ($testData) {
                    $schema = json_decode(json_encode($content->schema));
                    $this->testSchema(true, $schema, $collection->file, $content->description);

                    foreach ($content->tests as $test) {
                        $schema = json_decode(json_encode($content->schema));
                        $this->testData($test, $schema, $collection->file, $content->description . '::' . $test->description);
                    }
                }
            }
        }

        // Exit
        if ($this->totalSuccess > 0) {
            $this->results(true, sprintf('TOTAL SUCCESSFUL TESTS: %d', $this->totalSuccess));
        }

        if ($this->totalFails > 0) {
            $this->results(false, sprintf('TOTAL FAILED TESTS: %d', $this->totalFails));
            exit(1);
        }
    }

    /**
     * Test provided data
     * @param $test
     * @param object|boolean $jsonSchema
     * @param string $file
     * @param string $description
     */
    protected function testData($test, $jsonSchema, string $file, string $description): void
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
            $schema = new \FrontLayer\JsonSchema\Schema($jsonSchema);
            $validator = new \FrontLayer\JsonSchema\Validator($mode);
            $newData = $validator->validate($test->data, $schema);
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
     * @param object|boolean $jsonSchema
     * @param string $file
     * @param string $description
     */
    protected function testSchema(bool $valid, $jsonSchema, string $file, string $description): void
    {
        if (!$this->checkFilter($file . $description)) {
            return;
        }

        $exception = null;

        try {
            $schema = new \FrontLayer\JsonSchema\Schema($jsonSchema);
            $validator = new \FrontLayer\JsonSchema\Validator();
            $validator->validate('', $schema);
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

        if ($file) {
            $log .= ' (' . $file . ')';
        }

        if ($exceptionMessage) {
            $log .= ' > ' . $exceptionMessage->getMessage();
        }

        if (!$success) {
            $this->totalFails++;

            if ($this->isCli) {
                print PHP_EOL . "\e[0;31;40m" . $log . "!\e[0m" . PHP_EOL;
            } else {
                print '<pre style="color: red;">' . $log . '</pre>';
            }
        } else {
            $this->totalSuccess++;

            if ($this->isCli) {
                print PHP_EOL . "\e[0;32;40m" . $log . "!\e[0m" . PHP_EOL;
            } else {
                print '<pre style="color: #a3d39b;">' . $log . '</pre>';
            }
        }
    }
}

$test = new Tests();
$test->addCollection(__DIR__ . '/data', false);
$test->addCollection(__DIR__ . '/schema', true);

$test->showOnly(Tests::SHOW_FAIL);

// @todo this must be fine - delete it and fix it
$test->addFilter('all items match schema', Tests::FILTER_EXCLUDE);

// @todo remove when allOf is ready
$test->addFilter('additionalProperties should not look in applicators', Tests::FILTER_EXCLUDE);

// @todo "pseudo-arrays" are arrays or not?
$test->addFilter('JavaScript pseudo-array is valid', Tests::FILTER_EXCLUDE);

// @todo so is patternProperty with high priority or not... or what means this test
$test->addFilter('properties, patternProperties, additionalProperties interaction::patternProperty invalidates property', Tests::FILTER_EXCLUDE);

$test->run();
