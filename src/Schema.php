<?php

namespace FrontLayer\JsonSchema;

class Schema
{
    protected $storage;

    protected $formatsMap = null;

    protected $mainType = null;

    /**
     * Schema constructor.
     * @param object $schema
     * @param object|null $formatsMap
     * @throws SchemaException
     */
    public function __construct(object $schema, ?object $formatsMap = null)
    {
        $this->storage = $schema;
        $this->formatsMap = $formatsMap;

        // Set 1st level of _path
        if (!property_exists($this->storage, '_path')) {
            $this->storage->_path = 'schema::/';
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
     * @return object
     */
    public function storage(): object
    {
        return $this->storage;
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
                    $this->storage->_path . '/type'
                ));
            }

            // Check for unknown types
            if (!in_array($type, ['string', 'number', 'integer', 'array', 'object', 'boolean'])) {
                throw new SchemaException(sprintf(
                    'Unknown type "%s" (%s)',
                    $type,
                    $this->storage->_path . '/type'
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
                        $this->storage->_path . '/type'
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
                $this->storage->_path . '/format'
            ));
        }

        // Check for undefined format
        if (!property_exists($this->formatsMap, $this->storage->format)) {
            throw new SchemaException(sprintf(
                'Unknown format "%s" (%s)',
                $this->storage->format,
                $this->storage->_path . '/format'
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
                    $this->storage->_path . '/type'
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
                $this->storage->_path . '/minLength'
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
                $this->storage->_path . '/maxLength'
            ));
        }

