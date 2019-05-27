<?php
/**
 * XML File
 *
 * @package DataCruncher
 * @subpackage Helpers
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
declare(strict_types=1);
namespace mfmbarber\DataCruncher\Helpers\Files;

use mfmbarber\DataCruncher\Exceptions;
use mfmbarber\DataCruncher\Helpers\Interfaces\DataInterface;

class XMLFile extends DataFile implements DataInterface
{
    private $nodeName;
    private $startElement;
    private $fields = [];

    public function __construct(string $nodeName, string $startElement)
    {
        $this->nodeName = $nodeName;
        $this->startElement = $startElement;
    }

    /**
     * Opens a file at the beginning, reads a line and closes the file
     * Returns the configured fields
     *
     * @param string $nodeName
     * @param string $startElement
     *
     * @return array
    **/
    public function getHeaders(bool $force = true) : array
    {
        if ($force || $this->headers === []) {
            $this->open(true, $this->nodeName, $this->startElement);
            $this->getNextDataRow()->current();
            $this->close();
        }
        return $this->fields;
    }

    /**
     * Returns the next row of data from a file, if there are no rows locale_accept_from_http
     * this returns false
     *
     * @return Generator
     */
    public function getNextDataRow() : \Generator
    {
        $row = [];
        while ($this->_fp->name === $this->nodeName) {
            $row = $this->toArray(new \SimpleXMLElement($this->_fp->readOuterXML()));
            $this->fields  = ($this->fields === []) ? array_keys($row) : $this->fields;
            $this->_fp->next($this->nodeName);
            yield $row;
        }

    }
    /**
     * Writes a row of data to an output file.
     *
     * @param array $row    The row of data to write back to the file
     *
     * @return boolean
     */
    public function writeDataRow(array $row) : bool
    {
        if ($this->_fp) {
            if (!count($row)) {
                return false;
            }
            $this->_fp->startElement($this->nodeName);
            foreach ($row as $key => $value) {
                $this->_fp->writeElement($key, $value);
            }
            $this->_fp->endElement();
            $this->_fp->flush();
            if ($this->fields === []) {
                $this->fields = array_keys($row);
            }
            return true;
        } else {
            throw new Exceptions\FilePointerInvalidException(
                'The filepointer is null on this object, use class::open'
                .' to open a new filepointer'
            );
        }
    }
    /**
     * open the file and set the file pointer
     *
     * @param bool  $read   Should we open this in READ mode?
     *
     * @return void
     */
    public function open() : bool
    {
        if (!$this->_fp) {
            if ($this->read) {
                $this->_fp = new \XMLReader();
                $this->_fp->open($this->path);
                while ($this->_fp->read() && $this->_fp->name !== $this->nodeName);
            } elseif ($this->write) {
                $this->_fp = new \XMLWriter();
                $this->_fp->openURI($this->path);
                $this->_fp->startDocument('1.0');
                if ($this->startElement !== null) {
                    $this->_fp->startElement($this->startElement);
                }
            }
            return true;
        } else {
            throw new Exceptions\FilePointerExistsException(
                'A filepointer exists on this object, use class::close to'
                .' close the current pointer'
            );
        }
    }
    /**
     * Close closes the file pointer attribute
     *
     * @return void
     */
    public function close() : void
    {
        if ($this->_fp) {
            if (get_class($this->_fp) !== 'XMLReader') {
                $this->_fp->endDocument();
                $this->_fp->flush();
            }
            $this->_fp = null;
        } else {
            throw new Exceptions\FilePointerInvalidException(
                'The filepointer is null on this object, use class::open'
                .' to open a new filepointer'
            );
        }
    }
    /**
     * Reset the file pointer to the start of the file
     *
     * @return void
     */
    public function reset() : void
    {
        if ($this->_fp) {
            // we have to close and reopen
            $this->close();
            $this->open($this->read, $this->nodeName, $this->startElement);
        } else {
            throw new Exceptions\FilePointerInvalidException(
                'The filepointer is null on this object, use class::open'
                .' to open a new filepointer'
            );
        }
    }

    /**
     * Sort the XML object
     *
     * @param string  $key  The key to sort the data on
     *
     * @return ?array
    **/
    public function sort(string $key) : ?array
    {
        throw new Exception('Not yet implemented');
    }

    /**
     * Converts simpleXMLObject to array
     *
     * @param simpleXMLObject   $xml The current element as a simple xml object
     * @param array             $row The current row represented as an array
     *
     * @return array
     */
    private function toArray($xml, array $row = [])
    {
        // if the data is an object, we get this as an array, else we return the xml
        $data = (is_object($xml)) ? get_object_vars($xml) : $xml;
        // if the data is an array...
        if (is_array($data)) {
            // we loop across building our equivelent with recursive calls
            foreach ($data as $key => $value) {
                $res = [];
                $res = $this->toArray($value, $res);
                (($key == '@attribute') && ($key)) ? $row = $res : $row[$key] = trim($res);
            }
        } else {
            $row = $data;
        }
        return $row;
    }
}
