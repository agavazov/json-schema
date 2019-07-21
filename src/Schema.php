<?php

namespace FrontLayer\JsonSchema;

class Schema
{
    protected $storage;

    protected $formatsMap = null;

    protected $path;

    protected $mainType = null;

    /**
     * Schema constructor.
     * @param object|bool $schema
     * @param object|null $formatsMap
     * @param string|null $path
     * @throws SchemaException
     */
    public function __construct($schema, ?object $formatsMap = null, string $path = null)
    {
        $this->storage = $schema;
        $this->formatsMap = $formatsMap;

        // Set path
        $this->path = $path ?: 'schema::/';

        // Check for valid property type
        if (!is_object($schema) && !is_bool($schema)) {
            throw new SchemaException(sprintf(
                'You have "schema" which value is not a "object" or "boolean" but it is "%s" (%s)',
                gettype($schema),
                $this->path
            ));
        }

        // Check for valid property type
        if (is_bool($schema)) {
            return; // @todo - make it work with boolean schema
        }

        // Check each attribute
        $this->processType();
        $this->processFormat();
        $this->processIf();
        $this->processThen();
        $this->processElse();
        $this->processConst();
        $this->processEnum();
        $this->processAllOf();
        $this->processAnyOf();
        $this->processOneOf();
        $this->processNot();
        $this->processMinLength();
        $this->processMaxLength();
        $this->processPattern();
        $this->processContentMediaType();
        $this->processContentEncoding();
        $this->processMultipleOf();
        $this->processMinimum();
        $this->processExclusiveMinimum();
        $this->processMaximum();
        $this->processExclusiveMaximum();
        $this->processProperties();
        $this->processAdditionalProperties();
        $this->processRequired();
        $this->processPropertyNames();
        $this->processMinProperties();
        $this->processMaxProperties();
        $this->processDependencies();
        $this->processPatternProperties();
        $this->processItems();
        $this->processContains();
        $this->processAdditionalItems();
        $this->processMinItems();
        $this->processMaxItems();
        $this->processUniqueItems();
    }

    /**
     * Return current schema structure
     * @return object|bool
     */
    public function storage()
    {
        return $this->storage;
    }

    /**
     * Return current schema path
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Type property is array. After data is analyzed the we will set main type which matches one of the property types
     * @param string $type
     */
    public function setMainType(string $type): void
    {
        $this->mainType = $type;
    }

    /**
     * Return matched type
     * @return string|null
     */
    public function getMainType(): ?string
    {
        return $this->mainType;
    }

