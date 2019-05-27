<?php
/**
 * Split Processor
 *
 * @package DataCruncher
 * @subpackage Segmentation
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
namespace mfmbarber\DataCruncher\Segmentation;

use mfmbarber\DataCruncher\Exceptions as Exceptions;
use mfmbarber\DataCruncher\Config\Validation as Validation;
use mfmbarber\DataCruncher\Helpers\Interfaces\DataInterface as DataInterface;

class Split
{
    private $source;

    /**
     * Sets the data source for the split
     *
     * @param DataInterface $source The data source for the query
     *
     * @return Split
    **/
    public function fromSource(DataInterface $source) : Split
    {
        $this->source= $source;
        return $this;
    }

    /**
     * Split the data horizontally into chunks of X size
     *
     * @param integer $size The size of each chunk
     *
     * @return Split
     **/
    public function horizontal($size) : Split
    {
        $this->direction = 'HORIZONTAL';
        if (!is_int($size)) {
            throw new \InvalidArgumentException("Size expected to be an integer");
        }
        $this->size = (int) $size;
        return $this;
    }

    /**
     * Split the data vertically
     *
     * @param array $groupings The fields for each of the groups
     *
     * @return Split
     **/
    public function vertical($groupings = []) : Split
    {
        $this->direction = 'VERTICAL';
        // Groupings provided as [['name', 'colour'], ['name', 'age']] where each element
        // defines a groups headers
        $this->groups = $this->setGroupings($groupings);
        return $this;
    }

    /**
     * Execute the split, returning an array of arrays, where each sub array
     * is a row of headers and values
     *
     * @param array(Helpers\DataInterface) $outfile a location to populate with results
     *
     * @return array
    **/
    public function execute(array $outfiles = [], $nodeName = '', $startElement = '') : array
    {
        $result = [];
        $set = [];
        $ticker = false; // horizontal ticker

        Validation::openDataFile($this->source, $nodeName, $startElement);
        if (($writeOutFiles = $this->openOutFiles($outfiles, $nodeName, $startElement, true))) {
            $result = array_fill(0, count($outfiles), 0);
        }
        if ($this->direction === 'VERTICAL') {
            $result = array_fill(0, count($this->groups)-1, []);
        }
        foreach ($this->source->getNextDataRow() as $row) {
            switch ($this->direction) {
                // TODO move processing of out files into functions that handle this
                case 'HORIZONTAL':
                    // push the row on to our array of lines
                    $set[] = $row;
                    // if we're at the chunk size then...
                    if (count($set) === $this->size) {
                        // Decide on output mode
                        if ($writeOutFiles) {
                            foreach ($set as $row) {
                                $outfiles[(int) $ticker]->writeDataRow($row);
                            }
                            $result[(int) $ticker] += count($set);
                            $ticker = !$ticker;
                        } else {
                            $result[] = $set;
                        }
                        $set = [];
                    }
                    break;
                case 'VERTICAL':
                    foreach ($this->groups as $idx => $group) {
                        $out_row = array_intersect_key($row, $group);
                        ($writeOutFiles) ? $outfiles[$idx]->writeDataRow($out_row) : $result[$idx][] = $out_row;
                    }
                    break;
            }
        }
        $this->source->close();
        if ($writeOutFiles) {
            foreach ($outfiles as &$outfile) {
                $outfile->close();
            }
        }
        return $result;
    }

    /**
     * Opens an array of data sources
     * @param array     $outfiles       to open (by reference)
     * @param string    $nodeName      are the names of the nodes if the file being opened is xml
     * @param string    $start_elemnent is the parent element of the data nodes if the file is xml
     *
     * @throws InvalidArgumentException
     * @return Boolean
    **/
    private function openOutFiles(&$outfiles, $nodeName = '', $startElement = '') : bool
    {
        if ($outfiles == []) {
            return false;
        }
        $count = count($outfiles);
        $error = false;
        if ($count <= 1) {
            $error = true;
        }
        if ($this->direction === 'HORIZONTAL' && $count !== $this->size) {
            $error = true;
        }
        if ($this->direction === 'VERTICAL' && $count != count($this->groups)) {
            $error = true;
        }
        if ($error) {
            throw new \InvalidArgumentException(
                "Ensure 2 outputs are provided for vertical split, and x "
                ."for horizontal split"
            );
        }
        foreach ($outfiles as &$outfile) {
            Validation::openDataFile($outfile, $nodeName, $startElement);
        }
        return true;
    }

    /**
     * Set groupings creates an array of arrays, where each array represents a vertical group
     *
     * @param array     $groupings  Can be an array of comma seperated field names,
     *                              or of arrays where each field within represents a field name
     *
     * @return array
    **/
    private function setGroupings($groupings) : array
    {
        $groups = [];
        foreach ($groupings as $group) {
            $group = is_array($group) ? $group : array_map('trim', explode(',', $group));
            $groups[] = array_flip($group);
        }
        return $groups;
    }
}
