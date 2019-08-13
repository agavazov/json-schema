<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Formats
{
    /**
     * Formats constructor.
     */
    public function __construct()
    {
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

    /**
     * Register new format
     * @param string $formatId
     * @param string $type
     * @param callable $validation
     */
    public function registerFormat(string $formatId, string $type, callable $validation): void
    {
        $this->{$formatId} = (object)[
            'type' => $type,
            'validation' => $validation
        ];
    }
}