        // Check is maxLength lower than minLength
        if (property_exists($this->storage, 'minLength')) {
            if ($this->storage->maxLength < $this->storage->minLength) {
                throw new SchemaException(sprintf(
                    'You have "maxLength" with value "%d" which is lower than "minLength" with value "%d" (%s)',
                    $this->storage->maxLength,
                    $this->storage->minLength,
                    $this->storage->_path . '/maxLength'
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
                $this->storage->_path . '/pattern'
            ));
        }

        // Check for valid property value
        if (!Check::regex($this->storage->pattern)) {
            throw new SchemaException(sprintf(
                'You have "pattern" which is not valid regex (%s)',
                $this->storage->_path . '/pattern'
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
                $this->storage->_path . '/contentMediaType'
            ));
        }

        // Check for valid property value
        if (strstr($this->storage->contentMediaType, '/') === false) {
            throw new SchemaException(sprintf(
                'You have "contentMediaType" which is not well formatted. Slash "/" is missing (%s)',
                $this->storage->_path . '/contentMediaType'
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
                $this->storage->_path . '/contentEncoding'
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
                $this->storage->_path . '/multipleOf'
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
                $this->storage->_path . '/minimum'
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
                $this->storage->_path . '/exclusiveMinimum'
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
                    $this->storage->_path . '/exclusiveMinimum'
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
                $this->storage->_path . '/maximum'
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
                    $this->storage->_path . '/maximum'
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
                $this->storage->_path . '/exclusiveMaximum'
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
                    $this->storage->_path . '/exclusiveMaximum'
                ));
            }

            // Check is exclusiveMaximum equal to exclusiveMinimum
            if ($this->storage->exclusiveMaximum == $this->storage->exclusiveMinimum) {
                throw new SchemaException(sprintf(
                    'You have "exclusiveMaximum" with value "%d" which is equal to "exclusiveMinimum" with value "%d" (%s)',
                    $this->storage->exclusiveMaximum,
                    $this->storage->exclusiveMinimum,
                    $this->storage->_path . '/exclusiveMaximum'
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
                $this->storage->_path . '/properties'
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

        // Check for valid property type
        if (!is_bool($this->storage->additionalProperties) && !is_object($this->storage->additionalProperties)) {
            throw new SchemaException(sprintf(
                'You have "additionalProperties" which value is not a "boolean" or "object" but it is "%s" (%s)',
                gettype($this->storage->additionalProperties),
                $this->storage->_path . '/additionalProperties'
            ));
        }

        // Create sub-schema object
        if (is_object($this->storage->additionalProperties)) {
            $this->storage->additionalProperties->_path = $this->storage->_path . '/additionalProperties';
            $this->storage->additionalProperties = new Schema($this->storage->additionalProperties, $this->formatsMap);
        }
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
                $this->storage->_path . '/required'
            ));
        }

        // Check that each item is with proper type
        foreach ($this->storage->required as $required) {
            if (!is_string($required)) {
                throw new SchemaException(sprintf(
                    'You have defined required property which is not a string value (%s)',
                    $this->storage->_path . '/required'
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

        // Check for valid property type
        if (!is_object($this->storage->propertyNames)) {
            throw new SchemaException(sprintf(
                'You have "propertyNames" which value is not an "object" but it is "%s" (%s)',
                gettype($this->storage->propertyNames),
                $this->storage->_path . '/propertyNames'
            ));
        }

        // Create sub-schema object
        $this->storage->propertyNames->_path = $this->storage->_path . '/propertyNames';
        $this->storage->propertyNames = new Schema($this->storage->propertyNames, $this->formatsMap);
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
                $this->storage->_path . '/minProperties'
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
                $this->storage->_path . '/maxProperties'
            ));
        }

        // Check is maxProperties lower than minProperties
        if (property_exists($this->storage, 'minProperties')) {
            if ($this->storage->maxProperties < $this->storage->minProperties) {
                throw new SchemaException(sprintf(
                    'You have "maxProperties" with value "%d" which is lower than "minProperties" with value "%d" (%s)',
                    $this->storage->maxProperties,
                    $this->storage->minProperties,
                    $this->storage->_path . '/maxProperties'
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
                $this->storage->_path . '/dependencies'
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
                            $this->storage->_path . '/dependencies/[' . $sKey . ']'
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
            if (is_object($schema)) {
                $schema->_path = $this->storage->_path . '/dependencies/' . $dKey;
                $this->storage->dependencies->{$dKey} = new Schema((object)$schema, $this->formatsMap);
            }
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
                $this->storage->_path . '/patternProperties'
            ));
        }

        // Check the structure of patternProperties
        foreach ($this->storage->patternProperties as $keyPattern => $schema) {
            // Check key pattern
            if (!Check::regex($keyPattern)) {
                throw new SchemaException(sprintf(
                    'You have "patternProperties" with key "%s" which is not valid regex pattern (%s)',
                    $keyPattern,
                    $this->storage->_path . '/patternProperties/' . $keyPattern
                ));
            }

            // Check for valid property schema type
            if (!is_object($schema)) {
                throw new SchemaException(sprintf(
                    'You have "patternProperties" with key "%s" which value is not an "object" but it is "%s" (%s)',
                    gettype($this->storage->patternProperties),
                    gettype($schema),
                    $this->storage->_path . '/patternProperties/' . $keyPattern
                ));
            }

            // Set value to Schema
            $schema->_path = $this->storage->_path . '/patternProperties/' . $keyPattern;
            $this->storage->patternProperties->{$keyPattern} = new Schema($schema, $this->formatsMap);
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
        if (!is_array($this->storage->items) && !is_object($this->storage->items)) {
            throw new SchemaException(sprintf(
                'You have "items" which value is not a "array" or "object" but it is "%s" (%s)',
                gettype($this->storage->items),
                $this->storage->_path . '/items'
            ));
        }

        // Validate multiple item schema
        if (is_array($this->storage->items)) {
            foreach ($this->storage->items as $key => $schema) {
                // Check for valid property type
                if (!is_object($schema)) {
                    throw new SchemaException(sprintf(
                        'You have "item" which value is not a "object" but it is "%s" (%s)',
                        gettype($this->storage->items),
                        $this->storage->_path . '/items/[' . $key . ']'
                    ));
                }

                // Transform to schema
                $this->storage->items[$key]->_path = $this->storage->_path . '/items[' . $key . ']';
                $this->storage->items[$key] = new Schema($this->storage->items[$key], $this->formatsMap);
            }
        }

        // Validate single item schema
        if (is_object($this->storage->items)) {
            $this->storage->items->_path = $this->storage->_path . '/items';
            $this->storage->items = new Schema($this->storage->items, $this->formatsMap);
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

        // Check for valid property type
        if (!is_object($this->storage->contains)) {
            throw new SchemaException(sprintf(
                'You have "contains" which value is not a "object" but it is "%s" (%s)',
                gettype($this->storage->contains),
                $this->storage->_path . '/contains'
            ));
        }

        // Transform to schema
        $this->storage->contains->_path = $this->storage->_path . '/contains';
        $this->storage->contains = new Schema($this->storage->contains, $this->formatsMap);
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

        // Check for valid property type
        if (!is_bool($this->storage->additionalItems) && !is_object($this->storage->additionalItems)) {
            throw new SchemaException(sprintf(
                'You have "additionalItems" which value is not a "boolean" or "object" but it is "%s" (%s)',
                gettype($this->storage->additionalItems),
                $this->storage->_path . '/additionalItems'
            ));
        }

        // Create sub-schema object
        if (is_object($this->storage->additionalItems)) {
            $this->storage->additionalItems->_path = $this->storage->_path . '/additionalItems';
            $this->storage->additionalItems = new Schema($this->storage->additionalItems, $this->formatsMap);
        }
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
                $this->storage->_path . '/minItems'
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
                $this->storage->_path . '/maxItems'
            ));
        }

        // Check is maxItems lower than minItems
        if (property_exists($this->storage, 'minItems')) {
            if ($this->storage->maxItems < $this->storage->minItems) {
                throw new SchemaException(sprintf(
                    'You have "maxItems" with value "%d" which is lower than "minItems" with value "%d" (%s)',
                    $this->storage->maxItems,
                    $this->storage->minItems,
                    $this->storage->_path . '/maxItems'
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
                $this->storage->_path . '/uniqueItems'
            ));
        }
    }
}
