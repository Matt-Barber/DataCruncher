<?php
/**
 * Interface for all data files
 *
 * @package DataCruncher
 * @subpackage Helpers
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
declare(strict_types=1);
namespace mfmbarber\DataCruncher\Helpers\Interfaces;

interface DataInterface
{
    public function setSource(string $path, array $properties);
    public function getSourceName() : string;
    public function getNextDataRow();
    public function writeDataRow(array $row);
    public function getHeaders(bool $force = true) : array;
    public function open() : bool;
    public function close(): void;
    public function reset(): void;
    public function getFieldType(string $field) : string;
    public function sort(string $key) : ?array;
}
