<?php
declare(strict_types=1);

namespace FrontLayer\JsonSchema;

class Cast
{
    /**
     * Try to cast income data to string
     * @param mixed $data
     * @return mixed
     */
    public static function string($data)
    {
        if (in_array(gettype($data), ['integer', 'double'])) {
            return (string)$data;
        }

        return $data;
    }

    /**
     * Try to cast income data to number
     * @param mixed $data
     * @return mixed
     */
    public static function number($data)
    {
        if (!is_double($data) && is_numeric($data)) {
            return (double)$data;
        }

        return $data;
    }

    /**
     * Try to cast income data to integer
     * @param mixed $data
     * @return mixed
     */
    public static function integer($data)
    {
        if (!is_integer($data) && is_numeric($data)) {
            if (strstr((string)$data, '.') !== false) {
                return (double)$data;
            }

            return (int)$data;
        }

        return $data;
    }

    /**
     * Try to cast income data to object
     * @param mixed $data
     * @return mixed
     */
    public static function object($data)
    {
        if (is_string($data)) {
            $newData = json_decode($data);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_object($newData) || $newData === null) {
                    return $newData;
                }
            }
        }

        return $data;
    }

    /**
     * Try to cast income data to array
     * @param mixed $data
     * @return mixed
     */
    public static function array($data)
    {
        if (is_string($data)) {
            $newData = json_decode($data);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($newData) || $newData === null) {
                    return $newData;
                }
            }
        }

        return $data;
    }

    /**
     * Try to cast income data to boolean
     * @param mixed $data
     * @return mixed
     */
    public static function boolean($data)
    {
        switch (gettype($data)) {
            case 'integer':
                {
                    if ($data === 1) {
                        return true;
                    } elseif ($data === 0) {
                        return false;
                    }

                    break;
                }
            case 'string':
                {
                    if ($data === '1') {
                        return true;
                    } elseif ($data === '0') {
                        return false;
                    } elseif (strtolower($data) === 'true') {
                        return true;
                    } elseif (strtolower($data) === 'false') {
                        return false;
                    }

                    break;
                }
        }

        return $data;
    }
}