    /**
     * Check type property for appropriate format and for mismatch between properties and types
     * @throws SchemaException
     */
    protected function processType(): void
    {
        // Register type property
        if (!property_exists($this->storage, 'type')) {
            $this->storage->type = [];
        }

        // Make schema type to be array by default
        if (!is_array($this->storage->type)) {
            $this->storage->type = [$this->storage->type];
        }

        // If the schema type is null, then only null will be allowed as data
        if (in_array(null, $this->storage->type, true)) {
            $this->storage->const = null;
        }

        // Check that each item is with proper type
        foreach ($this->storage->type as $key => $type) {
            // Check is it a string
            if (!is_string($type)) {
                throw new SchemaException(sprintf(
                    'You have defined type which is not a string value (%s)',
                    $this->getPath() . '/type'
                ));
            }

            // Check for unknown types
            if (!in_array($type, ['string', 'number', 'integer', 'array', 'object', 'boolean', 'null'])) {
                throw new SchemaException(sprintf(
                    'Unknown type "%s" (%s)',
                    $type,
                    $this->getPath() . '/type'
                ));
            }
        }

        // Check for mismatch between properties and types
        $propertiesMap = (object)[
            'minLength' => ['string'],
            'maxLength' => ['string'],
            'pattern' => ['string'],
            'contentMediaType' => ['string'],
            'contentEncoding' => ['string'],
            'multipleOf' => ['number', 'integer'],
            'minimum' => ['number', 'integer'],
            'exclusiveMinimum' => ['number', 'integer'],
            'maximum' => ['number', 'integer'],
            'exclusiveMaximum' => ['number', 'integer'],
            'properties' => ['object'],
            'additionalProperties' => ['object'],
            'required' => ['object'],
            'propertyNames' => ['object'],
            'minProperties' => ['object'],
            'maxProperties' => ['object'],
            'dependencies' => ['object'],
            'patternProperties' => ['object'],
            'items' => ['array'],
            'contains' => ['array'],
            'additionalItems' => ['array'],
            'minItems' => ['array'],
            'maxItems' => ['array'],
            'uniqueItems' => ['array'],
        ];
        foreach ($propertiesMap as $property => $expectedTypes) {
            if (property_exists($this->storage, $property)) {
                $typesCount = count($this->storage->type);

                // When there is no any type then we will assign 1st match
                if ($typesCount === 0) {
                    $this->storage->type[] = $expectedTypes[0];
                    continue;
                }

                // Check for mismatches - when type/s are different than $expectedTypes
                $matches = 0;

                foreach ($expectedTypes as $expectedType) {
                    if (in_array($expectedType, $this->storage->type)) {
                        $matches++;
                    }
                }

                if ($typesCount > $matches) {
                    throw new SchemaException(sprintf(
                        'The property "%s" is exclusive for type "%s" but there is another type/s "%s" (%s)',
                        $property,
                        implode('; ', $expectedTypes),
                        implode('; ', $this->storage->type),
                        $this->getPath() . '/type'
                    ));
                }
            }
        }
    }

