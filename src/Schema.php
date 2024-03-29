<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Schema
{
    /**
     * Set default json schema version
     */
    const DEFAULT_VERSION = '7';

    /**
     * Current object schema
     * @var bool|object
     */
    protected $schema;

    /**
     * Current json schema version
     * @var string
     */
    protected $version;

    /**
     * Current instance path
     * @var string
     */
    protected $path;

    /**
     * Schema constructor.
     * @param object|bool $schema
     * @param string $version
     * @param string|null $path
     * @throws SchemaException
     */
    public function __construct($schema, string $version = self::DEFAULT_VERSION, string $path = '#')
    {
        // Set path
        $this->path = $path;

        // Set version
        $this->version = $version;

        // Get schema type
        $schemaType = gettype($schema);

        // Store current schema
        $this->schema = $schema;

        // Check for valid property type
        if ($schemaType !== 'object' && $schemaType !== 'boolean') {
            throw new SchemaException(sprintf(
                'You have "schema" which value is not a "object" or "boolean" but it is "%s" (%s)',
                gettype($this->schema),
                $this->getPath()
            ));
        }

        // If the schema is empty
        if ($schemaType === 'object') {
            $schemaCount = count((array)$this->schema);

            if ($schemaCount === 1 && property_exists($this->schema, 'additionalItems') && $this->schema->additionalItems === false) {
                unset($this->schema->additionalItems);
                $schemaCount--;
            }

            // Check for empty empty object. Then will make it "true"
            if ($schemaCount === 0) {
                $this->schema = true;
                $schemaType = 'boolean';
            }
        }

        // Check for boolean type
        if ($schemaType === 'boolean') {
            return;
        }

        // Check each attribute
        $this->processType();
        $this->processFormat();
        $this->processDefinitions();
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
        $this->processContentEncoding();
        $this->processContentMediaType();
        $this->processMultipleOf();
        $this->processMinimum();
        $this->processMaximum();
        $this->processExclusiveMinimum();
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
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * Return current json schema version
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Return current schema path
     * @param string $appendPath
     * @return string
     */
    public function getPath(string $appendPath = null): string
    {
        $path = trim($this->path, '/');

        if ($appendPath !== null) {
            $path .= '/' . trim($appendPath, '/');
        }

        if ($path === '#') {
            $path .= '/';
        }

        return $path;
    }

    /**
     * Transform jsonSchema to Schema object
     * @param bool|object ...$nesting
     * @throws SchemaException
     */
    protected function transform(... $nesting): void
    {
        $schema = $this->schema;

        foreach ($nesting as $path) {
            if (is_array($schema)) {
                $schema = &$schema[$path];
            } else {
                $schema = &$schema->{$path};
            }
        }

        // Build new path
        $newPath = $this->getPath(implode('/', $nesting));

        // Create (sub-)schema object
//        if ($this->_ref->isReference($newPath)) {
//            $schema = $this->_ref->getReferenceFor($newPath);
//        } else {
//            $schema = new Schema($schema, $this->version, $this->_ref, $newPath);
//        }

        // Temporary solution till the class is not refactored to work with the new recursive references
        if (count(explode('/', $newPath)) > 100) {
            $schema = new Schema(true, $this->version, $newPath);
            return;
        }

        // Create (sub-)schema object
        $schema = new Schema($schema, $this->version, $newPath);
    }

    /**
     * Check type property for appropriate format and for mismatch between properties and types
     * @throws SchemaException
     */
    protected function processType(): void
    {
        // Register type property
        if (!property_exists($this->schema, 'type')) {
            $this->schema->type = [];
        }

        // Make schema type to be array by default
        if (!is_array($this->schema->type)) {
            $this->schema->type = [$this->schema->type];
        }

        // If the schema type is null, then only null will be allowed as data
        if (in_array(null, $this->schema->type, true)) {
            $this->schema->const = null;
        }

        // Check that each item is with proper type
        foreach ($this->schema->type as $key => $type) {
            // Check is it a string
            if (!is_string($type)) {
                throw new SchemaException(sprintf(
                    'You have defined type which is not a string value (%s)',
                    $this->getPath('/type')
                ));
            }

            // Check for unknown types
            if (!in_array($type, ['string', 'number', 'integer', 'array', 'object', 'boolean', 'null'])) {
                throw new SchemaException(sprintf(
                    'Unknown type "%s" (%s)',
                    $type,
                    $this->getPath('/type')
                ));
            }
        }
    }

    /**
     * Check format property
     * @throws SchemaException
     */
    protected function processFormat(): void
    {
        if (!property_exists($this->schema, 'format')) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->schema->format)) {
            throw new SchemaException(sprintf(
                'You have "format" which value is not an string but it is "%s" (%s)',
                gettype($this->schema->format),
                $this->getPath('/format')
            ));
        }
    }

    /**
     * Check definitions property
     * @throws SchemaException
     */
    protected function processDefinitions(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'definitions')) {
            return;
        }

        // Check for valid property type
        if (!is_object($this->schema->definitions)) {
            throw new SchemaException(sprintf(
                'You have "definitions" which value is not a "object" but it is "%s" (%s)',
                gettype($this->schema->definitions),
                $this->getPath('/definitions')
            ));
        }

        foreach ($this->schema->definitions as $key => $value) {
            if ($value instanceof Schema) {
                continue;
            }

            // Transform to schema
            $this->transform('definitions', $key);
        }
    }

    /**
     * Check if property
     * @throws SchemaException
     */
    protected function processIf(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'if')) {
            return;
        }

        // Skip "if" when "then" and "else" are missing
        if (!property_exists($this->schema, 'then') && !property_exists($this->schema, 'else')) {
            unset($this->schema->if);
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->if instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('if');
    }

    /**
     * Check then property
     * @throws SchemaException
     */
    protected function processThen(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'then')) {
            return;
        }

        // Skip "then" when "if" is missing
        if (!property_exists($this->schema, 'if')) {
            unset($this->schema->then);
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->then instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('then');
    }

    /**
     * Check else property
     * @throws SchemaException
     */
    protected function processElse(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'else')) {
            return;
        }

        // Skip "else" when "if" is missing
        if (!property_exists($this->schema, 'if')) {
            unset($this->schema->else);
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->else instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('else');
    }

    /**
     * Check const property
     */
    protected function processConst(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'const')) {
            return;
        }

        // Do nothing
    }

    /**
     * Check enum property
     * @throws SchemaException
     */
    protected function processEnum(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'enum')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->schema->enum)) {
            throw new SchemaException(sprintf(
                'You have "enum" which value is not an "array" but it is "%s" (%s)',
                gettype($this->schema->enum),
                $this->getPath('/enum')
            ));
        }
    }

    /**
     * Check allOf property
     * @throws SchemaException
     */
    protected function processAllOf(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'allOf')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->schema->allOf)) {
            throw new SchemaException(sprintf(
                'You have "allOf" which value is not a "array" but it is "%s" (%s)',
                gettype($this->schema->allOf),
                $this->getPath('/allOf')
            ));
        }

        foreach ($this->schema->allOf as $key => $value) {
            if ($value instanceof Schema) {
                continue;
            }

            // Transform to schema
            $this->transform('allOf', $key);
        }
    }

    /**
     * Check anyOf property
     * @throws SchemaException
     */
    protected function processAnyOf(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'anyOf')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->schema->anyOf)) {
            throw new SchemaException(sprintf(
                'You have "anyOf" which value is not a "array" but it is "%s" (%s)',
                gettype($this->schema->anyOf),
                $this->getPath('/anyOf')
            ));
        }

        foreach ($this->schema->anyOf as $key => $value) {
            if ($value instanceof Schema) {
                continue;
            }

            // Transform to schema
            $this->transform('anyOf', $key);
        }
    }

    /**
     * Check oneOf property
     * @throws SchemaException
     */
    protected function processOneOf(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'oneOf')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->schema->oneOf)) {
            throw new SchemaException(sprintf(
                'You have "oneOf" which value is not a "array" but it is "%s" (%s)',
                gettype($this->schema->oneOf),
                $this->getPath('/oneOf')
            ));
        }

        foreach ($this->schema->oneOf as $key => $value) {
            if ($value instanceof Schema) {
                continue;
            }

            // Transform to schema
            $this->transform('oneOf', $key);
        }
    }

    /**
     * Check not property
     * @throws SchemaException
     */
    protected function processNot(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'not')) {
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->not instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('not');
    }

    /**
     * Check minLength property
     * @throws SchemaException
     */
    protected function processMinLength(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'minLength')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->schema->minLength)) {
            throw new SchemaException(sprintf(
                'You have "minLength" which value is not an integer but it is "%s" (%s)',
                gettype($this->schema->minLength),
                $this->getPath('/minLength')
            ));
        }

        // Check positive value
        if ($this->schema->minLength < 0) {
            throw new SchemaException(sprintf(
                '"minLength" must be positive integer, you have "%s" (%s)',
                $this->schema->minLength,
                $this->getPath('/minLength')
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
        if (!property_exists($this->schema, 'maxLength')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->schema->maxLength)) {
            throw new SchemaException(sprintf(
                'You have "maxLength" which value is not an integer but it is "%s" (%s)',
                gettype($this->schema->maxLength),
                $this->getPath('/maxLength')
            ));
        }

        // Check positive value
        if ($this->schema->maxLength < 0) {
            throw new SchemaException(sprintf(
                '"maxLength" must be positive integer, you have "%s" (%s)',
                $this->schema->maxLength,
                $this->getPath('/maxLength')
            ));
        }

        // Check is maxLength lower than minLength
        if (property_exists($this->schema, 'minLength')) {
            if ($this->schema->maxLength < $this->schema->minLength) {
                throw new SchemaException(sprintf(
                    'You have "maxLength" with value "%d" which is lower than "minLength" with value "%d" (%s)',
                    $this->schema->maxLength,
                    $this->schema->minLength,
                    $this->getPath('/maxLength')
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
        if (!property_exists($this->schema, 'pattern')) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->schema->pattern)) {
            throw new SchemaException(sprintf(
                'You have "pattern" which value is not a "string" but it is "%s" (%s)',
                gettype($this->schema->pattern),
                $this->getPath('/pattern')
            ));
        }

        // Check for valid property value
        if (!Check::regex($this->schema->pattern)) {
            throw new SchemaException(sprintf(
                'You have "pattern" which is not valid regex (%s)',
                $this->getPath('/pattern')
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
        if (!property_exists($this->schema, 'contentEncoding')) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->schema->contentEncoding)) {
            throw new SchemaException(sprintf(
                'You have "contentEncoding" which value is not a "string" but it is "%s" (%s)',
                gettype($this->schema->contentEncoding),
                $this->getPath('/contentEncoding')
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
        if (!property_exists($this->schema, 'contentMediaType')) {
            return;
        }

        // Check for valid property type
        if (!is_string($this->schema->contentMediaType)) {
            throw new SchemaException(sprintf(
                'You have "contentMediaType" which value is not a "string" but it is "%s" (%s)',
                gettype($this->schema->contentMediaType),
                $this->getPath('/contentMediaType')
            ));
        }

        // Check for valid property value
        if (strstr($this->schema->contentMediaType, '/') === false) {
            throw new SchemaException(sprintf(
                'You have "contentMediaType" which is not well formatted. Slash "/" is missing (%s)',
                $this->getPath('/contentMediaType')
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
        if (!property_exists($this->schema, 'multipleOf')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->schema->multipleOf) && !is_integer($this->schema->multipleOf)) {
            throw new SchemaException(sprintf(
                'You have "multipleOf" which value is not a "numeric" but it is "%s" (%s)',
                gettype($this->schema->multipleOf),
                $this->getPath('/multipleOf')
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
        if (!property_exists($this->schema, 'minimum')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->schema->minimum) && !is_integer($this->schema->minimum)) {
            throw new SchemaException(sprintf(
                'You have "minimum" which value is not a "number/integer" but it is "%s" (%s)',
                gettype($this->schema->minimum),
                $this->getPath('/minimum')
            ));
        }
    }

    /**
     * Check maximum property
     * @throws SchemaException
     */
    protected function processMaximum(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'maximum')) {
            return;
        }

        // Check for valid property type
        if (!is_double($this->schema->maximum) && !is_integer($this->schema->maximum)) {
            throw new SchemaException(sprintf(
                'You have "maximum" which value is not a "number/integer" but it is "%s" (%s)',
                gettype($this->schema->maximum),
                $this->getPath('/maximum')
            ));
        }

        // Minimum checks
        if (property_exists($this->schema, 'minimum')) {
            // Check is maximum lower than minimum
            if ($this->schema->maximum < $this->schema->minimum) {
                throw new SchemaException(sprintf(
                    'You have "maximum" with value "%d" which is lower than "minimum" with value "%d" (%s)',
                    $this->schema->maximum,
                    $this->schema->minimum,
                    $this->getPath('/maximum')
                ));
            }
        }
    }

    /**
     * Check exclusiveMinimum property
     * @throws SchemaException
     */
    protected function processExclusiveMinimum(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'exclusiveMinimum')) {
            return;
        }

        // Check for valid property type
        $propertyType = gettype($this->schema->exclusiveMinimum);

        switch ($this->getVersion()) {
            case '6':
            case '7':
            {
                // Check for valid property type
                if (!is_double($this->schema->exclusiveMinimum) && !is_integer($this->schema->exclusiveMinimum)) {
                    throw new SchemaException(sprintf(
                        'You have "exclusiveMinimum" which value is not a "number/integer" but it is "%s" (%s)',
                        gettype($this->schema->exclusiveMinimum),
                        $this->getPath('/exclusiveMinimum')
                    ));
                }

                // Minimum checks
                if (property_exists($this->schema, 'minimum')) {
                    // Check is exclusiveMinimum lower than minimum
                    if ($this->schema->exclusiveMinimum < $this->schema->minimum) {
                        throw new SchemaException(sprintf(
                            'You have "exclusiveMinimum" with value "%d" which is lower than "minimum" with value "%d" (%s)',
                            $this->schema->exclusiveMinimum,
                            $this->schema->minimum,
                            $this->getPath('/exclusiveMinimum')
                        ));
                    }
                }

                break;
            }
            case '4':
            {
                if ($propertyType !== 'boolean') {
                    throw new SchemaException(sprintf(
                        'You have "exclusiveMinimum" which value is not a "boolean" but it is "%s" (%s)',
                        gettype($this->schema->exclusiveMinimum),
                        $this->getPath('/exclusiveMinimum')
                    ));
                }

                break;
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
        if (!property_exists($this->schema, 'exclusiveMaximum')) {
            return;
        }

        // Check for valid property type
        $propertyType = gettype($this->schema->exclusiveMaximum);

        switch ($this->getVersion()) {
            case '6':
            case '7':
            {
                // Check for valid property type
                if (!is_double($this->schema->exclusiveMaximum) && !is_integer($this->schema->exclusiveMaximum)) {
                    throw new SchemaException(sprintf(
                        'You have "exclusiveMaximum" which value is not a "number/integer" but it is "%s" (%s)',
                        gettype($this->schema->exclusiveMaximum),
                        $this->getPath('/exclusiveMaximum')
                    ));
                }

                // exclusiveMinimum checks
                if (property_exists($this->schema, 'exclusiveMinimum')) {
                    // Check is exclusiveMaximum lower than exclusiveMinimum
                    if ($this->schema->exclusiveMaximum < $this->schema->exclusiveMinimum) {
                        throw new SchemaException(sprintf(
                            'You have "exclusiveMaximum" with value "%d" which is lower than "exclusiveMinimum" with value "%d" (%s)',
                            $this->schema->exclusiveMaximum,
                            $this->schema->exclusiveMinimum,
                            $this->getPath('/exclusiveMaximum')
                        ));
                    }

                    // Check is exclusiveMaximum equal to exclusiveMinimum
                    if ($this->schema->exclusiveMaximum == $this->schema->exclusiveMinimum) {
                        throw new SchemaException(sprintf(
                            'You have "exclusiveMaximum" with value "%d" which is equal to "exclusiveMinimum" with value "%d" (%s)',
                            $this->schema->exclusiveMaximum,
                            $this->schema->exclusiveMinimum,
                            $this->getPath('/exclusiveMaximum')
                        ));
                    }
                }

                break;
            }
            case '4':
            {
                if ($propertyType !== 'boolean') {
                    throw new SchemaException(sprintf(
                        'You have "exclusiveMaximum" which value is not a "booleanbool" but it is "%s" (%s)',
                        gettype($this->schema->exclusiveMaximum),
                        $this->getPath('/exclusiveMaximum')
                    ));
                }

                break;
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
        if (!property_exists($this->schema, 'properties')) {
            return;
        }

        // Check for valid property type
        if (!is_object($this->schema->properties)) {
            throw new SchemaException(sprintf(
                'You have "properties" which value is not a "object" but it is "%s" (%s)',
                gettype($this->schema->properties),
                $this->getPath('/properties')
            ));
        }

        foreach ($this->schema->properties as $key => $value) {
            if ($value instanceof Schema) {
                continue;
            }

            // Transform to schema
            $this->transform('properties', $key);
        }
    }

    /**
     * Check additionalProperties property
     * @throws SchemaException
     */
    protected function processAdditionalProperties(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'additionalProperties')) {
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->additionalProperties instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('additionalProperties');
    }

    /**
     * Check required property
     * @throws SchemaException
     */
    protected function processRequired(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'required')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->schema->required)) {
            throw new SchemaException(sprintf(
                'You have "required" which value is not a "array" but it is "%s" (%s)',
                gettype($this->schema->required),
                $this->getPath('/required')
            ));
        }

        // Check that each item is with proper type
        foreach ($this->schema->required as $required) {
            if (!is_string($required)) {
                throw new SchemaException(sprintf(
                    'You have defined required property which is not a string value (%s)',
                    $this->getPath('/required')
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
        if (!property_exists($this->schema, 'propertyNames')) {
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->propertyNames instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('propertyNames');
    }

    /**
     * Check minProperties property
     * @throws SchemaException
     */
    protected function processMinProperties(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'minProperties')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->schema->minProperties)) {
            throw new SchemaException(sprintf(
                'You have "minProperties" which value is not a "integer" but it is "%s" (%s)',
                gettype($this->schema->minProperties),
                $this->getPath('/minProperties')
            ));
        }

        // Check positive value
        if ($this->schema->minProperties < 0) {
            throw new SchemaException(sprintf(
                '"minProperties" must be positive integer, you have "%s" (%s)',
                $this->schema->minProperties,
                $this->getPath('/minProperties')
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
        if (!property_exists($this->schema, 'maxProperties')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->schema->maxProperties)) {
            throw new SchemaException(sprintf(
                'You have "maxProperties" which value is not a "integer" but it is "%s" (%s)',
                gettype($this->schema->maxProperties),
                $this->getPath('/maxProperties')
            ));
        }

        // Check positive value
        if ($this->schema->maxProperties < 0) {
            throw new SchemaException(sprintf(
                '"maxProperties" must be positive integer, you have "%s" (%s)',
                $this->schema->maxProperties,
                $this->getPath('/maxProperties')
            ));
        }

        // Check is maxProperties lower than minProperties
        if (property_exists($this->schema, 'minProperties')) {
            if ($this->schema->maxProperties < $this->schema->minProperties) {
                throw new SchemaException(sprintf(
                    'You have "maxProperties" with value "%d" which is lower than "minProperties" with value "%d" (%s)',
                    $this->schema->maxProperties,
                    $this->schema->minProperties,
                    $this->getPath('/maxProperties')
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
        if (!property_exists($this->schema, 'dependencies')) {
            return;
        }

        // Check for valid property type
        if (!is_object($this->schema->dependencies)) {
            throw new SchemaException(sprintf(
                'You have "dependencies" which value is not an "object" but it is "%s" (%s)',
                gettype($this->schema->dependencies),
                $this->getPath('/dependencies')
            ));
        }

        // Check the schema
        foreach ($this->schema->dependencies as $dKey => $schema) {
            // If is already transformed to Schema
            if ($schema instanceof Schema) {
                continue;
            }

            // If schema is array
            if (is_array($schema)) {
                // Check that each item is a string
                foreach ($schema as $sKey => $item) {
                    if (!is_string($item)) {
                        throw new SchemaException(sprintf(
                            'You have defined dependency item which is not a string value (%s)',
                            $this->getPath('/dependencies/' . $sKey)
                        ));
                    }
                }

                // Normalize to schema
                $this->schema->dependencies->{$dKey} = (object)[
                    'type' => 'object',
                    'additionalProperties' => true,
                    'required' => $schema
                ];
            }

            // Transform to schema
            $this->transform('dependencies', $dKey);
        }
    }

    /**
     * Check patternProperties property
     * @throws SchemaException
     */
    protected function processPatternProperties(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'patternProperties')) {
            return;
        }

        // Check for valid property type
        if (!is_object($this->schema->patternProperties)) {
            throw new SchemaException(sprintf(
                'You have "patternProperties" which value is not an "object" but it is "%s" (%s)',
                gettype($this->schema->patternProperties),
                $this->getPath('/patternProperties')
            ));
        }

        // Check the structure of patternProperties
        foreach ($this->schema->patternProperties as $keyPattern => $schema) {
            // If is already transformed to Schema
            if ($schema instanceof Schema) {
                continue;
            }

            // Check key pattern
            if (!Check::regex($keyPattern)) {
                throw new SchemaException(sprintf(
                    'You have "patternProperties" with key "%s" which is not valid regex pattern (%s)',
                    $keyPattern,
                    $this->getPath('/patternProperties/' . $keyPattern)
                ));
            }

            // Transform to schema
            $this->transform('patternProperties', $keyPattern);
        }
    }

    /**
     * Check items property
     * @throws SchemaException
     */
    protected function processItems(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'items')) {
            return;
        }

        // Check for valid property type
        if (!is_array($this->schema->items) && !is_object($this->schema->items) && !is_bool($this->schema->items)) {
            throw new SchemaException(sprintf(
                'You have "items" which value is not a "array", "object" or "boolean" but it is "%s" (%s)',
                gettype($this->schema->items),
                $this->getPath('/items')
            ));
        }

        // Validate multiple item schema
        if (is_array($this->schema->items)) {
            foreach ($this->schema->items as $key => $schema) {
                // If is already transformed to Schema
                if ($schema instanceof Schema) {
                    return;
                }

                // Transform to schema
                $this->transform('items', $key);
            }
        }

        // Validate single item schema
        if (is_object($this->schema->items) || is_bool($this->schema->items)) {
            // If is already transformed to Schema
            if ($this->schema->items instanceof Schema) {
                return;
            }

            // Transform to schema
            $this->transform('items');
        }
    }

    /**
     * Check contains property
     * @throws SchemaException
     */
    protected function processContains(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'contains')) {
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->contains instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('contains');
    }

    /**
     * Check additionalItems property
     * @throws SchemaException
     */
    protected function processAdditionalItems(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'additionalItems')) {
            return;
        }

        // If is already transformed to Schema
        if ($this->schema->additionalItems instanceof Schema) {
            return;
        }

        // Transform to schema
        $this->transform('additionalItems');
    }

    /**
     * Check minItems property
     * @throws SchemaException
     */
    protected function processMinItems(): void
    {
        // Check exists
        if (!property_exists($this->schema, 'minItems')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->schema->minItems)) {
            throw new SchemaException(sprintf(
                'You have "minItems" which value is not an integer but it is "%s" (%s)',
                gettype($this->schema->minItems),
                $this->getPath('/minItems')
            ));
        }

        // Check positive value
        if ($this->schema->minItems < 0) {
            throw new SchemaException(sprintf(
                '"minItems" must be positive integer, you have "%s" (%s)',
                $this->schema->minItems,
                $this->getPath('/minItems')
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
        if (!property_exists($this->schema, 'maxItems')) {
            return;
        }

        // Check for valid property type
        if (!is_integer($this->schema->maxItems)) {
            throw new SchemaException(sprintf(
                'You have "maxItems" which value is not an integer but it is "%s" (%s)',
                gettype($this->schema->maxItems),
                $this->getPath('/maxItems')
            ));
        }

        // Check positive value
        if ($this->schema->maxItems < 0) {
            throw new SchemaException(sprintf(
                '"maxItems" must be positive integer, you have "%s" (%s)',
                $this->schema->maxItems,
                $this->getPath('/maxItems')
            ));
        }

        // Check is maxItems lower than minItems
        if (property_exists($this->schema, 'minItems')) {
            if ($this->schema->maxItems < $this->schema->minItems) {
                throw new SchemaException(sprintf(
                    'You have "maxItems" with value "%d" which is lower than "minItems" with value "%d" (%s)',
                    $this->schema->maxItems,
                    $this->schema->minItems,
                    $this->getPath('/maxItems')
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
        if (!property_exists($this->schema, 'uniqueItems')) {
            return;
        }

        // Check for valid property type
        if (!is_bool($this->schema->uniqueItems)) {
            throw new SchemaException(sprintf(
                'You have "uniqueItems" which value is not a "boolean" but it is "%s" (%s)',
                gettype($this->schema->uniqueItems),
                $this->getPath('/uniqueItems')
            ));
        }
    }
}
