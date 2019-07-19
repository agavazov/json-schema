<?php

namespace FrontLayer\JsonSchema;

class Schema
{
    protected $storage;

    protected $mainType = null;

    /**
     * Schema constructor.
     * @param object $schema
     * @param object|null $formatsMap
     * @throws SchemaException
     */
    public function __construct(object $schema, object $formatsMap = null)
    {
        $this->storage = $schema;

        // Set 1st level of _path
        if (!property_exists($this->storage, '_path')) {
            $this->storage->_path = '#';
        }

        // Check each attribute
        $this->processType();
        $this->processFormat($formatsMap);
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

        // Check that each item
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
                    'Unknown type %s (%s)',
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
     * @param object|null $formatsMap
     * @throws SchemaException
     */
    protected function processFormat(?object $formatsMap): void
    {
        if (!property_exists($this->storage, 'format') || $formatsMap === null || count((array)$formatsMap) === 0) {
            return;
        }

        // Check for valid property value
        if (!is_string($this->storage->format)) {
            throw new SchemaException(sprintf(
                'You have "format" which value is not an string but it is "%s" (%s)',
                gettype($this->storage->format),
                $this->storage->_path . '/format'
            ));
        }

        // Check for undefined format
        if (!property_exists($formatsMap, $this->storage->format)) {
            throw new SchemaException(sprintf(
                'Unknown format "%s" (%s)',
                $this->storage->format,
                $this->storage->_path . '/format'
            ));
        }

        // Check for mismatch between formats and types
        $expectedType = $formatsMap->{$this->storage->format};
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
        if (!property_exists($this->storage, 'if')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processThen(): void
    {
        if (!property_exists($this->storage, 'then')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processElse(): void
    {
        if (!property_exists($this->storage, 'else')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processConst(): void
    {
        if (!property_exists($this->storage, 'const')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processEnum(): void
    {
        if (!property_exists($this->storage, 'enum')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processAllOf(): void
    {
        if (!property_exists($this->storage, 'allOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processAnyOf(): void
    {
        if (!property_exists($this->storage, 'anyOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processOneOf(): void
    {
        if (!property_exists($this->storage, 'oneOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processNot(): void
    {
        if (!property_exists($this->storage, 'not')) {
            return;
        }

        // @todo
    }

    /**
     * Check minLength property
     * @throws SchemaException
     */
    protected function processMinLength(): void
    {
        if (!property_exists($this->storage, 'minLength')) {
            return;
        }

        // Check for valid property value
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
        if (!property_exists($this->storage, 'maxLength')) {
            return;
        }

        // Check for valid property value
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
        if (!property_exists($this->storage, 'pattern')) {
            return;
        }

        if (is_string($this->storage->pattern)) {
            throw new SchemaException(sprintf(
                'You have "pattern" which value is not a string but it is "%s" (%s)',
                gettype($this->storage->pattern),
                $this->storage->_path . '/pattern'
            ));
        }

        if (!Check::regex($this->storage->pattern)) {
            throw new SchemaException(sprintf(
                'You have "pattern" which is not valid regex (%s)',
                $this->storage->_path . '/pattern'
            ));
        }
    }

    /**
     * @todo
     */
    protected function processContentMediaType(): void
    {
        if (!property_exists($this->storage, 'contentMediaType')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processContentEncoding(): void
    {
        if (!property_exists($this->storage, 'contentEncoding')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processMultipleOf(): void
    {
        if (!property_exists($this->storage, 'multipleOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processMinimum(): void
    {
        if (!property_exists($this->storage, 'minimum')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processExclusiveMinimum(): void
    {
        if (!property_exists($this->storage, 'exclusiveMinimum')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processMaximum(): void
    {
        if (!property_exists($this->storage, 'maximum')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processExclusiveMaximum(): void
    {
        if (!property_exists($this->storage, 'exclusiveMaximum')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processProperties(): void
    {
        if (!property_exists($this->storage, 'properties')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processAdditionalProperties(): void
    {
        if (!property_exists($this->storage, 'additionalProperties')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processRequired(): void
    {
        if (!property_exists($this->storage, 'required')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processPropertyNames(): void
    {
        if (!property_exists($this->storage, 'propertyNames')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processMinProperties(): void
    {
        if (!property_exists($this->storage, 'minProperties')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processMaxProperties(): void
    {
        if (!property_exists($this->storage, 'maxProperties')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processDependencies(): void
    {
        if (!property_exists($this->storage, 'dependencies')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processPatternProperties(): void
    {
        if (!property_exists($this->storage, 'patternProperties')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processItems(): void
    {
        if (!property_exists($this->storage, 'items')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processContains(): void
    {
        if (!property_exists($this->storage, 'contains')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processAdditionalItems(): void
    {
        if (!property_exists($this->storage, 'additionalItems')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processMinItems(): void
    {
        if (!property_exists($this->storage, 'minItems')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processMaxItems(): void
    {
        if (!property_exists($this->storage, 'maxItems')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function processUniqueItems(): void
    {
        if (!property_exists($this->storage, 'uniqueItems')) {
            return;
        }

        // @todo
    }
}
