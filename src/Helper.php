<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Helper
{
    /**
     * Combine url parts
     * @param object $components
     * @return string
     */
    public static function buildUrl(object $components): string
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

    /**
     * Compare two variables
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    public static function compare($a, $b): bool
    {
        $type = gettype($a);

        if ($type !== gettype($b)) {
            return false;
        }

        // Check by type
        switch ($type) {
            case 'object':
                {
                    // Check keys
                    if (!self::compare(array_keys((array)$a), array_keys((array)$b))) {
                        return false;
                    }

                    // Check values
                    foreach ($a as $key => $aValue) {
                        if (!self::compare($aValue, $b->{$key})) {
                            return false;
                        }
                    }

                    return true;
                }
            case 'array':
                {
                    // If both are associative array they will be casted as objects
                    if (array_values($a) !== $a && array_values($b) !== $b) {
                        return self::compare((object)$a, (object)$b);
                    }

                    // Check arrays length
                    if (count($a) !== count($b)) {
                        return false;
                    }

                    // Sort & compare 1st level
                    $a = self::sortFirstLevelArrayValues($a);
                    $b = self::sortFirstLevelArrayValues($b);

                    // Compare by sorted keys
                    foreach ($a as $key => $aValue) {
                        if (self::compare($aValue, $b[$key]) === false) {
                            return false;
                        }
                    }

                    // Of there are no difference return true
                    return true;
                }
            default:
                {
                    return $a === $b;
                }
        }
    }

    /**
     * Sort 1st level array values
     * @param $data
     * @return array
     */
    public static function sortFirstLevelArrayValues($data)
    {
        $tmp = (object)[];

        foreach ($data as $key => &$item) {
            if (is_object($item)) {
                $tmp->{$key} = $item;

                $item = array_keys((array)$item);
                asort($item);
            }
        }

        asort($data);

        foreach ($tmp as $key => $value) {
            $data[$key] = $value;
        }

        return array_values($data);
    }
}