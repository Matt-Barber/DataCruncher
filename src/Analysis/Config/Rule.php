<?php
/**
 * Rule Processor
 *
 * @package DataCruncher
 * @subpackage Analysis
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
declare(strict_types=1);
namespace mfmbarber\DataCruncher\Analysis\Config;

use mfmbarber\DataCruncher\Config\Validation;
use mfmbarber\DataCruncher\Helpers\Interfaces\DataInterface;
use mfmbarber\DataCruncher\Analysis\Config\Rule;
use mfmbarber\DataCruncher\Exceptions;

class Rule
{
    public $field;
    public $type;
    public $function;
    public $option;
    public $label = null;
    public $min = null;
    public $max = null;
    public $deviationThreshold = null;
    public $product = null;
  /**
     * Sets the field to calculate statistics on
     *
     * @param string $field The name of the field to run the statistics on
     *
     * @return Rule
    **/
    public function setField(string $field) : Rule
    {
        $this->field = $field;
        return $this;
    }

    /**
     * Set the reference label
     *
     * @param string    $label  The label to reference the result in the results array
     *
     * @return Rule
    **/
    public function setLabel(string $label) : Rule
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the rule to return the minimum value
     *
     * @return Rule
    **/
    public function getMin() : Rule
    {
        $this->function = function ($value) {
            if (null === $this->min) {
                $this->min = $value;
            }
            if ($value < $this->min) {
                $this->min = $value;
            }
            return false;
        };
        $this->type = null; // make this numeric
        return $this;
    }

    /**
     * Sets the rule to return the maximum value
     *
     * @return Rule
    **/
    public function getMax() : Rule
    {
        $this->function = function ($value) {
            if (null ===$this->max) {
                $this->max = $value;
            }
            if ($value > $this->max) {
                $this->max = $value;
            }
            return false;
        };
        $this->type = null; // make this numeric
        return $this;
    }

    /**
     * Sets the rule to store the product, if this is set,
     * the statistics object will infer the average from it
     *
     * @return Rule
    **/
    public function getAverage() : Rule
    {
        $this->function = function ($value) {
            if (!$this->product) {
                $this->product = 0;
            }
            $this->product += $value;
            return $value;
        };
        return $this;
    }

    /**
     * Sets the rule to store the deviation treshold, if this is set
     * the Statistics object will infer the standard deviation from it
     * As it needs the average, it will have to use that function to generate
     * the product
     *
     * @return Rule
    **/
    public function getDeviation(int $threshold) : Rule
    {
        $this->deviationThreshold = $threshold;
        return $this->getAverage();
    }

    /**
     * Sets the _function private property to be a closure, this closure
     * simply returns the value given. Exact implies the key in the results
     * will be exact and not a grouping.
     *
     * @return Rule
    **/
    public function groupExact() : Rule
    {
        $this->function = function ($value, $option) {
            return $value;
        };
        $this->type = null;
        return $this;
    }

    /**
     * Sets the _function private property to be a closure, this closure
     * returns the numeric grouping given the step for the groups.
     * So for instance a step of 10, would return 0, 10, if the value given
     * was 7
     *
     * @param integer $step The step between each value in the grouping
     *
     * @return Rule
    **/
    public function groupNumeric(int $step) : Rule
    {
        $this->option = $step;
        $this->function = function($value, $step) {
            $lower = ((int) ($value / $step)) * $step;
            $upper = (((int) ($value / $step)) + 1) * $step;
            return "$lower, $upper";
        };
        $this->type = 'int';
        return $this;
    }

    /**
     * Sets the _function private property to a closure, this closure
     * returns the result of a regex expression. i.e. '/^([\w\-]+)/i'
     *
     * @param string $regex i.e. '/^([\w\-]+)/i'
     *
     * @return Rule
    **/
    public function groupRegex($regex) : Rule
    {
        $this->option = $regex;
        $this->function = function ($value, $regex) {
            $result = [];
            preg_match($regex, trim($value), $result, PREG_OFFSET_CAPTURE);
            return $result[0][0];
        };
        $this->type = 'string';
        return $this;
    }
    /**
     * Sets the _function private property to be a closure, this closure
     * returns the date grouping given the part of the date to be returned
     * So for instance, given a returnFormat of 'Y', would return 1987 given
     * 24/11/1987 and a dataFormat of d/m/Y
     *
     * @param string $dataFormat   The format that the data is in in the source
     * @param string $returnFormat The format to return the data in
     *
     * @return Rule
    **/
    public function groupDate(string $dataFormat, string $returnFormat) : Rule
    {
        $this->option = $returnFormat;
        $this->dataFormat = $dataFormat;
        $this->function = function ($value, $format) {
            $date = Validation::getDateTime($value, $this->dataFormat);
            return (!$date) ? false : $date->format($format);
        };
        $this->type = 'date';
        return $this;
    }
}
