<?php
/**
 * Abstract Data File Handler (shared methods)
 *
 * @package DataCruncher
 * @subpackage Helpers
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
declare(strict_types=1);
namespace mfmbarber\DataCruncher\Helpers\Files;

use mfmbarber\DataCruncher\Exceptions;
use mfmbarber\DataCruncher\Config\Validation;

abstract class DataFile
{

    public $_fp = null;

    protected $fileMode;
    protected $path;
    protected $read = false;
    protected $write = false;

    const READ_MODES = ['r', 'r+', 'rb', 'rb+', 'w+', 'wb+', 'a+', 'x+', 'c+'];
    const WRITE_MODES = ['r+', 'rb+', 'w', 'w+', 'wb', 'wb+', 'a', 'a+', 'x', 'x+', 'c', 'c+'];

    /**
     * Sets the source  file of the Manipulator object, if valid sets attributes
     *
     * @param string $path   The name of the file to set as the source
     * @param array  $properties Data properties, fileMode, encloser, delimiter
     *
     * @return void
    **/
    public function setSource(string $path, array $properties = []) : void
    {
        $this->fileMode = strtolower($properties['fileMode'] ?? 'r+');
        if (in_array($this->fileMode, self::READ_MODES)) {
            if (!$this->fileExists($path)) {
                throw new Exceptions\InvalidFileException("File doesn't exist");
            }
            if (!$this->readable($path)) {
                throw new Exceptions\InvalidFileException("File is not readable");
            }
            $this->read = true;
        }
        if (in_array($this->fileMode, self::WRITE_MODES)) {
            if (!$this->fileExists($path)) {
                touch($path);
            }
            if (!$this->writable($path)) {
                throw new Exceptions\InvalidFileException("File is not writable");
            }
            $this->write = true;
        }
        $this->path = $path;

        if (!$this->read && !$this->write) {
            throw new Exceptions\InvalidFileException("{$this->fileMode} is not a valid fileMode");
        }
    }

    /**
     * Update the fileMode
     *
     * @param string $fileMode  The fileMode to apply
    **/
    public function setfileMode(string $fileMode) : void
    {
        $this->setSource($this->path, ['modifer' => strtolower($fileMode)]);
    }

    /**
     * A local copy of fileExists to allow us to mock this function

     * @param string    $path   The name of the file to check
     *
     * @return bool
    **/
    public function fileExists(string $path) : bool
    {
        return (bool) file_exists($path);
    }

    /**
     * We abstract this method as we might change our check for writable in future
     * @param string $path  The name of the file to check is writable
     *
     * @return bool
    **/
    public function writable(string $path) : bool
    {
        return (bool) is_writable($path);
    }

    /**
     * We abstract this method as we might change our check for readable in future
     * @param string $path  The name of the file to check is writable
     *
     * @return bool
    **/
    public function readable(string $path) : bool
    {
        return (bool) is_readable($path);
    }

    /**
     * Returns the type of the source
     *
     * @return string
    **/
    public function getType() : string
    {
        return 'file';
    }

    /**
     * Returns the current path tied to this object
     *
     * @return string
     *
    **/
    public function getSourceName() : string
    {
        return $this->path;
    }

    /**
     * Open is a method that sets a local file pointer
     *
     * @return void
    **/
    public function open() : bool
    {
        if ($this->_fp === null) {
            $this->_fp = fopen($this->path, $this->fileMode);
            return true;
        }
        throw new Exceptions\FilePointerExistsException(
            'A filepointer exists on this object, use class::close to'
            .' close the current pointer'
        );
    }

    /**
     * Reset the file pointer to the start of the file
     *
     * @return void
     */
    public function reset() : void
    {
        if ($this->_fp !== null) {
            rewind($this->_fp);
            return;
        }
        throw new Exceptions\FilePointerInvalidException(
            'The filepointer is null on this object, use class::open'
            .' to open a new filepointer'
        );
    }

    /**
     * Close closes the file pointer attribute
     *
     * @return void
    **/
    public function close() : void
    {
        if ($this->_fp !== null) {
            fclose($this->_fp);
            $this->_fp = null;
            return;
        }
        throw new Exceptions\FilePointerInvalidException(
            'The filepointer is null on this object, use class::open'
            .' to open a new filepointer'
        );
    }

    /**
     * Return the type of a given field as a string
     *
     * @param string    $field  The field to get the type of
     *
     * @return string
    **/
    public function getFieldType(string $field) : string
    {
        $this->open();
        $row = $this->getNextDataRow()->current();
        $this->close();
        return Validation::getType($row[$field]);
    }
}
