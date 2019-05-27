<?php
/**
 * Validation and Reusable Functions
 *
 * @package DataCruncher
 * @subpackage Config
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
declare(strict_types=1);
namespace mfmbarber\DataCruncher\Config;

use mfmbarber\DataCruncher\Helpers\Interfaces\DataInterface as DataInterface;
use mfmbarber\DataCruncher\Exceptions as CSV_Exceptions;

class Validation
{
    public const CONDITIONS = [
            'EQUALS',
            'GREATER',
            'LESS',
            'NOT',
            'AFTER',
            'BEFORE',
            'ON',
            'BETWEEN',
            'NOT_BETWEEN',
            'EMPTY',
            'NOT_EMPTY',
            'CONTAINS',
            'IN'
    ];

    public const CLI_LOGO = '
        88888888ba,                  ,ad8888ba,   88           88
        88      `"8b                d8"\'    `"8b  88           88
        88        `8b              d8\'            88           88
        88         88  ,adPPYYba,  88             88           88
        88         88  ""     `Y8  88             88           88
        88         8P  ,adPPPPP88  Y8,            88           88
        88      .a8P   88,    ,88   Y8a.    .a8P  88           88
        88888888Y"\'    `"8bbdP"Y8    `"Y8888Y"\'   88888888888  88
    ';
    /**
     * Checks to see if this is a normal numerical array and not associative
     * @param mixed $arr     The array to check (this is type hinted)
     * @param int   $minSize The minimum size for the array - this defaults to 0
     *
     * @return bool
    **/
    public static function isNormalArray($arr, int $minSize = 1) : bool
    {
        return self::isArray($arr, $minSize) && (bool) !count(array_filter(array_keys($arr), 'is_string'));
    }
    /**
     * Checks to see if this an associative array and not an integer array
     * @param mixed $arr     The array to check (this is type hinted)
     * @param int   $minSize The minimum size for the array - this defaults to 0
     *
     * @return bool
    **/
    public static function isAssociativeArray($arr, int $minSize = 1) : bool
    {
        return self::isArray($arr, $minSize) && (count(array_filter(array_keys($arr), 'is_string')) > 0);
    }
    /**
     *
     *
    **/
    public static function isArray($arr, int $minSize = 1) : bool
    {
        return is_array($arr) && (count($arr) >= $minSize);
    }
    /**
     * Is the given condition a valid condition
     *
    **/
    public static function validCondition(string $cond) : bool
    {
        return (in_array($cond, self::CONDITIONS));
    }
    /**
     * Given a value and a dateFormat, return a DateTime obj
     *
     * @param string $value      The date time value we want to use as the basis
     * @param string $dateFormat The format of our value
     *
     * @return mixed
    **/
    public static function getDateTime($value, string $dateFormat) : ?\DateTime
    {
        $dateFormat = trim($dateFormat);
        $type = self::getType($value);
        if (!in_array($type, ['int', 'date'])) {
            return null;
        }
        // If they just need the year then assume from 01/01 of year
        if ($type === 'int' && stripos('y', $dateFormat) !== false) {
            $dateObj = new \DateTime();
            $dateObj->setDate((int) $value, 1, 1);
            return $dateObj;
        }
        return \DateTime::createFromFormat($dateFormat, $value);
    }
    /**
     * Given a data file object, open this - by reference
     *
     * @param DataInterface &$file          The file to open
     *
     * @return void
     */
    public static function openDataFile(DataInterface &$file)
    {
        try {
            $file->open();
        } catch (Exceptions\FilePointerExistsException $e) {
            // The stream is already open
        }
    }

    /**
     * Converts an array of arrays to a CSV string
    **/
    public static function arrayToCSV(array $arr, string $delimiter = ',', string $encloser = '"') : string
    {
        $temp = fopen('php://temp', 'rw');
        foreach ($arr as $row) {
            fputcsv($temp, $row, $delimiter, $encloser);
        }
        rewind($temp);
        $csv = stream_get_contents($temp);
        fclose($temp);
        return $csv;
    }

    /**
     * Compares the values in two different arrays, to see if arr1 is in arr2
     * @param array     $arr1
     * @param array     $arr2
     *
     * @return bool
    **/
    public static function areArraysDifferent(array $arr1, array $arr2) : bool
    {
        return count(array_diff($arr1, $arr2)) ? true : false;
    }

    /**
     * Delete files from a directory - unless there extension exists in array
     * @param string    $dir                    The directory to delete the files from
     * @param array     $dontDeleteExtensions   Don't delete files with these extensions
     *
     * @return void
    **/
    public static function deleteFiles(string $dir, array $dontDeleteExtensions = []) : void
    {
        if (false !== ($files = scandir($dir))) {
            array_map(
                function ($file) use ($dir, $dontDeleteExtensions) {
                    if (strlen($file) <= 2) {
                        return;
                    }
                    $file = strtolower("$dir/$file");
                    if (is_dir($file)) {
                        return;
                    }
                    if (!in_array(pathinfo($file, PATHINFO_EXTENSION), $dontDeleteExtensions)) {
                        @unlink($file);
                    }
                },
                $files
            );
        }
    }

    /**
     * Completes a multineed stripos across a haystack string
     *
     * @param string    $haystack   The string to scan
     * @param array     $needles    The characters to check for
     *
     * @return array
    **/
    public static function multiStripos(string $haystack, array $needles, bool $filter = false)
    {
        $found = array_flip($needles);
        array_walk(
            $found,
            function (&$needle, $key) use ($haystack) {
                $needle = stripos($haystack, $key);
            }
        );
        return ($filter) ? count(array_filter(array_values($found), function ($item) { return $item !== false; })) > 0 : $found;
    }

    /**
     * Returns a string representation of the type based on the variable value
     * The variable in this case is always going to be a string
     *
     * @param string    $var    The variable
     *
     * @return string
    **/
    public static function getType(string $var) : string
    {
        // it's easy to determine a number, so we check that first
        if (is_numeric($var)) return (is_float($var + 0) === true) ? 'float' : 'int';
        // next we determine if it's a bool
        if (
            in_array(
                strtolower($var),
                [
                    '0',
                    'false',
                    'null',
                    'none'
                ]
            )
        ) return 'bool';
        // date times are difficult - we can guess to a degree?
        if (self::testDates(
            $var,
            [
                'd/m/y',
                'd/m/Y',
                'm/d/y',
                'm/d/Y',
                'U',
                'd.m.y',
                'd.m.Y',
                'm.d.y',
                'm.d.Y'
            ]
        )) {
            return 'date';
        }
        return 'string';
    }

    /**
     * given a date and a list of valid formats, see if the date conforms to any
     * of these.
     *
     * @param string    $date       The date string to test
     * @param array     $dateList   The list of dates to test
     *
     * @return bool
    **/
    public static function testDates(string $date, array $dateList) : bool
    {
        if (!count($dateList)) return false;
        if (\DateTime::createFromFormat(array_pop($dateList), $date)) {
            return true;
        }
        return self::testDates($date, $dateList);
    }

}
