<?php

require __DIR__ . './../vendor/autoload.php';

class Tests
{
    /**
     * Directory where all tests are stored
     * @var string
     */
    protected $testsDirectory;

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
     * Tests constructor.
     * @param string $testsDirectory
     */
    public function __construct(string $testsDirectory)
    {
        $this->testsDirectory = $testsDirectory;
        $this->validator = new \FrontLayer\JsonSchema\Validator();

        $this->collectFiles();
    }

    /**
     * Collect all tests
     */
    public function collectFiles(): void
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->testsDirectory));

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
                'content' => json_decode(file_get_contents($file->getPathname()))
            ];
        }
    }

    /**
     * Run all tests
     * @param string|null $specificFile
     * @param string|null $descriptionSearch
     */
    public function runTests(string $specificFile = null, string $descriptionSearch = null): void
    {
        // Run tests
        foreach ($this->testCollections as $collection) {
            if ($specificFile && $specificFile !== $collection->file) {
                continue;
            }

            foreach ($collection->content as $content) {
                // Schema only
                if (!property_exists($content, 'tests')) {
                    $data = null;

                    if (property_exists($content->schema, 'type')) {
                        @settype($data, $content->schema->type);
                    }

                    $content->tests = [
                        (object)[
                            'description' => $content->description,
                            'valid' => $content->valid,
                            'data' => $data
                        ]
                    ];
                }

                // Validate
                foreach ($content->tests as $test) {
                    $description = $content->description . '::' . $test->description;

                    if ($descriptionSearch && strstr($description, $descriptionSearch) === false) {
                        continue;
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
                        $newData = $this->validator->validate($test->data, $content->schema, $mode);
                        $testResult = true;
                        /*
                        } catch (\FrontLayer\JsonSchema\ValidationException $e) {
                            $testResult = false;
                            $exception = $e;
                        */
                    } catch (\Exception $e) {
                        $testResult = false;
                        $exception = $e;
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

                    $this->results($testResult === $test->valid, $description, $collection->file, $exception);
                }
            }
        }
    }

    public function results(bool $success, string $description, string $file = null, ?\Exception $exceptionMessage = null): void
    {
        $log = '';
        if ($success) {
            $log .= 'SUCCESS: ';
        } else {
            $log .= 'FALSE: ';
        }

        $log .= $description;
        $log .= ' (' . $file . ')';

        if ($exceptionMessage) {
            $log .= ' > ' . $exceptionMessage->getMessage();
        }

        if (!$success) {
            print '<pre style="color: red;">' . $log . '</pre>';
        } else {
            print '<pre style="color: green;">' . $log . '</pre>';
        }
    }
}

$dir = './collections/custom/format';
$dir = './collections'; // @todo
$test = new Tests($dir);
//$test->runTests('./collections/custom/types.json', 'basic number validation from integer');
$test->runTests();
