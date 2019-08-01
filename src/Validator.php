<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Validator
{
    const MODE_CAST = 1;
    const MODE_DISALLOW_ADDITIONALS_BY_DEFAULT = 2; // @todo

    protected $formats;

    public function __construct()
    {
        $this->formats = (object)[]; // @todo move it to class body when PHP is ready for this syntax

        $this->registerFormat('date-time', 'string', __NAMESPACE__ . '\\Check::dateTime');
        $this->registerFormat('time', 'string', __NAMESPACE__ . '\\Check::time');
        $this->registerFormat('date', 'string', __NAMESPACE__ . '\\Check::date');
        $this->registerFormat('email', 'string', __NAMESPACE__ . '\\Check::email');
        $this->registerFormat('idn-email', 'string', __NAMESPACE__ . '\\Check::idnEmail');
        $this->registerFormat('hostname', 'string', __NAMESPACE__ . '\\Check::hostname');
        $this->registerFormat('idn-hostname', 'string', __NAMESPACE__ . '\\Check::idnHostname');
        $this->registerFormat('ipv4', 'string', __NAMESPACE__ . '\\Check::ipv4');
        $this->registerFormat('ipv6', 'string', __NAMESPACE__ . '\\Check::ipv6');
        $this->registerFormat('uri', 'string', __NAMESPACE__ . '\\Check::uri');
        $this->registerFormat('uri-reference', 'string', __NAMESPACE__ . '\\Check::uriReference');
        $this->registerFormat('iri', 'string', __NAMESPACE__ . '\\Check::iri');
        $this->registerFormat('iri-reference', 'string', __NAMESPACE__ . '\\Check::iriReference');
        $this->registerFormat('uri-template', 'string', __NAMESPACE__ . '\\Check::uriTemplate');
        $this->registerFormat('json-pointer', 'string', __NAMESPACE__ . '\\Check::jsonPointer');
        $this->registerFormat('relative-json-pointer', 'string', __NAMESPACE__ . '\\Check::relativeJsonPointer');
        $this->registerFormat('regex', 'string', __NAMESPACE__ . '\\Check::regex');
    }

    public function registerFormat(string $formatId, string $type, callable $validation): void
    {
        $this->formats->{$formatId} = (object)[
            'type' => $type,
            'validation' => $validation
        ];
    }

    /**
     * Validate data against the schema
     * @param $data
     * @param Schema|object|bool $schema
     * @param int $mode
     * @return mixed
     * @throws SchemaException
     * @throws ValidationException
     */
    public function validate($data, $schema, int $mode = 0)
    {
        // Make schema variable to be instance of Schema class
        $formatsMap = (object)array_map(function (object $item) {
            return $item->type;
        }, (array)$this->formats);

        // Transform to schema
        if (!($schema instanceof Schema)) {
            $schema = new Schema($schema, $formatsMap);
        }

        // If the whole schema is boolean
        if (is_bool($schema->storage())) {
            // If schema is "false" then it will disallow everything
            if ($schema->storage() === false) {
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
        $this->validateType($data, $schema, ($mode & self::MODE_CAST) === self::MODE_CAST);
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

        switch ($schema->getMainType()) {
            case 'string':
                {
                    $this->validateMinLength($data, $schema);
                    $this->validateMaxLength($data, $schema);
                    $this->validatePattern($data, $schema);
                    $this->validateContentEncoding($data, $schema);
                    $this->validateContentMediaType($data, $schema);
                    break;
                }
            case 'number':
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
     * @param bool $cast
     * @throws ValidationException
     */
    protected function validateType(&$data, Schema $schema, $cast = false): void
    {
        // When there is no type the validation will allow everything
        if (!property_exists($schema->storage(), 'type') || count($schema->storage()->type) === 0) {
            return;
        }

        // Fix type cases
        foreach ($schema->storage()->type as $key => $value) {
            $schema->storage()->type[$key] = strtolower($value);
        }

        // Cast only if there is one type
        if ($cast && count($schema->storage()->type) === 1) {
            $castTo = $schema->storage()->type[0];

            $data = call_user_func_array(__NAMESPACE__ . '\\Cast::' . $castTo, [$data]);
        }

        // Decide which format to choose
        $dataType = strtolower(gettype($data));
        $matchType = false;

        // Special integer/number cases and mixes
        if ($dataType === 'double' || $dataType === 'float') {
            if (in_array('integer', $schema->storage()->type) && Check::integer($data)) {
                $dataType = 'integer';
            } else {
                $dataType = 'number';
            }
        } elseif ($dataType === 'integer') {
            if (!in_array('integer', $schema->storage()->type)) {
                $dataType = 'number';
            }
        }

        // Check for type match
        foreach ($schema->storage()->type as $type) {
            if ($dataType === $type) {
                $matchType = $type;
                break;
            }
        }

        if ($matchType !== false) {
            $schema->setMainType((string)$matchType);

            if (!call_user_func_array(__NAMESPACE__ . '\\Check::' . $schema->getMainType(), [$data])) {
                throw new ValidationException(sprintf(
                    'Provided data type "%s" does not validated with schema type "%s" (%s)',
                    $dataType,
                    $schema->getMainType(),
                    $schema->getPath()
                ));
            }
        } else {
            throw new ValidationException(sprintf(
                'There is provided schema with type/s "%s" which not match with the data type "%s" (%s)',
                implode(';', $schema->storage()->type),
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
    protected function validateFormat($data, Schema $schema): void
    {
        if (!property_exists($schema->storage(), 'format')) {
            return;
        }

        $format = $schema->storage()->format;
        $isValid = call_user_func_array($this->formats->{$format}->validation, [$data]);

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
    protected function validateIf($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'if')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateThen($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'then')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateElse($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'else')) {
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
    protected function validateConst($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'const')) {
            return;
        }

        if (Helper::compare($data, $schema->storage()->const)) {
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
    protected function validateEnum($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'enum')) {
            return;
        }

        foreach ($schema->storage()->enum as $enumData) {
            if (Helper::compare($data, $enumData)) {
                return;
            }
        }

        throw new ValidationException(sprintf(
            'Non of provided "%d" enums matches with the provided data (%s)',
            count($schema->storage()->enum),
            $schema->getPath() . '/enum'
        ));
    }

    /**
     * @todo
     */
    protected function validateAllOf($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'allOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateAnyOf($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'anyOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateOneOf($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'oneOf')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateNot($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'not')) {
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
    protected function validateMinLength(string $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'minLength')) {
            return;
        }

        // Check string min length
        if (mb_strlen($data) < $schema->storage()->minLength) {
            throw new ValidationException(sprintf(
                'Min length is "%s" but your string is with "%s" (%s)',
                $schema->storage()->minLength,
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
    protected function validateMaxLength(string $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'maxLength')) {
            return;
        }

        // Check string max length
        if (mb_strlen($data) > $schema->storage()->maxLength) {
            throw new ValidationException(sprintf(
                'Max length is "%s" but your string is with "%s" (%s)',
                $schema->storage()->maxLength,
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
    protected function validatePattern(string $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'pattern')) {
            return;
        }

        // Check pattern match
        if (!preg_match('/' . $schema->storage()->pattern . '/u', $data)) {
            throw new ValidationException(sprintf(
                'Pattern "%s" not match with the input data (%s)',
                $schema->storage()->pattern,
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
        if (!property_exists($schema->storage(), 'contentEncoding')) {
            return;
        }

        $encoded = $data;

        switch ($schema->storage()->contentEncoding) {
            case 'base64':
                {
                    $encoded = base64_decode($encoded, true);
                    break;
                }
        }

        if ($encoded === false) {
            throw new ValidationException(sprintf(
                'The data can`t be encoded by "%d" (%s)',
                $schema->storage()->contentEncoding,
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
    protected function validateContentMediaType(string $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'contentMediaType')) {
            return;
        }

        $isValid = true;

        switch ($schema->storage()->contentMediaType) {
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
                $schema->storage()->contentMediaType,
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
    protected function validateMultipleOf($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'multipleOf')) {
            return;
        }

        $number = $data;
        $multipleOf = $schema->storage()->multipleOf;

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
                $schema->storage()->multipleOf,
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
    protected function validateMinimum($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'minimum')) {
            return;
        }

        // Check number value
        if ($data < $schema->storage()->minimum) {
            throw new ValidationException(sprintf(
                'Min value is "%d" but your number is with value "%d" (%s)',
                $schema->storage()->minimum,
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
    protected function validateMaximum($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'maximum')) {
            return;
        }

        // Check number value
        if ($data > $schema->storage()->maximum) {
            throw new ValidationException(sprintf(
                'Max value is "%d" but your number is with value "%d" (%s)',
                $schema->storage()->maximum,
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
    protected function validateExclusiveMinimum($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'exclusiveMinimum')) {
            return;
        }

        // Check number value
        if ($data <= $schema->storage()->exclusiveMinimum) {
            throw new ValidationException(sprintf(
                'Exclusive minimum value is "%d" but your number is with value "%d" (%s)',
                $schema->storage()->exclusiveMinimum,
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
    protected function validateExclusiveMaximum($data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'exclusiveMaximum')) {
            return;
        }

        // Check number value
        if ($data >= $schema->storage()->exclusiveMaximum) {
            throw new ValidationException(sprintf(
                'Exclusive maximum value is "%d" but your number is with value "%d" (%s)',
                $schema->storage()->exclusiveMaximum,
                $data,
                $schema->getPath() . '/exclusiveMaximum'
            ));
        }
    }

    /**
     * @todo
     */
    protected function validateProperties(object $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'properties')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateAdditionalProperties(object $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'additionalProperties')) {
            return;
        }

        // @todo
    }

    /**
     * Validate required
     * @param object $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateRequired(object $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'required')) {
            return;
        }

        // Check for each property
        foreach ($schema->storage()->required as $required) {
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
    protected function validatePropertyNames(object $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'propertyNames')) {
            return;
        }

        foreach ($data as $dataKey => $dataValue) {
            $data->{$dataKey} = $this->validate($dataKey, $schema->storage()->propertyNames);
        }
    }

    /**
     * Validate minProperties
     * @param object $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMinProperties(object $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'minProperties')) {
            return;
        }

        // Check object length
        if (count((array)$data) < $schema->storage()->minProperties) {
            throw new ValidationException(sprintf(
                'Min properties are "%s" but your object contains "%s" properties (%s)',
                $schema->storage()->minProperties,
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
    protected function validateMaxProperties(object $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'maxProperties')) {
            return;
        }

        // Check object length
        if (count((array)$data) > $schema->storage()->maxProperties) {
            throw new ValidationException(sprintf(
                'Max properties are "%s" but your object contains "%s" properties (%s)',
                $schema->storage()->maxProperties,
                count((array)$data),
                $schema->getPath() . '/maxProperties'
            ));
        }
    }

    /**
     * @todo
     */
    protected function validateDependencies(object $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'dependencies')) {
            return;
        }

        // @todo
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
        if (!property_exists($schema->storage(), 'patternProperties')) {
            return;
        }

        foreach ($schema->storage()->patternProperties as $pattern => $propertySchema) {
            /* @var $propertySchema Schema */

            foreach ($data as $dataKey => $dataValue) {
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
     * @param bool $additionalsDefault
     * @throws SchemaException
     * @throws ValidationException
     */
    protected function validateItems(array $data, Schema $schema, $additionalsDefault = true): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'items')) {
            return;
        }

        // Get additional items
        $tupleValidation = is_array($schema->storage()->items);
        $additionalValues = $additionalsDefault;

        if (property_exists($schema->storage(), 'additionalItems')) {
            $additionalValues = $schema->storage()->additionalItems->storage();
        }

        // Check each data item
        foreach ($data as $key => $item) {
            // Tuple validation will check each item by mapped key
            if ($tupleValidation) {
                if (array_key_exists($key, $schema->storage()->items)) {
                    $this->validate($item, $schema->storage()->items[$key]);
                } elseif (!$additionalValues) {
                    // If additional items are not allowed the will fail if there is an extra items
                    throw new ValidationException(sprintf(
                        'Array item with key "%d" is not declared in tuple item list (%s)',
                        $key,
                        $schema->getPath() . '/items'
                    ));
                }
            } else {
                // Single schema validation will check each item
                $this->validate($item, $schema->storage()->items);
            }
        }
    }

    /**
     * @todo
     */
    protected function validateContains(array $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'contains')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateAdditionalItems(array $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'additionalItems')) {
            return;
        }

        // @todo
    }

    /**
     * Validate minItems
     * @param array $data
     * @param Schema $schema
     * @throws ValidationException
     */
    protected function validateMinItems(array $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'minItems')) {
            return;
        }

        // Check array length
        if (count($data) < $schema->storage()->minItems) {
            throw new ValidationException(sprintf(
                'Min items are "%s" but your array contains "%s" items (%s)',
                $schema->storage()->minItems,
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
    protected function validateMaxItems(array $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'maxItems')) {
            return;
        }

        // Check array length
        if (count($data) > $schema->storage()->maxItems) {
            throw new ValidationException(sprintf(
                'Max items are "%s" but your array contains "%s" items (%s)',
                $schema->storage()->maxItems,
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
    protected function validateUniqueItems(array $data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'uniqueItems')) {
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
