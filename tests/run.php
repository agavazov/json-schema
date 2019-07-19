<?php

require __DIR__ . './../vendor/autoload.php';

class Tests
{
    /**
     * Validator instance
     * @var \FrontLayer\JsonSchema\Validator
     */
    protected $validator;

    /**
     * Collected tests
     * @var array
     */
    protected $testCollections = [];

    /**
     * Filter by file
     * @var null|string
     */
    protected $specificFile = null;

    /**
     * Filter by description
     * @var null|string
     */
    protected $descriptionSearch = null;

    /**
     * Tests constructor.
     */
    public function __construct()
    {
        $this->validator = new \FrontLayer\JsonSchema\Validator();
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
     * @param string|null $specificFile
     * @param string|null $descriptionSearch
     */
    public function addFilter(string $specificFile = null, string $descriptionSearch = null): void
    {
        $this->specificFile = $specificFile;
        $this->descriptionSearch = $descriptionSearch;
    }

    /**
     * Run all tests
     */
    public function run(): void
    {
        // Run tests
        foreach ($this->testCollections as $collection) {
            if ($this->specificFile && $this->specificFile !== $collection->file) {
                continue;
            }

            foreach ($collection->content as $content) {
                // Validate
                if (!empty($collection->schemaOnly)) {
                    $this->testSchema($content->valid, $content->schema, $collection->file, $content->description);
                } else {
                    // Data test
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
     * @param object $schema
     * @param string $file
     * @param string $description
     */
    protected function testData($test, object $schema, string $file, string $description): void
    {
        if ($this->descriptionSearch && strstr($description, $this->descriptionSearch) === false) {
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

        $this->results($testResult === $test->valid, $description, $file, $exception);
    }

    /**
     * Test only the schema (without the data)
     * @param bool $valid
     * @param object $schema
     * @param string $file
     * @param string $description
     */
    protected function testSchema(bool $valid, object $schema, string $file, string $description): void
    {
        if ($this->descriptionSearch && strstr($description, $this->descriptionSearch) === false) {
            return;
        }

        $exception = null;

        try {
            $data = null;
            if (property_exists($schema, 'type')) {
                $setType = $schema->type === 'number' ? 'integer' : $schema->type;
                @settype($data, $setType);
            }
            $this->validator->validate($data, $schema);
            $testResult = true;
        } catch (\FrontLayer\JsonSchema\SchemaException $exception) {
            $testResult = false;
        } catch (\Exception $exception) {
            $this->results(false, $description . ' (NON SCHEMA EXCEPTION)', $file, $exception);
            return;
        }

        $this->results($testResult === $valid, $description, $file, $exception);
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
//$test->addFilter(null, 'dependencies');
$test->run();
