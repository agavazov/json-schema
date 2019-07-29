<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Validator
{
    const MODE_CAST = 1;

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

    public function validate($data, $schema, int $mode = 0)
    {
        // Make schema variable to be instance of Schema class
        $formatsMap = (object)array_map(function (object $item) {
            return $item->type;
        }, (array)$this->formats);

        $schema = new Schema($schema, $formatsMap);

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
                    $this->validateContentMediaType($data, $schema);
                    $this->validateContentEncoding($data, $schema);
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

    protected function validateType(&$data, Schema $schema, $cast = false): void
    {
        // If the whole schema is boolean
        if (is_bool($schema->storage())) {
            // If schema is "false" then it will disallow everything
            if ($schema->storage() === true) {
                throw new ValidationException(sprintf(
                    'Provided schema is with value "false" which means it will disallow everything (%s)',
                    $schema->getPath()
                ));
            } else {
                return; // When is "true" then it will allow everything
            }
        }

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

    protected function validateFormat(&$data, Schema $schema): void
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
    protected function validateIf(&$data, Schema $schema): void
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
    protected function validateThen(&$data, Schema $schema): void
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
    protected function validateElse(&$data, Schema $schema): void
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
    protected function validateConst(&$data, Schema $schema): void
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
    protected function validateEnum(&$data, Schema $schema): void
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
    protected function validateAllOf(&$data, Schema $schema): void
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
    protected function validateAnyOf(&$data, Schema $schema): void
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
    protected function validateOneOf(&$data, Schema $schema): void
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
    protected function validateNot(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'not')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateMinLength(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'minLength')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateMaxLength(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'maxLength')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validatePattern(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'pattern')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateContentMediaType(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'contentMediaType')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateContentEncoding(string &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'contentEncoding')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateMultipleOf(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'multipleOf')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateMinimum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'minimum')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateExclusiveMinimum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'exclusiveMinimum')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateMaximum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'maximum')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateExclusiveMaximum(&$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'exclusiveMaximum')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateProperties(object &$data, Schema $schema): void
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
    protected function validateAdditionalProperties(object &$data, Schema $schema): void
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
    protected function validateRequired(object &$data, Schema $schema): void
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
     * @todo
     */
    protected function validatePropertyNames(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'propertyNames')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateMinProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'minProperties')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateMaxProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'maxProperties')) {
            return;
        }

        // @todo ok
    }

    /**
     * @todo
     */
    protected function validateDependencies(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'dependencies')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validatePatternProperties(object &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'patternProperties')) {
            return;
        }

        // @todo
    }

    /**
     * @todo
     */
    protected function validateItems(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'items')) {
            return;
        }

        // @todo

        // Check for dependencies
        if (property_exists($schema->storage(), 'additionalItems')) {
            $this->validateAdditionalItems($data, $schema);
        }
    }

    /**
     * @todo
     */
    protected function validateContains(array &$data, Schema $schema): void
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
    protected function validateAdditionalItems(array &$data, Schema $schema): void
    {
        // Check exists
        if (!property_exists($schema->storage(), 'additionalItems')) {
            return;
        }

        // Check for dependencies
        if (property_exists($schema->storage(), 'items')) {
            // @todo
        }

        // @todo
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
    protected function validateMaxItems(array &$data, Schema $schema): void
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
    protected function validateUniqueItems(array &$data, Schema $schema): void
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
