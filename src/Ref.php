<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Ref
{
    /**
     * External $url timeout ms.
     */
    const CURL_TIMEOUT = 1000;

    protected $referenceStorage;
    protected $identificationStorage;
    protected $pathRegistry;

    protected $rootObject;

    protected $definitions;
    protected $urlCache;

    /**
     * Ref constructor
     * @param object|bool $object
     * @throws RefException
     */
    public function __construct($object)
    {
        if (!is_object($object)) {
            return;
        }

        $this->referenceStorage = (object)[];
        $this->identificationStorage = (object)[];

        $this->rootObject = $object;

        $this->urlCache = (object)[];
        $this->pathRegistry = (object)[];

        $this->registerIdentifications($this, 'rootObject');

        $this->registerReferences($this, 'rootObject', '#');

        if (property_exists($this->rootObject, 'definitions')) {
            $this->definitions = $this->rootObject->definitions;
            unset($this->rootObject->definitions);
        }

        $this->buildReferences($this->rootObject);

        //unset($this->referenceStorage); // @todo to uncomment it when everithing is done
    }

    protected function registerIdentifications(&$parent, $parentKey): void
    {
        $parentType = gettype($parent);
        $currentValue = $parentType === 'object' ? $parent->{$parentKey} : $parent[$parentKey];
        $currentType = gettype($currentValue);

        if ($currentType === 'object') {
            // If the object has $id then it will be register to the idStorage
            if (property_exists($currentValue, '$id')) {
                if (is_string($currentValue->{'$id'})) {
                    $this->identificationStorage->{$currentValue->{'$id'}} = $parent->{$parentKey};
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

    protected function registerReferences(&$parent, $parentKey, $path): void
    {
        $parentType = gettype($parent);
        $currentValue = $parentType === 'object' ? $parent->{$parentKey} : $parent[$parentKey];
        $currentType = gettype($currentValue);

        if ($currentType === 'object') {
            // Transform reference to global references location
            if (property_exists($currentValue, '$ref')) {
                $ref = $currentValue->{'$ref'};

                if (!is_string($ref)) {
                    return;
                }

                // Check for id reference
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

                if (!property_exists($this->referenceStorage, $ref)) {
                    $this->referenceStorage->{$ref} = (object)[];
                }

                if ($parentType === 'object') {
                    $parent->{$parentKey} = &$this->referenceStorage->{$ref};
                } elseif ($parentType === 'array') {
                    $parent[$parentKey] = &$this->referenceStorage->{$ref};
                }

                // ... regisster the pathhh
                $this->pathRegistry->{$path} = &$this->referenceStorage->{$ref};

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

    public function isReference(string $path): bool
    {
        return property_exists($this->pathRegistry, $path);
    }

    public function getReferenceFor(string $path): ?object
    {
        if ($this->isReference($path)) {
            return $this->pathRegistry->{$path};
        } else {
            return null;
        }
    }

    protected function downloadJsonResource(string $url)
    {
        // @todo make check if it downloads the same file each time (it will be download recursion)

        if (!isset($this->urlCache->{$url})) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, self::CURL_TIMEOUT);
            $data = curl_exec($ch);
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlErrno > 0) {
                throw new RefException(sprintf(
                    'External reference download problem: "%s"',
                    $curlError,
                    $url
                ));
            } else {
                $json = json_decode($data);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new SchemaException(sprintf(
                        'Invalid json response for url "%s"',
                        $url
                    ));
                }
            }

            $this->urlCache->{$url} = $json;
        }

        return $this->urlCache->{$url};
    }

    protected function buildReferences(object $object): void
    {
        foreach ($this->referenceStorage as $key => $value) {
            if ($key === '#') {
                $this->referenceStorage->{$key} = $this->rootObject;
                return;
            }

            // @todo add comment
            $pathParts = explode('/', substr($key, 2));

            if ($pathParts[0] === 'definitions') {
                $tmp = (object)['definitions' => $this->definitions];
            } else {
                $tmp = $object;
            }

            foreach ($pathParts as $path) {
                $path = str_replace(['~0', '~1', '%25', '%22'], ['~', '/', '%', '"'], $path);

                $childType = gettype($tmp);
                if ($childType === 'object') {
                    if (!property_exists($tmp, $path)) {
                        throw new RefException(sprintf(
                            '... $ref path not found (%s)',
                            $key
                        ));
                    }

                    $tmp = $tmp->{$path};
                } elseif ($childType === 'array') {
                    if (!array_key_exists($path, $tmp)) {
                        throw new RefException(sprintf(
                            '... $ref path not found (%s)',
                            $key
                        ));
                    }

                    $tmp = &$tmp[$path];
                }
            }

            $this->referenceStorage->{$key} = $tmp;
        }
    }
}
