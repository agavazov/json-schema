<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Check
{
    /**
     * String validation
     * @param $data
     * @return bool
     */
    public static function string($data): bool
    {
        return is_string($data);
    }

    /**
     * Number validation
     * @param $data
     * @return bool
     */
    public static function number($data): bool
    {
        return is_double($data) || is_integer($data);
    }

    /**
     * Integer validation
     * @param $data
     * @return bool
     */
    public static function integer($data): bool
    {
        // 1.0 is valid integer
        if (is_double($data) && ($data - (int)$data == 0)) {
            return true;
        }

        return is_integer($data);
    }

    /**
     * Object validation
     * @param $data
     * @return bool
     */
    public static function object($data): bool
    {
        return is_object($data);
    }

    /**
     * Array validation
     * @param $data
     * @return bool
     */
    public static function array($data): bool
    {
        return is_array($data);
    }

    /**
     * Boolean validation
     * @param $data
     * @return bool
     */
    public static function boolean($data): bool
    {
        return is_bool($data);
    }

    /**
     * Validate date and time
     * @param string $dateTime
     * @return bool
     */
    public static function dateTime(string $dateTime): bool
    {
        //$regex = '/^([0-9]+)-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])[Tt]([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9]|60)(\.[0-9]+)?(([Zz])|([\+|\-]([01][0-9]|2[0-3]):[0-5][0-9]))$/'; // with 60 sec included
        $regex = '/^([0-9]+)-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])[Tt]([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])(\.[0-9]+)?(([Zz])|([\+|\-]([01][0-9]|2[0-3]):[0-5][0-9]))$/';

        return (bool)preg_match($regex, $dateTime);
    }

    /**
     * Validate time string
     * @param string $time
     * @return bool
     */
    public static function time(string $time): bool
    {
        $regex = '/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])(\.[0-9]+)?(([Zz])|([\+|\-]([01][0-9]|2[0-3]):[0-5][0-9]))$/';

        return (bool)preg_match($regex, $time);
    }

    /**
     * Date validation
     * @param string $date
     * @return bool
     */
    public static function date(string $date): bool
    {
        $regex = '/^([0-9]+)-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$/';

        return (bool)preg_match($regex, $date);
    }

    /**
     * Email validation
     * @param string $email
     * @return bool
     */
    public static function email(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * IDN Email validation
     * @param string $email
     * @return bool
     */
    public static function idnEmail(string $email): bool
    {
        $email = implode('@', array_map(function ($fragment) {
            return idn_to_ascii($fragment, 0, INTL_IDNA_VARIANT_UTS46);
        }, explode('@', $email)));

        return self::email($email);
    }

    /**
     * Validate hostname
     * @param string $hostname
     * @return bool
     */
    public static function hostname(string $hostname): bool
    {
        $regex = '/^(([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9]){1,63}\.)*([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9]){1,63}$/i';

        if (preg_match($regex, $hostname)) {
            return true;
        }

        if (preg_match('/^\[(?<ip>[^\]]+)\]$/', $hostname, $m)) {
            $hostname = $m['ip'];
        }

        return (bool)filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * IDN host check
     * @param string $hostname
     * @return bool
     */
    public static function idnHostname(string $hostname): bool
    {
        // Hangul single dot can be in the beginning but not after that
        $hangulDot = mb_strpos($hostname, '〮');
        if ($hangulDot !== false && $hangulDot !== 0) {
            return false;
        }

        // IDN check
        $hostname = idn_to_ascii($hostname, 0, INTL_IDNA_VARIANT_UTS46);
        return self::hostname((string)$hostname);
    }

    public static function ipv4(string $ipAddress): bool
    {
        return (bool)filter_var($ipAddress, FILTER_VALIDATE_IP);
    }

    public static function ipv6(string $ipAddress): bool
    {
        return (bool)filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * URI validation
     * @param string $uri
     * @return bool
     */
    public static function uri(string $uri): bool
    {
        $uriData = (object)parse_url($uri);

        if (!$uriData) {
            return false;
        }

        if (!isset($uriData->scheme) || $uriData->scheme === '') {
            return false;
        }

        if (isset($uriData->host) && !self::hostname($uriData->host)) {
            return false;
        }

        if (isset($uriData->path) && !self::path($uriData->path)) {
            return false;
        }

        if (isset($uriData->fragment) && !self::fragment($uriData->fragment)) {
            return false;
        }

        return true;
    }

    /**
     * Validate URI reference
     * @param string $uri
     * @return bool
     */
    public static function uriReference(string $uri): bool
    {
        $uriData = (object)parse_url($uri);

        if (!$uriData) {
            return false;
        }

        if (isset($uriData->host) && !self::hostname($uriData->host)) {
            return false;
        }

        if (isset($uriData->path) && !self::path($uriData->path)) {
            return false;
        }

        if (isset($uriData->fragment) && !self::fragment($uriData->fragment)) {
            return false;
        }

        return true;
    }

    /**
     * Validate IRI address
     * @param string $iri
     * @return bool
     */
    public static function iri(string $iri): bool
    {
        $iriData = (object)parse_url($iri);

        if (!$iriData) {
            return false;
        }

        foreach (['host', 'path', 'fragment'] as $component) {
            if (isset($iriData->{$component})) {
                $iriData->{$component} = idn_to_ascii($iriData->{$component}, 0, INTL_IDNA_VARIANT_UTS46);
            }
        }

        $newIri = self::buildUrl($iriData);

        return self::uri($newIri);
    }

    public static function iriReference(string $iriReference): bool
    {
        $iriData = (object)parse_url($iriReference);

        if (!$iriData) {
            return false;
        }

        foreach (['host', 'path', 'fragment'] as $component) {
            if (isset($iriData->{$component})) {
                $iriData->{$component} = idn_to_ascii($iriData->{$component}, 0, INTL_IDNA_VARIANT_UTS46);
            }
        }

        $newIri = self::buildUrl($iriData);

        return self::uriReference($newIri);
    }

    /**
     * Validate URI Template
     * @param string $uriTemplate
     * @return bool
     */
    public static function uriTemplate(string $uriTemplate): bool
    {
        if (substr_count($uriTemplate, '{') !== substr_count($uriTemplate, '}')) {
            return false;
        }

        $uriData = parse_url($uriTemplate);

        if (!empty($uriData['path'])) {
            $fixedPath = str_replace(['{', '}'], '', $uriData['path']);
            $uriTemplate = str_replace($uriData['path'], $fixedPath, $uriTemplate);
        }

        if (isset($uriData['scheme'])) {
            return self::uri($uriTemplate);
        }

        if (isset($uriData['path'])) {
            return self::path($uriTemplate);
        }

        return false;
    }

    /**
     * Validate JSON pointer
     * @param string $jsonPointer
     * @return bool
     */
    public static function jsonPointer(string $jsonPointer): bool
    {
        if ($jsonPointer !== '' && !(bool)preg_match('~^(?:/|(?:/[^/#]*)*)$~', $jsonPointer)) {
            return false;
        }

        if (preg_match('/~([^01]|$)/', $jsonPointer)) {
            return false;
        }

        return true;
    }

    /**
     * Validate relative JSON pointer
     * @param string $relativeJsonPointer
     * @return bool
     */
    public static function relativeJsonPointer(string $relativeJsonPointer): bool
    {
        if (!preg_match('~^(0|[1-9][0-9]*)((?:/[^/#]+)*)(#?)$~', $relativeJsonPointer)) {
            return false;
        }

        if (preg_match('/~([^01]|$)/', $relativeJsonPointer)) {
            return false;
        }

        return true;
    }

    /**
     * Validate regex
     * @param string $regex
     * @return bool
     */
    public static function regex(string $regex): bool
    {
        if (substr($regex, -2) === '\Z' || substr($regex, 0, 2) === '\A') {
            return false;
        }

        return @preg_match('/' . $regex . '/', '') !== false;
    }

    /**
     * Path validation
     * @param string $path
     * @return bool
     */
    public static function path(string $path): bool
    {
        return (bool)preg_match('/^(?:(%[0-9a-f]{2})|[a-z0-9\/:@\-._~\!\$&\'\(\)*+,;=])*$/i', $path);
    }

    /**
     * Fragment validation
     * @param string $fragment
     * @return bool
     */
    public static function fragment(string $fragment): bool
    {
        return (bool)preg_match('/^(?:(%[0-9a-f]{2})|[a-z0-9\/:@\-._~\!\$&\'\(\)*+,;=])*$/i', $fragment);
    }

    /**
     * Combine url parts
     * @param object $components
     * @return string
     */
    protected static function buildUrl(object $components): string
    {
        if (empty($components)) {
            return '';
        }

        $uri = $components->path ?? '/';

        if (isset($components->query)) {
            $uri .= '?' . $components->query;
        }

        if (isset($components->fragment)) {
            $uri .= '#' . $components->fragment;
        }

        if (isset($components->host)) {
            $authority = $components->host;

            if (isset($components->port)) {
                $authority .= ':' . $components->port;
            }

            if (isset($components->user)) {
                $authority = $components->user . '@' . $authority;
            }

            if ($uri !== '') {
                if ($uri[0] !== '/' && $uri[0] !== '?' && $uri[0] !== '#') {
                    $uri = '/' . $uri;
                }
            }

            $uri = '//' . $authority . $uri;
        }

        if (isset($components->scheme)) {
            if ('file' === $components->scheme) {
                $uri = '//' . $uri;
            }
            return $components->scheme . ':' . $uri;
        }

        return $uri !== false ? $uri : '';
    }
}
