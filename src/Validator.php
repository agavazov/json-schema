<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Validator
{
    /**
     * Cast the data to the specific type (if it is passable)
     */
    const MODE_CAST = 1;

    /**
     * Apply default values from the schema to the data
     */
    const MODE_APPLY_DEFAULTS = 2; // @todo

    /**
     * Remove additional properties & additional items if they are not set to TRUE
     */
    const MODE_REMOVE_ADDITIONALS = 4; // @todo

    /**
     * Registered formats
     * @var object
     */
    protected $formats;

    /**
     * Validator configuration
     * @var int
     */
    protected $mode;

    /**
     * Validator constructor.
     * @param int $mode
     */
    public function __construct(int $mode = 0)
    {
        $this->formats = (object)[];
        $this->mode = $mode;

        $this->registerFormat('date-time', __NAMESPACE__ . '\\Check::dateTime');
        $this->registerFormat('time', __NAMESPACE__ . '\\Check::time');
        $this->registerFormat('date', __NAMESPACE__ . '\\Check::date');
        $this->registerFormat('email', __NAMESPACE__ . '\\Check::email');
        $this->registerFormat('idn-email', __NAMESPACE__ . '\\Check::idnEmail');
        $this->registerFormat('hostname', __NAMESPACE__ . '\\Check::hostname');
        $this->registerFormat('idn-hostname', __NAMESPACE__ . '\\Check::idnHostname');
        $this->registerFormat('ipv4', __NAMESPACE__ . '\\Check::ipv4');
        $this->registerFormat('ipv6', __NAMESPACE__ . '\\Check::ipv6');
        $this->registerFormat('uri', __NAMESPACE__ . '\\Check::uri');
        $this->registerFormat('uri-reference', __NAMESPACE__ . '\\Check::uriReference');
        $this->registerFormat('iri', __NAMESPACE__ . '\\Check::iri');
        $this->registerFormat('iri-reference', __NAMESPACE__ . '\\Check::iriReference');
        $this->registerFormat('uri-template', __NAMESPACE__ . '\\Check::uriTemplate');
        $this->registerFormat('json-pointer', __NAMESPACE__ . '\\Check::jsonPointer');
        $this->registerFormat('relative-json-pointer', __NAMESPACE__ . '\\Check::relativeJsonPointer');
        $this->registerFormat('regex', __NAMESPACE__ . '\\Check::regex');
    }

    /**
     * Register new format
     * @param string $formatId
     * @param callable $validation
     */
    public function registerFormat(string $formatId, callable $validation): void
    {
        $this->formats->{$formatId} = $validation;
    }

    /**
     * Validate data against the schema
     * @param $data
     * @param Schema
     * @return mixed
     * @throws SchemaException
     * @throws ValidationException
     */
    public function validate($data, Schema $schema)
    {
        // If the whole schema is boolean
        if (is_bool($schema->getSchema())) {
            // If schema is "false" then it will disallow everything
            if ($schema->getSchema() === false) {
                throw new ValidationException(sprintf(
                    'Provided schema is with value "false" which means it will disallow everything (%s)',
                    $schema->getPath()
                ));
            } else {
                return true; // When is "true" then it will allow everything
            }
        }

        // Check for pseudo arrays
        $data = Helper::transformPseudoArrays($data);

        // Validate
        $this->validateType($data, $schema);
        $this->validateFormat($data, $schema);
        $this->validateIf($data, $schema);
        $this->validateThen($data, $schema);
        $this->validateElse($data, $schema);
        $this->validateConst($data, $schema);
        $this->validateEnum($data, $schema);
        $this->validateAllOf($data, $schema);
        $this->validateAnyOf($data, $schema);
        $this->validateOneOf($data, $schema);
        $this->validateNot($data, $schema);

        switch (gettype($data)) {
            case 'string':
                {
                    $this->validateMinLength($data, $schema);
                    $this->validateMaxLength($data, $schema);
                    $this->validatePattern($data, $schema);
                    $this->validateContentEncoding($data, $schema);
                    $this->validateContentMediaType($data, $schema);
                    break;
                }
            case 'double':
            case 'integer':
                {
                    $this->validateMultipleOf($data, $schema);
                    $this->validateMinimum($data, $schema);
                    $this->validateExclusiveMinimum($data, $schema);
                    $this->validateMaximum($data, $schema);
                    $this->validateExclusiveMaximum($data, $schema);
                    break;
                }
            case 'object':
                {
                    $this->validateProperties($data, $schema);
                    $this->validateAdditionalProperties($data, $schema);
                    $this->validateRequired($data, $schema);
                    $this->validatePropertyNames($data, $schema);
                    $this->validateMinProperties($data, $schema);
                    $this->validateMaxProperties($data, $schema);
                    $this->validateDependencies($data, $schema);
                    $this->validatePatternProperties($data, $schema);
                    break;
                }
            case 'array':
                {
                    $this->validateItems($data, $schema);
                    $this->validateContains($data, $schema);
                    $this->validateAdditionalItems($data, $schema);
                    $this->validateMinItems($data, $schema);
                    $this->validateMaxItems($data, $schema);
                    $this->validateUniqueItems($data, $schema);
                    break;
                }
        }

        return $data;
    }

    /**
     * Validate type & cast the data
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateType(&$data, Schema $schema): void
    {
        // When there is no type the validation will allow everything
        if (!property_exists($schema->getSchema(), 'type') || count($schema->getSchema()->type) === 0) {
            return;
        }

        // Fix type cases
        foreach ($schema->getSchema()->type as $key => $value) {
            $schema->getSchema()->type[$key] = strtolower($value);
        }

        // Cast only if there is one type
        $doCast = ($this->mode & self::MODE_CAST) === self::MODE_CAST;
        if ($doCast && count($schema->getSchema()->type) === 1) {
            $castTo = $schema->getSchema()->type[0];

            $data = call_user_func_array(__NAMESPACE__ . '\\Cast::' . $castTo, [$data]);
        }

        // Decide which format to choose
        $dataType = strtolower(gettype($data));
        $matchType = false;

        // Special integer/number cases and mixes
        if ($dataType === 'double' || $dataType === 'float') {
            if (in_array('integer', $schema->getSchema()->type) && Check::integer($data)) {
                $dataType = 'integer';
            } else {
                $dataType = 'number';
            }
        } elseif ($dataType === 'integer') {
            if (!in_array('integer', $schema->getSchema()->type)) {
                $dataType = 'number';
            }
        }

        // Check for type match
        foreach ($schema->getSchema()->type as $type) {
            if ($dataType === $type) {
                $matchType = $type;
                break;
            }
        }

        if ($matchType !== false) {
            /*
            $schema->setMainType((string)$matchType);

            if (!call_user_func_array(__NAMESPACE__ . '\\Check::' . $schema->getMainType(), [$data])) {
                throw new ValidationException(sprintf(
                    'Provided data type "%s" does not validated with schema type "%s" (%s)',
                    $dataType,
                    $schema->getMainType(),
                    $schema->getPath()
                ));
            }
            */
            // @todo
        } else {
            throw new ValidationException(sprintf(
                'There is provided schema with type/s "%s" which not match with the data type "%s" (%s)',
                implode(';', $schema->getSchema()->type),
                $dataType,
                $schema->getPath() . '/type'
            ));
        }
    }

    /**
     * Validate format
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateFormat(&$data, Schema $schema): void
    {
        if (!property_exists($schema->getSchema(), 'format')) {
            return;
        }

        $format = $schema->getSchema()->format;

        if (!property_exists($this->formats, $format)) {
            throw new ValidationException(sprintf(
                'Unknown format "%s" (%s)',
                $format,
                $schema->getPath() . '/format'
            ));
        }

        $isValid = call_user_func_array($this->formats->{$format}, [$data]);

        if (!$isValid) {
            throw new ValidationException(sprintf(
                'Provided data is not validated with format "%s" (%s)',
                $format,
                $schema->getPath() . '/format'
            ));
        }
    }

    /**
     * @todo
     */
    protected function validateIf(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'if')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateThen(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'then')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateElse(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'else')) {
            return;
        }

        // @todo
    }

    /**
     * Validate const
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateConst(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'const')) {
            return;
        }

        if (Helper::compare($data, $schema->getSchema()->const)) {
            return;
        }

        throw new ValidationException(sprintf(
            $schema->getPath() . '/const'
        ));
    }

    /**
     * Validate enum
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateEnum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'enum')) {
            return;
        }

        foreach ($schema->getSchema()->enum as $enumData) {
            if (Helper::compare($data, $enumData)) {
                return;
            }
        }

        throw new ValidationException(sprintf(
            'Non of provided "%d" enums matches with the provided data (%s)',
            count($schema->getSchema()->enum),
            $schema->getPath() . '/enum'
        ));
    }

    /**
     * @todo
     */
    protected function validateAllOf(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'allOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateAnyOf(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'anyOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateOneOf(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'oneOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateNot(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'not')) {
            return;
        }

        // @todo ok
    }

    /**
     * Validate minLength
     * @param string $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMinLength(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'minLength')) {
            return;
        }

        // Check string min length
        if (mb_strlen($data) < $schema->getSchema()->minLength) {
            throw new ValidationException(sprintf(
                'Min length is "%s" but your string is with "%s" (%s)',
                $schema->getSchema()->minLength,
                strlen($data),
                $schema->getPath() . '/minLength'
            ));
        }
    }

    /**
     * Validate maxLength
     * @param string $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMaxLength(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'maxLength')) {
            return;
        }

        // Check string max length
        if (mb_strlen($data) > $schema->getSchema()->maxLength) {
            throw new ValidationException(sprintf(
                'Max length is "%s" but your string is with "%s" (%s)',
                $schema->getSchema()->maxLength,
                strlen($data),
                $schema->getPath() . '/maxLength'
            ));
        }
    }

    /**
     * Validate pattern
     * @param string $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validatePattern(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'pattern')) {
            return;
        }

        // Check pattern match
        if (!preg_match('/' . $schema->getSchema()->pattern . '/u', $data)) {
            throw new ValidationException(sprintf(
                'Pattern "%s" not match with the input data (%s)',
                $schema->getSchema()->pattern,
                $schema->getPath() . '/pattern'
            ));
        }
    }

    /**
     * Validate content encoding
     * @param string $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateContentEncoding(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'contentEncoding')) {
            return;
        }

        $encoded = $data;

        switch ($schema->getSchema()->contentEncoding) {
            case 'base64':
                {
                    $encoded = base64_decode($encoded, true);
                    break;
                }
        }

        if ($encoded === false) {
            throw new ValidationException(sprintf(
                'The data can`t be encoded by "%d" (%s)',
                $schema->getSchema()->contentEncoding,
                $schema->getPath() . '/contentEncoding'
            ));
        } else {
            $data = $encoded;
        }
    }

    /**
     * Validate content type
     * @param string $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateContentMediaType(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'contentMediaType')) {
            return;
        }

        $isValid = true;

        switch ($schema->getSchema()->contentMediaType) {
            case 'application/json':
                {
                    json_decode($data);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $isValid = false;
                    }

                    break;
                }
        }

        if ($isValid !== true) {
            throw new ValidationException(sprintf(
                'The input data is not does not validated with content type "%s" (%s)',
                $schema->getSchema()->contentMediaType,
                $schema->getPath() . '/contentMediaType'
            ));
        }
    }

    /**
     * Validate multiple of
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMultipleOf(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'multipleOf')) {
            return;
        }

        $number = $data;
        $multipleOf = $schema->getSchema()->multipleOf;

        if ($number === 0) {
            return;
        }

        // Convert float values to integers
        if (is_double($number) || is_double($multipleOf)) {
            $zeroMultiplier = 1;
            foreach ([strlen((string)(int)(1 / $number)), strlen((string)(int)(1 / $multipleOf))] as $numberLength) {
                $tmp = (int)('1' . str_repeat('0', $numberLength));
                if ($zeroMultiplier < $tmp) {
                    $zeroMultiplier = $tmp;
                }
            }

            $number *= $zeroMultiplier;
            $multipleOf *= $zeroMultiplier;
        }

        // Check number value
        if (($number - $multipleOf * (int)($number / $multipleOf)) != 0) {
            throw new ValidationException(sprintf(
                'You have value "%s" which is not multiple of "%s" (%s)',
                $number,
                $schema->getSchema()->multipleOf,
                $schema->getPath() . '/multipleOf'
            ));
        }
    }

    /**
     * Validate minimum
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMinimum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'minimum')) {
            return;
        }

        // Check number value
        if ($data < $schema->getSchema()->minimum) {
            throw new ValidationException(sprintf(
                'Min value is "%d" but your number is with value "%d" (%s)',
                $schema->getSchema()->minimum,
                $data,
                $schema->getPath() . '/minimum'
            ));
        }
    }

    /**
     * Validate maximum
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMaximum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'maximum')) {
            return;
        }

        // Check number value
        if ($data > $schema->getSchema()->maximum) {
            throw new ValidationException(sprintf(
                'Max value is "%d" but your number is with value "%d" (%s)',
                $schema->getSchema()->maximum,
                $data,
                $schema->getPath() . '/maximum'
            ));
        }
    }

    /**
     * Validate exclusive minimum
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateExclusiveMinimum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'exclusiveMinimum')) {
            return;
        }

        // Check number value
        if ($data <= $schema->getSchema()->exclusiveMinimum) {
            throw new ValidationException(sprintf(
                'Exclusive minimum value is "%d" but your number is with value "%d" (%s)',
                $schema->getSchema()->exclusiveMinimum,
                $data,
                $schema->getPath() . '/exclusiveMinimum'
            ));
        }
    }

    /**
     * Validate exclusive maximum
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateExclusiveMaximum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'exclusiveMaximum')) {
            return;
        }

        // Check number value
        if ($data >= $schema->getSchema()->exclusiveMaximum) {
            throw new ValidationException(sprintf(
                'Exclusive maximum value is "%d" but your number is with value "%d" (%s)',
                $schema->getSchema()->exclusiveMaximum,
                $data,
                $schema->getPath() . '/exclusiveMaximum'
            ));
        }
    }

    /**
     * Validate properties
     * @param object $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validateProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'properties')) {
            return;
        }

        // Get additional properties
        $additionalsDefault = true;
        $additionalProperties = $additionalsDefault;

        if (property_exists($schema->getSchema(), 'additionalProperties')) {
            $additionalProperties = $schema->getSchema()->additionalProperties->getSchema();
        }

        // Get pattern properties
        $patternProperties = [];
        if (property_exists($schema->getSchema(), 'patternProperties')) {
            $patternProperties = array_keys(get_object_vars($schema->getSchema()->patternProperties));
        }

        // Check each data property
        foreach ($data as $key => $propertyData) {
            // Validate mapped properties
            if (property_exists($schema->getSchema()->properties, $key)) {
                $data->{$key} = $this->validate($propertyData, $schema->getSchema()->properties->{$key});
            } elseif (!$additionalProperties) {
                // If the property matches patternProperties then its not an additional property
                foreach ($patternProperties as $pattern) {
                    if (preg_match('/' . $pattern . '/', $key)) {
                        continue 2;
                    }
                }

                // If additional properties are not allowed the will fail if there is an extra properties
                throw new ValidationException(sprintf(
                    'Object property with key "%d" is not declared in properties map (%s)',
                    $key,
                    $schema->getPath() . '/properties'
                ));
            }
        }
    }

    /**
     * Validate additional properties
     * @param object $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validateAdditionalProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'additionalProperties')) {
            return;
        }

        // Get current properties
        $currentProperties = [];
        if (property_exists($schema->getSchema(), 'properties')) {
            $currentProperties = array_keys(get_object_vars($schema->getSchema()->properties));
        }

        // Get pattern properties
        $patternProperties = [];
        if (property_exists($schema->getSchema(), 'patternProperties')) {
            $patternProperties = array_keys(get_object_vars($schema->getSchema()->patternProperties));
        }

        // Check each data property
        foreach ($data as $key => $propertyData) {
            // If the property is declared in properties list then its not an additional property
            if (in_array($key, $currentProperties)) {
                continue;
            }

            // If the property matches patternProperties then its not an additional property
            foreach ($patternProperties as $pattern) {
                if (preg_match('/' . $pattern . '/', $key)) {
                    continue 2;
                }
            }

            // Validate the additional property
            $data->{$key} = $this->validate($propertyData, $schema->getSchema()->additionalProperties);
        }
    }

    /**
     * Validate required
     * @param object $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateRequired(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'required')) {
            return;
        }

        // Check for each property
        foreach ($schema->getSchema()->required as $required) {
            if (!property_exists($data, $required)) {
                throw new ValidationException(sprintf(
                    'You have missing property key "%s" (%s)',
                    $required,
                    $schema->getPath() . '/required'
                ));
            }
        }
    }

    /**
     * Validate property names
     * @param object $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validatePropertyNames(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'propertyNames')) {
            return;
        }

        foreach ($data as $dataKey => $dataValue) {
            $this->validate($dataKey, $schema->getSchema()->propertyNames);
        }
    }

    /**
     * Validate minProperties
     * @param object $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMinProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'minProperties')) {
            return;
        }

        // Check object length
        if (count((array)$data) < $schema->getSchema()->minProperties) {
            throw new ValidationException(sprintf(
                'Min properties are "%s" but your object contains "%s" properties (%s)',
                $schema->getSchema()->minProperties,
                count((array)$data),
                $schema->getPath() . '/minProperties'
            ));
        }
    }

    /**
     * Validate maxProperties
     * @param object $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMaxProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'maxProperties')) {
            return;
        }

        // Check object length
        if (count((array)$data) > $schema->getSchema()->maxProperties) {
            throw new ValidationException(sprintf(
                'Max properties are "%s" but your object contains "%s" properties (%s)',
                $schema->getSchema()->maxProperties,
                count((array)$data),
                $schema->getPath() . '/maxProperties'
            ));
        }
    }

    /**
     * Validate dependencies
     * @param object $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validateDependencies(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'dependencies')) {
            return;
        }

        foreach ($data as $key => $dependencySchema) {
            /* @var $dependencySchema Schema */

            if (property_exists($schema->getSchema()->dependencies, $key)) {
                $this->validate($data, $schema->getSchema()->dependencies->{$key});
            }
        }
    }

    /**
     * Validate pattern properties
     * @param object $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validatePatternProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'patternProperties')) {
            return;
        }

        // Get current properties
        $currentProperties = [];
        if (property_exists($schema->getSchema(), 'properties')) {
            $currentProperties = array_keys(get_object_vars($schema->getSchema()->properties));
        }

        foreach ($schema->getSchema()->patternProperties as $pattern => $propertySchema) {
            /* @var $propertySchema Schema */

            foreach ($data as $dataKey => $dataValue) {
                // If the property is declared in properties list then its not an additional property
                if (in_array($dataKey, $currentProperties)) {
                    continue;
                }

                // Check for pattern
                if (preg_match('/' . $pattern . '/', $dataKey)) {
                    $data->{$dataKey} = $this->validate($dataValue, $propertySchema);
                }
            }
        }
    }

    /**
     * Validate items
     * @param array $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validateItems(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'items')) {
            return;
        }

        // Get additional items
        $tupleValidation = is_array($schema->getSchema()->items);
        $additionalsDefault = true;
        $additionalItems = $additionalsDefault;

        if (property_exists($schema->getSchema(), 'additionalItems')) {
            $additionalItems = $schema->getSchema()->additionalItems->getSchema();
        }

        // Check each data item
        foreach ($data as $key => $item) {
            // Tuple validation will check each item by mapped key
            if ($tupleValidation) {
                if (array_key_exists($key, $schema->getSchema()->items)) {
                    $data[$key] = $this->validate($item, $schema->getSchema()->items[$key]);
                } elseif (!$additionalItems) {
                    // If additional items are not allowed the will fail if there is an extra items
                    throw new ValidationException(sprintf(
                        'Array item with key "%d" is not declared in tuple item list (%s)',
                        $key,
                        $schema->getPath() . '/items'
                    ));
                }
            } else {
                // Single schema validation will check each item
                $data[$key] = $this->validate($item, $schema->getSchema()->items);
            }
        }
    }

    /**
     * Validate contains
     * @param array $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validateContains(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'contains')) {
            return;
        }

        // Check for single match
        foreach ($data as $key => $item) {
            try {
                $data[$key] = $this->validate($item, $schema->getSchema()->contains);
                return;
            } catch (ValidationException $e) {
                // Do nothing
            }
        }

        throw new ValidationException('ok');
    }

    /**
     * Validate additional items
     * @param array $data
     * @param Schema $schema
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validateAdditionalItems(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'additionalItems')) {
            return;
        }

        // Get items type
        $itemsType = property_exists($schema->getSchema(), 'items') ? gettype($schema->getSchema()->items) : false;

        // If "items" schema is true then additionalItems check will be skip
        if ($itemsType === 'object') {
            if ($schema->getSchema()->items->getSchema() === true) {
                return;
            }
        }

        // From which key current items will end
        $currentItemsEnd = 0;
        if ($itemsType === 'array') {
            $currentItemsEnd = count($schema->getSchema()->items);
        }

        // Check additional items
        $dataLength = count($data);
        for ($i = $currentItemsEnd; $i < $dataLength; $i++) {
            $data[$i] = $this->validate($data[$i], $schema->getSchema()->additionalItems);
        }
    }

    /**
     * Validate minItems
     * @param array $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMinItems(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'minItems')) {
            return;
        }

        // Check array length
        if (count($data) < $schema->getSchema()->minItems) {
            throw new ValidationException(sprintf(
                'Min items are "%s" but your array contains "%s" items (%s)',
                $schema->getSchema()->minItems,
                count($data),
                $schema->getPath() . '/minItems'
            ));
        }
    }

    /**
     * Validate maxItems
     * @param array $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMaxItems(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'maxItems')) {
            return;
        }

        // Check array length
        if (count($data) > $schema->getSchema()->maxItems) {
            throw new ValidationException(sprintf(
                'Max items are "%s" but your array contains "%s" items (%s)',
                $schema->getSchema()->maxItems,
                count($data),
                $schema->getPath() . '/maxItems'
            ));
        }
    }

    /**
     * Validate uniqueItems
     * @param $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateUniqueItems(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->getSchema(), 'uniqueItems')) {
            return;
        }

        // Get items length
        $itemsLength = count($data);

        // Collect all values as string/integer and check the unique length
        $tmp = [];
        foreach ($data as $item) {
            if (in_array(gettype($item), ['integer', 'string'])) {
                $tmp[] = $item;
            } else {
                $tmp[] = serialize($item);
            }
        }

        // Check the values length
        if ($itemsLength !== count(array_count_values($tmp))) {
            throw new ValidationException(sprintf(
                'You have value array which contains non unique values (%s)',
                $schema->getPath() . '/type'
            ));
        }
    }
}
