<?php
/**
 * CSVFile Handler
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
use mfmbarber\DataCruncher\Helpers\Interfaces\DataInterface;

use Symfony\Component\Stopwatch\Stopwatch;

use mfmbarber\DataCruncher\Exceptions\InvalidFileException;

class CSVFile extends DataFile implements DataInterface
{
    // The amount of the file to load into memory in one chunk
    private const CHUNK_SIZE = 100000;
    private const SORT_CHUNK_SIZE = 10000000;

    // The amount of lines to store  in memory before writing to the output
    private const WRITE_BUFFER_LIMIT = 500;

    // file meta info
    private $headers = [];
    private $delimiter = ',';
    private $encloser = '"';

    // current chunk and the buffer
    private $chunk = [];
    private $buffer = '';

    // current write buffer and the length of the write buffer
    private $writeBuffer = [];
    private $writeBufferCounter = 0;

    /**
     * Sets the source for this CSV object, by specifying the file name and properties
     * @param string    $path   The name and path of the file
     * @param array     $properties The properties for the source (i.e. ['fileMode' => 'r'])
     *
     * @return void
    **/
    public function setSource(string $path, array $properties = []) : void
    {
        parent::setSource($path, $properties);
        $this->delimiter = $properties['delimiter'] ?? $this->delimiter;
        $this->encloser = $properties['encloser'] ?? $this->encloser;
        if ($this->read) {
            $this->open();
            $row = $this->getNextDataRow()->current();
            $this->close();
            if (!$row) {
                throw new Exceptions\InvalidFileException("The file provided is not in the correct format");
            }
        }
    }
    /**
     * Opens a file at the beginning, reads a line and closes the file
     * Returns the configured fields
     *
     * @return array
    **/
    public function getHeaders(bool $force = true) : array
    {
        if ($force || $this->headers === []) {
            $this->open();
            $this->getNextDataRow()->current();
            $this->close();
        }
        return $this->headers;
    }

    /**
     * Calls the _getcsv method to get the next line, if it
     * exists. The process then creates an a set of key value pairs that
     * depict that line (headers and values)
     * GENERATOR
     *
     * @param bool  $peek   Peek will not remove the returned line
     *
     * @return array
    **/
    public function getNextDataRow(bool $peek = false) : \Generator
    {
        if (!$this->read) {
            throw new Exceptions\InvalidFileException("File is not set to read mode");
        }
        if ($this->headers === []) {
            $this->headers = $this->getCsv(self::CHUNK_SIZE);
        }
        while ([] !== ($line = $this->getCsv(self::CHUNK_SIZE, $peek))) {
            yield @array_combine($this->headers, $line);
        }
    }

    /**
     * Calls the _putcsv method to push a row to the output stream (if it exists)
     * This method returns null on success, and throws an error if there are any errors
     *
     * @return void
    **/
    public function writeDataRow(array $row) : void
    {
        if (!$this->_fp) {
            throw new Exceptions\FilePointerInvalidException(
                'The filepointer is null on this object, use class::open'
                .' to open a new filepointer'
            );
        }
        if ($this->headers === []) {
            $this->headers = array_keys($row);
            if (false === fputcsv($this->_fp, $this->headers, $this->delimiter, $this->encloser)) {
                throw new \RuntimeException("Couldn't write to {$this->getSourceName()}");
            }
        }
        if (false === $this->putCSV($row)) {
            throw new \RuntimeException("Couldn't write to {$this->getSourceName()}");
        }
    }

    /**
     * Override the open method, to clear the headers
     *
     * @return void
    **/
    public function open() : bool
    {
      $this->headers = [];
      return parent::open();
    }

    /**
     * Override the reset method, to clear the headers
     *
     * @return void
    **/

    public function reset() :void
    {
        $this->headers = [];
        parent::reset();
    }

    /**
     * Override the parent DataFile close method, because we're using chunked writing - we don't
     * necessarily know if the buffer is empty, so let's just write it to the output stream
     *
     * @return void
    **/
    public function close() : void
    {
        $this->flushBuffer();
        parent::close();
    }

    /**
     * Flush the write buffer (generally called before closing the file)
     *
     * @return void
    **/
    public function flushBuffer() : void
    {
        if ($this->_fp) {
            if (isset($this->writeBuffer)) {
                if ($this->write) {
                    fwrite($this->_fp, Validation::arrayToCSV($this->writeBuffer, $this->delimiter, $this->encloser));
                    $this->writeBuffer = [];
                }
            }
            $this->chunk = [];
            $this->buffer = '';
        }
    }

    /**
      * Sorts the data using an external merge sort algorithm, based on a consistent chunk size
      * Limits memory usage and can be used to sort >600mb files
      *
      * @param $key   The header to sort by
      *
      * @return array
      *
    **/
    public function sort(string $key) : ?array
    {
        // Timing
        $timer = new Stopwatch();
        $timer->start('sort');
        try {
            $this->open();
        } catch (\Exception $e) {
            $this->reset();
        }
        // dump the headers and load chunk 1
        $this->getNextDataRow(true)->current();
        // Sort this csv into a set of sorted files
        $csvs = $this->createSortedChunks($key);
        $this->close();
        // setup an output file
        $output = new CSVFile();
        $output->setSource($this->getSourceName(), ['fileMode' => 'wb']);
        $output->open();
        // create an array holding the current comparisons
        $cmps = array_fill(0, count($csvs), false);
        $lines = 0;
        // while there are still more than 1 sorted files
        while (count($csvs) > 1) {
            $remove = [];
            $low = ['index' => null, 'data' => []];
            foreach ($csvs as $i => $csv) {
                if (!$cmps[$i]) {
                    if (!($cmps[$i] = $csv->getNextDataRow(true)->current())) {
                        $remove[] = $i;
                        continue;
                    }
                }
                if (!$low['index'] || $cmps[$i][$key] <= $low['data'][$key]) {
                    $low = ['index' => $i, 'data' => $cmps[$i]];
                }
            }
            foreach ($remove as $i) {
                $csvs[$i]->close();
                unlink($csvs[$i]->getSourceName());
                unset($csvs[$i]);
            }
            if (isset($csvs[$low['index']]))
            {
                $csvs[$low['index']]->getNextDataRow()->current();
            }
            if ($low['data'] !== []) {
                $output->writeDataRow($low['data']);
            };
            ++$lines;
            $cmps[$low['index']] = false;
        }
        $csv = array_pop($csvs);
        foreach ($csv->getNextDataRow() as $line) {
            $output->writeDataRow($line);
            ++$lines;
        }
        $csv->close();
        unlink($csv->getSourceName());
        $output->close();
        $event = $timer->stop('sort');
        return [
            'duration' => $event->getDuration(),
            'max_memory' => $event->getMemory(),
            'lines' => $lines
        ];
     }

    /**
     * Return the current chunk
     *
     * @return array
    **/
    public function getChunk() : array
    {
        return $this->chunk;
    }

    /**
     * Return the current buffer
     *
     * @return string
    **/
    public function getBuffer() : string
    {
        return $this->buffer;
    }

    /**
     * Chunk the original CSV into separate files and store reference to these -
     * instantiated as CSVFiles
     *
     * @param string $key   The key to sort on
     *
     * @return array
     **/

     private function createSortedChunks(string $key) : array
     {
         $csvs = [];
         // change the key into an integer
         $key = array_search($key, $this->headers);
         // while we still have data in the file
         do
         {
             usort(
                 $this->chunk,
                 function($left, $right) use ($key) {
                     $left = str_getcsv($left);
                     $right = str_getcsv($right);
                     return (
                         (!isset($left[$key]) || !isset($right[$key])) ?
                         -1 : $left[$key] <=> $right[$key]
                     );

                 }
             );
             // add the CSV to our list of merge parts
             do {
                 $path = 'tmp/'.$this->generateRandomString();
             } while (file_exists($path));
             // Create a new CSVFile with the path
             $csv = new CSVFile();
             $csv->setSource($path, ['fileMode' => 'wb']);
             $csv->open();
             // write the headers
             fwrite($csv->_fp, implode(",", $this->headers) . "\n");
             // write the lines to the file
             fwrite($csv->_fp, implode(
                 "\n",
                 array_filter(
                     $this->chunk,
                     function ($line) {
                         return (bool) strlen($line);
                     }
                 )
             ));
             // close the CSVFile
             $csv->close();
             // Create a new CSVFile from the same file in read mode
             $csv =  new CSVFile();
             $csv->setSource($path, ['fileMode' => 'r']);
             $csv->open();
             // set the headers
             $csv->getNextDataRow(true)->current(); // dump the headers;
             $csvs[] = $csv;
              // get 8 meg loaded into a chunk
         } while ($this->getNextChunk(self::SORT_CHUNK_SIZE));
         return $csvs;
     }

    /**
     * _getcsv is a private method that uses the file pointer to get the next line
     * It uses a chunked method where it loads CHUNK_SIZE of string data into memory
     * This reduces the latency of reading from the file every time we want a line
     *
     * @param int   $size   The size of the chunk in bytes
     * @param bool  $peek   Toggle shifting the line off the top of the stack, or simply returning it
     *
     * @return array / bool
    **/
    private function getCsv(int $size, bool $peek = false) : array
    {
        if (empty($this->chunk)) {
            if (feof($this->_fp)) {
                return [];
            }
            $this->getNextChunk($size);
        }
        $line = (!$peek) ? array_shift($this->chunk) : $this->chunk[0];
        return "" === ($line)  ? [] : array_map('trim', str_getcsv($line));
    }

    /**
     * Gets the next chunk of the byte size allocated in the argument
     * @param int   $size   The size of the chunk to grab in bytes
     *
     * @return boolean
    **/
    private function getNextChunk(int $size) : bool
    {
        if (feof($this->_fp)) {
            if (!$this->buffer) {
                return false;
            }
            $this->chunk = $this->buffer;
        } else {
            // if we're empty get the next chunk using the previous buffer
            $this->chunk = $this->buffer . fread($this->_fp, $size);
            $this->buffer = null;
        }
        // explode the chunk into lines
        $this->chunk = preg_split("/\\r\\n|\\r|\\n/", $this->chunk);
        // if this isn't the end of the file buffer out the last line
        if (!feof($this->_fp)) {
            // buffer out the last line
            $this->buffer = array_pop($this->chunk);
        }
        return true;
    }

    /**
     * _putcvsv is a private method that writes a set of files to the output stream a
     * chunk at a time, again this reduces latency when writing to the file. A validation
     * method returns an array of csv lines as a single csv string.
     *
     * @param array     $row    The row to put in the CSV file
     *
     * @return bool
    **/
    private function putCSV(array $row) : bool
    {
        $result = true;
        $this->writeBuffer[] = $row;
        ++$this->writeBufferCounter;
        if ($this->writeBufferCounter >= self::WRITE_BUFFER_LIMIT) {
            $result = (bool) fwrite($this->_fp, Validation::arrayToCSV($this->writeBuffer, $this->delimiter, $this->encloser));
            $this->writeBuffer = [];
            $this->writeBufferCounter = 0;
        }
        return $result;
    }

    /**
     * Generates a random string of characters of a given length
     *
     * @param int   $length     The length of the random string to create
     *
     * @return string
    **/
    private function generateRandomString(int $length = 10) : string
    {
        return substr(
            str_shuffle(
                str_repeat(
                    $x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    (int) ceil($length/strlen($x)) // type cast incase we have a float
                )
            ),
            1,
            $length
        );
    }

}
