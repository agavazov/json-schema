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

    public function validate($data, object $schemaObject, int $mode = 0)
    {
        // Make schema variable to be instance of Schema class
        {
            $formatsMap = (object)array_map(function (object $item) {
                return $item->type;
            }, (array)$this->formats);

            $schema = new Schema($schemaObject, $formatsMap);
        }

        $this->validateType($data, $schema, ($mode & self::MODE_CAST) === self::MODE_CAST);
        $this->validateFormat($data, $schema);

        return $data;
    }

    public function validateType(&$data, Schema $schema, $cast = false): void
    {
        if (!property_exists($schema->storage(), 'type')) {
            return;
        }

        // Cast only if there is one type
        if ($cast && count($schema->storage()->type) === 1) {
            $castTo = $schema->storage()->type[0];

            $data = call_user_func_array(__NAMESPACE__ . '\\Cast::' . $castTo, [$data]);
        }

        // Decide which format to choose
        $dataType = gettype($data);
        $matchType = null;

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

        if ($matchType !== null) {
            $schema->setMainType((string)$matchType);

            if (!call_user_func_array(__NAMESPACE__ . '\\Check::' . $schema->getMainType(), [$data])) {
                throw new ValidationException(sprintf(
                    'Provided data type "%s" does not validated with schema type "%s" (%s)',
                    $dataType,
                    $schema->getMainType(),
                    $schema->storage()->_path
                ));
            }
        } else {
            throw new ValidationException(sprintf(
                'There is provided schema with types "%s" which not match with the data type "%s" (%s)',
                implode(';', $schema->storage()->type),
                $dataType,
                $schema->storage()->_path . '/type'
            ));
        }
    }

    public function validateFormat(&$data, Schema $schema): void
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
                $schema->storage()->_path . '/format'
            ));
        }
    }
}
