<?php
/**
 * Statistics Processor
 *
 * @package DataCruncher
 * @subpackage Analysis
 * @author matt barber <mfmbarber@gmail.com>
 *
 */
declare(strict_types=1);
namespace mfmbarber\DataCruncher\Analysis;

use mfmbarber\DataCruncher\Analysis\Config\Rule as Rule;
use mfmbarber\DataCruncher\Config\Validation as Validation;
use mfmbarber\DataCruncher\Helpers\Interfaces\DataInterface as DataInterface;
use mfmbarber\DataCruncher\Exceptions;
use mfmbarber\DataCruncher\Runner as Runner;


class Statistics extends Runner
{
    private $type = 'TOTAL';
    private $round;
    private $rules = [];

    /**
     * Sets the type of response to percentage rather than totals
     *
     * @return Statistics
    **/
    public function percentages(int $round = null) : Statistics
    {
        $this->type = 'PERCENT';
        $this->round = $round;
        return $this;
    }

    /**
     * Generates a new rule that is returned
     *
     * @return Rule
    **/
    public function getRule() : Rule
    {
        $rule = new Rule();
        return $rule;
    }

    /**
     * Adds a rule to the rule stack
     *
     * @return Statistics
    **/
    public function addRule(Rule $rule) : Statistics
    {
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * Execute the statistics calculation given the parameters are set
     * returns an associative array of key value results
     *
     * @return array
    **/
    public function execute() : array
    {
        // we need to create a results array, where each element is
        // based on a rule
        $results = array_fill_keys($this->validateRules(), []);
        Validation::openDataFile($this->source);
        if ($this->timer) $this->timer->start('execute');
        foreach ($this->source->getNextDataRow() as $rowTotal => $row) {
            foreach ($this->rules as $rule) {
                $this->processRow($results[$rule->label], $row, $rule);
            }
        }
        ++$rowTotal;
        $this->singleValueResults($results, $rowTotal);
        // For percentages calculate the percentage based on the total rows
        if ($this->type === 'PERCENT') $this->convertToPercent($results, $rowTotal);
        $this->closeOut($results);
        return $results;
    }

    /**
     * Validate the rules given to the statistic object, creating an array of keys from either
     * the rule label or an integer value returning an array of keys (and updating the rules attribute)
     * this is used to build our results container
     *
     * @return array
    **/
    private function validateRules() : array
    {
        $idx = 0;
        $keys = [];
        // walk across each of the rules we've added, and validate the field exists / type
        // also create a key array for each rule from the labels or the idx (can be mixed)
        array_walk(
            $this->rules,
            function (&$rule) use (&$idx, &$keys) {
                $headers = $this->source->getHeaders();
                if (!in_array($rule->field, $headers)) {
                    throw new \Exception(
                        "{$rule->field} is not in the source, ensure one of ".
                        implode(', ', $headers)
                    );
                }
                $fieldType = $this->source->getFieldType($rule->field);
                if ($rule->type !== null && $fieldType !== $rule->type) {
                    throw new \Exception(
                        "{$rule->field} is $fieldType expects {$rule->type}"
                    );
                }
                $rule->label = ($rule->label) ?? $idx++;
                $keys[] = $rule->label;
            }
        );
        return $keys;
    }

    /**
     * Process the rule against the row
     * @param array     &$result    The result of applying the rule
     * @param array     $row        The row to test
     * @param object     $rule       The rule
     *
     * @return void
    **/
    public function processRow(array &$result, array $row, Rule $rule) : void
    {
        // invoke the closure assigned to the attribute (our statistics func)
        $func = $rule->function;
        $key = $func($row[$rule->field], $rule->option);
        if (false !== $key) {
            if (!array_key_exists($key, $result)) {
                $result[$key] = 0;
            }
            ++$result[$key];
        }
    }

    /**
     * Converts the results from a standard numerical representation
     * To a percentage - with optional rounding based on the methods
     * called
     *
     * @param array     &$results   The results to convert
     * @param int       $total      The total amount of records
    **/
    private function convertToPercent(array &$results, int $total) : Void
    {
        foreach ($results as &$result) {
            foreach ($result as &$value) {
                $value = (100 / $total) * $value;
                if ($this->round) $value = round($value, $this->round);
            }
        }
    }

    /**
     * Iterates across the rules, and updates the results with a single value
     * representing either the average, deviation, min or max
     * This is used when the result is a single value / or requires special processing
     * - no point in having a single element array in the result
     *
     * @param array &$results   The result array to update
     * @param int   $total      The total number of rows in the data source
     *
    **/
    private function singleValueResults(array &$results, int $total) : Void
    {
        // process any single value results
        foreach ($this->rules as $rule) {
            if (null !== $rule->deviationThreshold) {
                foreach ($results[$rule->label] as $key => &$value) {
                    $value = $key - ($rule->product / $total);
                }
            } elseif (null !== $rule->product) {
                $results[$rule->label] = $rule->product / $total;
            } elseif (null !== $rule->min) {
                $results[$rule->label] = $rule->min;
            } elseif (null !== $rule->max) {
                $results[$rule->label] = $rule->max;
            }
        }
    }

    /**
     * Close the source, and apply any timing metrics
     *
     * @param array     &$results   The results
    **/
    private function closeOut(array &$results) : void
    {
        //if ($output !== null) {
            // foreach ($results as $key => &$result) {
            //     foreach ($result as $key => $value) {
            //         $row = [
            //             $this->rules[$key]['field'] => $key,
            //             $this->type => $value
            //         ];
            //         $output->writeDataRow($result);
            //     }
            // }
            //$output->close();
            //return true;
        //}
        // if we have a single result - return that
        $this->source->close();
        if ($this->timer) {
            $time = $this->timer->stop('execute');
            $results['data'] = $results;
            $results['timer'] = [
                'elapsed' => $time->getDuration(), // milliseconds
                'memory' => $time->getMemory() // bytes
            ];
        }
    }
}
