<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Ref
{
    /**
     * External $url timeout ms.
     */
    const CURL_TIMEOUT = 1000;

    /**
     * Storage of all references (the key is $ref keyword value)
     * @var object
     */
    protected $referenceStorage;

    /**
     * Storage of all identifications (the key is $id keyword value)
     * @var object
     */
    protected $identificationStorage;

    /**
     * Storage of all references with path key (the key is place where the reference is created)
     * With this registry you can find which value goes to some reference and which not (check isReference())
     * @var object
     */
    protected $pathReferences;

    /**
     * Mapped object with url key and response value (to skip second request to the same URL)
     * @var object
     */
    protected $urlCache;

    /**
     * The main object
     * @var object
     */
    protected $rootObject;

    /**
     * External definitions storage which will not goes into conflict with the root object
     * @var object
     */
    protected $definitions;

    /**
     * Ref constructor.
     * @param $object
     * @throws RefException
     */
    public function __construct($object)
    {
        // Register root object
        $this->rootObject = $object;

        // Assign blank object value to self properties to be objects
        $this->referenceStorage = (object)[];
        $this->identificationStorage = (object)[];
        $this->urlCache = (object)[];
        $this->pathReferences = (object)[];

        // Get root type
        $rootType = gettype($this->rootObject);

        // Break if the type of the root object it not array
        if (!in_array($rootType, ['array', 'object'])) {
            return;
        }

        // Collect all identifications
        $this->registerIdentifications($this, 'rootObject');

        // Collect all references
        $this->registerReferences($this, 'rootObject', '#');

        // Temporary remove the deffinitions from the root object
        if ($rootType === 'object' && property_exists($this->rootObject, 'definitions')) {
            $this->definitions = $this->rootObject->definitions;
            unset($this->rootObject->definitions);
        }

        // After the full collection of the references and identifications is ready we can assign references values
        $this->buildReferences($this->rootObject);

        // Return back the reference object
        if (isset($this->definitions)) {
            $this->rootObject->definitions = $this->definitions;
        }

        // Clean up some memory
        unset($this->referenceStorage);
        unset($this->urlCache);
    }

    /**
     * Check if specific path is mark as a reference. This will help you to skip recursive collisions
     * @param string $path
     * @return bool
     */
    public function isReference(string $path): bool
    {
        return property_exists($this->pathReferences, $path);
    }

    /**
     * Collect all identifications
     * @param $parent
     * @param $parentKey
     */
    protected function registerIdentifications(&$parent, $parentKey): void
    {
        // Get parent type & current value + type
        $parentType = gettype($parent);
        $currentValue = $parentType === 'object' ? $parent->{$parentKey} : $parent[$parentKey];
        $currentType = gettype($currentValue);

        // Check for $id property in current object
        if ($currentType === 'object') {
            // If the object has $id then it will be register to the idStorage
            if (property_exists($currentValue, '$id')) {
                // $id can be string only
                if (is_string($currentValue->{'$id'})) {
                    $this->identificationStorage->{$currentValue->{'$id'}} = $parent->{$parentKey};

                    // Remove it because we will not need it anymore
                    unset($parent->{$parentKey}->{'$id'});
                }
            }
        }

        // Go deep
        if (in_array($currentType, ['array', 'object'])) {
            foreach ($currentValue as $walkKey => $walkValue) {
                if ($parentType === 'object') {
                    $this->registerIdentifications($parent->{$parentKey}, $walkKey);
                } elseif ($parentType === 'array') {
                    $this->registerIdentifications($parent[$parentKey], $walkKey);
                }
            }
        }
    }

    /**
     * Collect all references
     * @param $parent
     * @param $parentKey
     * @param $path
     * @throws RefException
     */
    protected function registerReferences(&$parent, $parentKey, $path): void
    {
        // Get parent type & current value + type
        $parentType = gettype($parent);
        $currentValue = $parentType === 'object' ? $parent->{$parentKey} : $parent[$parentKey];
        $currentType = gettype($currentValue);

        // Check for $ref property in current object
        if ($currentType === 'object') {
            // Transform reference to global references location
            if (property_exists($currentValue, '$ref')) {
                $ref = $currentValue->{'$ref'};

                // $ref can be string only
                if (is_string($ref)) {
                    // Check for identification reference
                    if (property_exists($this->identificationStorage, $ref)) {
                        if ($parentType === 'object') {
                            $parent->{$parentKey} = &$this->identificationStorage->{$ref};
                        } elseif ($parentType === 'array') {
                            $parent[$parentKey] = &$this->identificationStorage->{$ref};
                        }

                        return;
                    }

                    // Check for url reference
                    if (substr($ref, 0, 7) === 'http://' || substr($ref, 0, 7) === 'http://') {
                        if ($parentType === 'object') {
                            $parent->{$parentKey} = $this->downloadJsonResource($ref);
                        } elseif ($parentType === 'array') {
                            $parent[$parentKey] = $this->downloadJsonResource($ref);
                        }

                        // Check it again
                        $this->registerReferences($parent, $parentKey, $path);

                        return;
                    }

                    // Create blank reference if the $ref tag is appear for 1st time
                    if (!property_exists($this->referenceStorage, $ref)) {
                        $this->referenceStorage->{$ref} = (object)[];
                    }

                    // Assign current value to the reference
                    if ($parentType === 'object') {
                        $parent->{$parentKey} = &$this->referenceStorage->{$ref};
                    } elseif ($parentType === 'array') {
                        $parent[$parentKey] = &$this->referenceStorage->{$ref};
                    }

                    // Assign current reference to the current path
                    $this->pathReferences->{$path} = &$this->referenceStorage->{$ref};
                }

                // If there is a $ref we will not going deep into the object
                return;
            }
        }

        // Go deep
        if (in_array($currentType, ['array', 'object'])) {
            foreach ($currentValue as $walkKey => $walkValue) {
                if ($parentType === 'object') {
                    $this->registerReferences($parent->{$parentKey}, $walkKey, $path . '/' . $walkKey);
                } elseif ($parentType === 'array') {
                    $this->registerReferences($parent[$parentKey], $walkKey, $path . '/' . $walkKey);
                }
            }
        }
    }

    /**
     * Assign references values
     * @param object $object
     * @throws RefException
     */
    protected function buildReferences(object $object): void
    {
        // Get the waiting references
        foreach ($this->referenceStorage as $refPath => $value) {
            // If the root definitions is requested
            if ($refPath === '#') {
                $this->referenceStorage->{$refPath} = $this->rootObject;
                return;
            }

            // Check for min path requirements
            if (strlen($refPath) < 2 && substr($refPath, 0, 2) !== '#/') {
                continue;
            }

            // Prepare path parts by skipping 1-st two symbols
            $pathParts = explode('/', substr($refPath, 2));

            // From which storage will be get the data
            if ($pathParts[0] === 'definitions') {
                $pathObject = (object)['definitions' => $this->definitions];
            } else {
                $pathObject = $object;
            }

            // Go part by part to get to the end point data
            foreach ($pathParts as $path) {
                // Fix special symbols
                $path = str_replace(['~0', '~1', '%25', '%22'], ['~', '/', '%', '"'], $path);

                // Get current object type & check how to extend it
                if (is_object($pathObject)) {
                    if (!property_exists($pathObject, $path)) {
                        throw new RefException(sprintf(
                            '$ref not found (%s)',
                            $refPath
                        ));
                    }

                    $pathObject = $pathObject->{$path};
                } elseif (is_array($pathObject)) {
                    if (!array_key_exists($path, $pathObject)) {
                        throw new RefException(sprintf(
                            '$ref not found (%s)',
                            $refPath
                        ));
                    }

                    $pathObject = &$pathObject[$path];
                }
            }

            // Assign final path value
            $this->referenceStorage->{$refPath} = $pathObject;
        }
    }

    /**
     * Download external JSON files with curl
     * @param string $url
     * @return mixed
     * @throws RefException
     */
    protected function downloadJsonResource(string $url)
    {
        // Check is already downloaded
        if (!isset($this->urlCache->{$url})) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, self::CURL_TIMEOUT);
            $data = curl_exec($ch);
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            // If there is an download error
            if ($curlErrno > 0) {
                throw new RefException(sprintf(
                    'External reference download problem: "%s" (%s)',
                    $curlError,
                    $url
                ));
            }

            // Get the json and check for errors
            $json = json_decode($data);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RefException(sprintf(
                    'Invalid json response for url "%s"',
                    $url
                ));
            }

            // Register the json response to the cache
            $this->urlCache->{$url} = $json;
        }

        // Return the data from the cache
        return $this->urlCache->{$url};
    }
}
