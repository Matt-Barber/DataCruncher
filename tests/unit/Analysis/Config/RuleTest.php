<?php
namespace mfmbarber\DataCruncher\Tests\Unit\Analysis\Config;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Analysis\Config\Rule as Rule;
use mfmbarber\DataCruncher\Helpers\Files\CSVFile as CSVFile;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class RuleTest extends TestCase
{
    /**
     * Test setting the field for the rule
     *
     * @return null
    **/
    public function testItShouldSetTheFieldToTest()
    {
        $rule = new Rule();
        $rule->setField('test');
        $this->assertEquals(
            $rule->field,
            'test'
        );
    }

    /**
     * Test settinwg the label for the rule
     *
     * @return null
    **/
    public function testItShouldSetTheLabelForTheTest()
    {
        $rule = new Rule();
        $rule->setLabel('test');
        $this->assertEquals(
            $rule->label,
            'test'
        );
    }

    /**
     * Test grouping a rule by exact values
     *
     * @return null
    **/
    public function testItShouldGroupByExact()
    {
        $rule = new Rule();
        $rule->groupExact();
        $func = $rule->function;
        $this->assertEquals(
            $func('a', null),
            'a'
        );
    }

    /**
     * Test grouping a rule by numeric boundaries
     *
     * @return null
    **/
    public function testItShouldGroupByNumeric()
    {
        $rule = new Rule();
        $rule->groupNumeric(10);
        $func = $rule->function;
        $this->assertEquals(
            $func(8, 10),
            '0, 10'
        );
    }

    /**
     * Test grouping rule by regular expressions
     *
     * @return null
    **/
    public function testItShouldGroupByRegex()
    {
        $rule = new Rule();
        $rule->groupRegex('/^([\w\-]+)/i');
        $func = $rule->function;
        $this->assertEquals(
            $func('apple iphone', '/^([\w\-]+)/i'),
            'apple'
        );
    }

    public function testItShouldReturnTheMinValue()
    {
        $rule = new Rule();
        $rule->getMin();
        $func = $rule->function;
        foreach (range(1, 100, 10) as $value) {
            $func($value);
        }
        $this->assertEquals($rule->min, 1);
    }
}