    /**
     * Check format property and assign type to the schema if need
     * @throws SchemaException
     */
    protected function processFormat(): void
    {
        if (!property_exists($this->storage, 'format') || $this->formatsMap === null || count((array)$this->formatsMap) === 0) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->storage->format)) {
            throw new SchemaException(sprintf(
                'You have "format" which value is not an string but it is "%s" (%s)',
                gettype($this->storage->format),
                $this->getPath() . '/format'
            ));
        }

        // Check for undefined format
        if (!property_exists($this->formatsMap, $this->storage->format)) {
            throw new SchemaException(sprintf(
                'Unknown format "%s" (%s)',
                $this->storage->format,
                $this->getPath() . '/format'
            ));
        }

        // Check for mismatch between formats and types
        $expectedType = $this->formatsMap->{$this->storage->format};
        $typesCount = count($this->storage->type);

        if ($typesCount === 0) {
            $this->storage->type[] = $expectedType;
        } else {
            if ($typesCount !== 1 || !in_array($expectedType, $this->storage->type)) {
                throw new SchemaException(sprintf(
                    'The format "%s" is exclusive for type "%s" but there is another type/s "%s" (%s)',
                    $this->storage->format,
                    $expectedType,
                    implode('; ', $this->storage->type),
                    $this->getPath() . '/type'
                ));
            }
        }
    }

    /**
     * @todo
     */
    protected function processIf(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'if')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * @todo
     */
    protected function processThen(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'then')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * @todo
     */
    protected function processElse(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'else')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * @todo
     */
    protected function processConst(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'const')) {
            return;
        }

        // Check for valid property type
        // @todo
        // @todo - reassign schema to enum [:const]
    }

    /**
     * @todo
     */
    protected function processEnum(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'enum')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * @todo
     */
    protected function processAllOf(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'allOf')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * @todo
     */
    protected function processAnyOf(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'anyOf')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * @todo
     */
    protected function processOneOf(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'oneOf')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * @todo
     */
    protected function processNot(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'not')) {
            return;
        }

        // Check for valid property type
        // @todo
    }

    /**
     * Check minLength property
     * @throws SchemaException
     */
    protected function processMinLength(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'minLength')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->storage->minLength)) {
            throw new SchemaException(sprintf(
                'You have "minLength" which value is not an integer but it is "%s" (%s)',
                gettype($this->storage->minLength),
                $this->getPath() . '/minLength'
            ));
        }
    }

    /**
     * Check maxLength property
     * @throws SchemaException
     */
    protected function processMaxLength(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'maxLength')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->storage->maxLength)) {
            throw new SchemaException(sprintf(
                'You have "maxLength" which value is not an integer but it is "%s" (%s)',
                gettype($this->storage->maxLength),
                $this->getPath() . '/maxLength'
            ));
        }

        // Check is maxLength lower than minLength
        if (property_exists($this->storage, 'minLength')) {
            if ($this->storage->maxLength < $this->storage->minLength) {
                throw new SchemaException(sprintf(
                    'You have "maxLength" with value "%d" which is lower than "minLength" with value "%d" (%s)',
                    $this->storage->maxLength,
                    $this->storage->minLength,
                    $this->getPath() . '/maxLength'
                ));
            }
        }
    }

    /**
     * Check pattern property
     * @throws SchemaException
     */
    protected function processPattern(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'pattern')) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->storage->pattern)) {
            throw new SchemaException(sprintf(
                'You have "pattern" which value is not a "string" but it is "%s" (%s)',
                gettype($this->storage->pattern),
                $this->getPath() . '/pattern'
            ));
        }

        // Check for valid property value
        if (!Check::regex($this->storage->pattern)) {
            throw new SchemaException(sprintf(
                'You have "pattern" which is not valid regex (%s)',
                $this->getPath() . '/pattern'
            ));
        }
    }

    /**
     * Check contentMediaType property
     * @throws SchemaException
     */
    protected function processContentMediaType(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'contentMediaType')) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->storage->contentMediaType)) {
            throw new SchemaException(sprintf(
                'You have "contentMediaType" which value is not a "string" but it is "%s" (%s)',
                gettype($this->storage->contentMediaType),
                $this->getPath() . '/contentMediaType'
            ));
        }

        // Check for valid property value
        if (strstr($this->storage->contentMediaType, '/') === false) {
            throw new SchemaException(sprintf(
                'You have "contentMediaType" which is not well formatted. Slash "/" is missing (%s)',
                $this->getPath() . '/contentMediaType'
            ));
        }
    }

    /**
     * Check contentEncoding property
     * @throws SchemaException
     */
    protected function processContentEncoding(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'contentEncoding')) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->storage->contentEncoding)) {
            throw new SchemaException(sprintf(
                'You have "contentEncoding" which value is not a "string" but it is "%s" (%s)',
                gettype($this->storage->contentEncoding),
                $this->getPath() . '/contentEncoding'
            ));
        }
    }

    /**
     * Check multipleOf property
     * @throws SchemaException
     */
    protected function processMultipleOf(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'multipleOf')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->storage->multipleOf) && !is_integer($this->storage->multipleOf)) {
            throw new SchemaException(sprintf(
                'You have "multipleOf" which value is not a "numeric" but it is "%s" (%s)',
                gettype($this->storage->multipleOf),
                $this->getPath() . '/multipleOf'
            ));
        }
    }

    /**
     * Check minimum property
     * @throws SchemaException
     */
    protected function processMinimum(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'minimum')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->storage->minimum) && !is_integer($this->storage->minimum)) {
            throw new SchemaException(sprintf(
                'You have "minimum" which value is not a "number/integer" but it is "%s" (%s)',
                gettype($this->storage->minimum),
                $this->getPath() . '/minimum'
            ));
        }
    }

    /**
     * Check exclusiveMinimum property
     * @throws SchemaException
     */
    protected function processExclusiveMinimum(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'exclusiveMinimum')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->storage->exclusiveMinimum) && !is_integer($this->storage->exclusiveMinimum)) {
            throw new SchemaException(sprintf(
                'You have "exclusiveMinimum" which value is not a "number/integer" but it is "%s" (%s)',
                gettype($this->storage->exclusiveMinimum),
                $this->getPath() . '/exclusiveMinimum'
            ));
        }

        // minimum checks
        if (property_exists($this->storage, 'minimum')) {
            // Check is exclusiveMinimum lower than minimum
            if ($this->storage->exclusiveMinimum < $this->storage->minimum) {
                throw new SchemaException(sprintf(
                    'You have "exclusiveMinimum" with value "%d" which is lower than "minimum" with value "%d" (%s)',
                    $this->storage->exclusiveMinimum,
                    $this->storage->minimum,
                    $this->getPath() . '/exclusiveMinimum'
                ));
            }
        }
    }

    /**
     * Check maximum property
     * @throws SchemaException
     */
    protected function processMaximum(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'maximum')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->storage->maximum) && !is_integer($this->storage->maximum)) {
            throw new SchemaException(sprintf(
                'You have "maximum" which value is not a "number/integer" but it is "%s" (%s)',
                gettype($this->storage->maximum),
                $this->getPath() . '/maximum'
            ));
        }

        // minimum checks
        if (property_exists($this->storage, 'minimum')) {
            // Check is maximum lower than minimum
            if ($this->storage->maximum < $this->storage->minimum) {
                throw new SchemaException(sprintf(
                    'You have "maximum" with value "%d" which is lower than "minimum" with value "%d" (%s)',
                    $this->storage->maximum,
                    $this->storage->minimum,
                    $this->getPath() . '/maximum'
                ));
            }
        }
    }

    /**
     * Check exclusiveMaximum property
     * @throws SchemaException
     */
    protected function processExclusiveMaximum(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'exclusiveMaximum')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->storage->exclusiveMaximum) && !is_integer($this->storage->exclusiveMaximum)) {
            throw new SchemaException(sprintf(
                'You have "exclusiveMaximum" which value is not a "number/integer" but it is "%s" (%s)',
                gettype($this->storage->exclusiveMaximum),
                $this->getPath() . '/exclusiveMaximum'
            ));
        }

        // exclusiveMinimum checks
        if (property_exists($this->storage, 'exclusiveMinimum')) {
            // Check is exclusiveMaximum lower than exclusiveMinimum
            if ($this->storage->exclusiveMaximum < $this->storage->exclusiveMinimum) {
                throw new SchemaException(sprintf(
                    'You have "exclusiveMaximum" with value "%d" which is lower than "exclusiveMinimum" with value "%d" (%s)',
                    $this->storage->exclusiveMaximum,
                    $this->storage->exclusiveMinimum,
                    $this->getPath() . '/exclusiveMaximum'
                ));
            }

            // Check is exclusiveMaximum equal to exclusiveMinimum
            if ($this->storage->exclusiveMaximum == $this->storage->exclusiveMinimum) {
                throw new SchemaException(sprintf(
                    'You have "exclusiveMaximum" with value "%d" which is equal to "exclusiveMinimum" with value "%d" (%s)',
                    $this->storage->exclusiveMaximum,
                    $this->storage->exclusiveMinimum,
                    $this->getPath() . '/exclusiveMaximum'
                ));
            }
        }
    }

    /**
     * Check properties property
     * @throws SchemaException
     */
    protected function processProperties(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'properties')) {
            return;
        }

        // Check for valid property type
        if (!is_object($this->storage->properties)) {
            throw new SchemaException(sprintf(
                'You have "properties" which value is not a "object" but it is "%s" (%s)',
                gettype($this->storage->properties),
                $this->getPath() . '/properties'
            ));
        }
    }

    /**
     * Check additionalProperties property
     * @throws SchemaException
     */
    protected function processAdditionalProperties(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'additionalProperties')) {
            return;
        }

        // Create sub-schema object
        $newPath = $this->getPath() . '/additionalProperties';
        $this->storage->additionalProperties = new Schema($this->storage->additionalProperties, $this->formatsMap, $newPath);
    }

    /**
     * Check required property
     * @throws SchemaException
     */
    protected function processRequired(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'required')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->storage->required)) {
            throw new SchemaException(sprintf(
                'You have "required" which value is not a "array" but it is "%s" (%s)',
                gettype($this->storage->required),
                $this->getPath() . '/required'
            ));
        }

        // Check that each item is with proper type
        foreach ($this->storage->required as $required) {
            if (!is_string($required)) {
                throw new SchemaException(sprintf(
                    'You have defined required property which is not a string value (%s)',
                    $this->getPath() . '/required'
                ));
            }
        }
    }

    /**
     * Check propertyNames property
     * @throws SchemaException
     */
    protected function processPropertyNames(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'propertyNames')) {
            return;
        }

        // Create sub-schema object
        $newPath = $this->getPath() . '/propertyNames';
        $this->storage->propertyNames = new Schema($this->storage->propertyNames, $this->formatsMap, $newPath);
    }

    /**
     * Check minProperties property
     * @throws SchemaException
     */
    protected function processMinProperties(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'minProperties')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->storage->minProperties)) {
            throw new SchemaException(sprintf(
                'You have "minProperties" which value is not a "integer" but it is "%s" (%s)',
                gettype($this->storage->minProperties),
                $this->getPath() . '/minProperties'
            ));
        }
    }

    /**
     * Check maxProperties property
     * @throws SchemaException
     */
    protected function processMaxProperties(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'maxProperties')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->storage->maxProperties)) {
            throw new SchemaException(sprintf(
                'You have "maxProperties" which value is not a "integer" but it is "%s" (%s)',
                gettype($this->storage->maxProperties),
                $this->getPath() . '/maxProperties'
            ));
        }

        // Check is maxProperties lower than minProperties
        if (property_exists($this->storage, 'minProperties')) {
            if ($this->storage->maxProperties < $this->storage->minProperties) {
                throw new SchemaException(sprintf(
                    'You have "maxProperties" with value "%d" which is lower than "minProperties" with value "%d" (%s)',
                    $this->storage->maxProperties,
                    $this->storage->minProperties,
                    $this->getPath() . '/maxProperties'
                ));
            }
        }
    }

    /**
     * Check dependencies property
     * @throws SchemaException
     */
    protected function processDependencies(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'dependencies')) {
            return;
        }

        // Check for valid property type
        if (!is_object($this->storage->dependencies)) {
            throw new SchemaException(sprintf(
                'You have "dependencies" which value is not an "object" but it is "%s" (%s)',
                gettype($this->storage->dependencies),
                $this->getPath() . '/dependencies'
            ));
        }

        // Check the schema
        foreach ($this->storage->dependencies as $dKey => $schema) {
            // If schema is array
            if (is_array($schema)) {
                // Check that each item is a string
                foreach ($schema as $sKey => $item) {
                    if (!is_string($item)) {
                        throw new SchemaException(sprintf(
                            'You have defined dependency item which is not a string value (%s)',
                            $this->getPath() . '/dependencies/[' . $sKey . ']'
                        ));
                    }
                }

                // Normalize to schema
                $this->storage->dependencies->{$dKey} = (object)[
                    'type' => 'object',
                    'additionalProperties' => true,
                    'required' => $schema
                ];
            }

            // If schema is object
            $newPath = $this->getPath() . '/dependencies/' . $dKey;
            $this->storage->dependencies->{$dKey} = new Schema($this->storage->dependencies->{$dKey}, $this->formatsMap, $newPath);
        }
    }

    /**
     * Check patternProperties property
     * @throws SchemaException
     */
    protected function processPatternProperties(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'patternProperties')) {
            return;
        }

        // Check for valid property type
        if (!is_object($this->storage->patternProperties)) {
            throw new SchemaException(sprintf(
                'You have "patternProperties" which value is not an "object" but it is "%s" (%s)',
                gettype($this->storage->patternProperties),
                $this->getPath() . '/patternProperties'
            ));
        }

        // Check the structure of patternProperties
        foreach ($this->storage->patternProperties as $keyPattern => $schema) {
            // Check key pattern
            if (!Check::regex($keyPattern)) {
                throw new SchemaException(sprintf(
                    'You have "patternProperties" with key "%s" which is not valid regex pattern (%s)',
                    $keyPattern,
                    $this->getPath() . '/patternProperties/' . $keyPattern
                ));
            }

            // Set value to Schema
            $newPath = $this->getPath() . '/patternProperties/' . $keyPattern;
            $this->storage->patternProperties->{$keyPattern} = new Schema($schema, $this->formatsMap, $newPath);
        }
    }

    /**
     * Check items property
     * @throws SchemaException
     */
    protected function processItems(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'items')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->storage->items) && !is_object($this->storage->items) && !is_bool($this->storage->items)) {
            throw new SchemaException(sprintf(
                'You have "items" which value is not a "array", "object" or "boolean" but it is "%s" (%s)',
                gettype($this->storage->items),
                $this->getPath() . '/items'
            ));
        }

        // Validate multiple item schema
        if (is_array($this->storage->items)) {
            foreach ($this->storage->items as $key => $schema) {
                // Transform to schema
                $newPath = $this->getPath() . '/items[' . $key . ']';
                $this->storage->items[$key] = new Schema($schema, $this->formatsMap, $newPath);
            }
        }

        // Validate single item schema
        if (is_object($this->storage->items) || is_bool($this->storage->items)) {
            $newPath = $this->getPath() . '/items';
            $this->storage->items = new Schema($this->storage->items, $this->formatsMap, $newPath);
        }
    }

    /**
     * Check contains property
     * @throws SchemaException
     */
    protected function processContains(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'contains')) {
            return;
        }

        // Transform to schema
        $newPath = $this->getPath() . '/contains';
        $this->storage->contains = new Schema($this->storage->contains, $this->formatsMap, $newPath);
    }

    /**
     * Check additionalItems property
     * @throws SchemaException
     */
    protected function processAdditionalItems(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'additionalItems')) {
            return;
        }

        // Create sub-schema object
        $newPath = $this->getPath() . '/additionalItems';
        $this->storage->additionalItems = new Schema($this->storage->additionalItems, $this->formatsMap, $newPath);
    }

    /**
     * Check minItems property
     * @throws SchemaException
     */
    protected function processMinItems(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'minItems')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->storage->minItems)) {
            throw new SchemaException(sprintf(
                'You have "minItems" which value is not an integer but it is "%s" (%s)',
                gettype($this->storage->minItems),
                $this->getPath() . '/minItems'
            ));
        }
    }

    /**
     * Check maxItems property
     * @throws SchemaException
     */
    protected function processMaxItems(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'maxItems')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->storage->maxItems)) {
            throw new SchemaException(sprintf(
                'You have "maxItems" which value is not an integer but it is "%s" (%s)',
                gettype($this->storage->maxItems),
                $this->getPath() . '/maxItems'
            ));
        }

        // Check is maxItems lower than minItems
        if (property_exists($this->storage, 'minItems')) {
            if ($this->storage->maxItems < $this->storage->minItems) {
                throw new SchemaException(sprintf(
                    'You have "maxItems" with value "%d" which is lower than "minItems" with value "%d" (%s)',
                    $this->storage->maxItems,
                    $this->storage->minItems,
                    $this->getPath() . '/maxItems'
                ));
            }
        }
    }

    /**
     * Check uniqueItems property
     * @throws SchemaException
     */
    protected function processUniqueItems(): void
    {
        // Check exists
        if (!property_exists($this->storage, 'uniqueItems')) {
            return;
        }

        // Check for valid property type
        if (!is_bool($this->storage->uniqueItems)) {
            throw new SchemaException(sprintf(
                'You have "uniqueItems" which value is not a "boolean" but it is "%s" (%s)',
                gettype($this->storage->uniqueItems),
                $this->getPath() . '/uniqueItems'
            ));
        }
    }
}
