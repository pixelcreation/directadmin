<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Utility;

use GuzzleHttp\Psr7\Query;

/**
 * Static helper class for various conversion operations.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class Conversion
{
    /**
     * Reduces any input to an ON/OFF value.
     *
     * @param mixed      $input   Data to convert
     * @param bool|mixed $default Fallback to use if $input is NULL
     *
     * @return string Either ON or OFF
     */
    public static function onOff(mixed $input, mixed $default = false): string
    {
        return self::toBool($input, $default) ? 'ON' : 'OFF';
    }

    /**
     * Expands a single option to its unlimited counterpart if NULL or literal 'unlimited'.
     *
     * @param array  $options Array of options to process
     * @param string $key     Key of the item to process
     */
    protected static function processUnlimitedOption(array &$options, string $key)
    {
        $uKey = "u$key";
        unset($options[$uKey]);
        if (array_key_exists($key, $options) && ($options[$key] === 'unlimited' || !isset($options[$key]))) {
            $options[$uKey] = 'ON';
        }
    }

    /**
     * Detects package/domain options that can be unlimited and sets them accordingly.
     *
     *
     * @return array Modified array
     */
    public static function processUnlimitedOptions(array $options): array
    {
        foreach ([
                     'bandwidth',
                     'domainptr',
                     'ftp',
                     'mysql',
                     'nemailf',
                     'nemailml',
                     'nemailr',
                     'nemails',
                     'nsubdomains',
                     'quota',
                     'vdomains',
                 ] as $key) {
            self::processUnlimitedOption($options, $key);
        }
        return $options;
    }

    /**
     * Processes DirectAdmin style encoded responses into a sane array.
     *
     *
     */
    public static function responseToArray(string $data): array
    {
        $unescaped = preg_replace_callback('/&#([0-9]{2})/', fn($val) => chr($val[1]), $data);
        return Query::parse($unescaped);
    }

    /**
     * Ensures a DA-style response element is wrapped properly as an array.
     *
     * @param mixed $result Messy input
     *
     * @return array Sane output
     */
    public static function sanitizeArray(mixed $result): array
    {
        if ((is_countable($result) ? count($result) : 0) == 1 && isset($result['list[]'])) {
            $result = $result['list[]'];
        }
        return is_array($result) ? $result : [$result];
    }

    /**
     * Converts values like ON, YES etc. to proper boolean variables.
     *
     * @param mixed      $value   Value to be converted
     * @param bool|mixed $default Value to use if $value is NULL
     */
    public static function toBool(mixed $value, mixed $default = false): bool
    {
        return filter_var($value ?? $default, FILTER_VALIDATE_BOOLEAN);
    }
}
