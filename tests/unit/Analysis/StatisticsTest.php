<?php
namespace mfmbarber\DataCruncher\Tests\Unit\Analysis;

use mfmbarber\DataCruncher\Analysis\Statistics as Statistics;

use mfmbarber\DataCruncher\Analysis\Config\Rule as Rule;
use mfmbarber\DataCruncher\Helpers\Files\CSVFile as CSVFile;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class StatisticsTest extends \PHPUnit_Framework_TestCase
{
    private $root;

    private $mockSourceCSV;
    private $mockOutCSV;

    public function setUp()
    {
        $this->root = vfsStream::setup('home', 0777);
        $file = vfsStream::url('home/test', 0777);
        file_put_contents(
            $file,
            "email, name, colour, dob, age, phone\n"
            ."mfmbarber@test.com, matt, \"black, green, blue\", 24/11/1987, 28, apple iphone 6\n"
            ."matt.barber@test.com, matthew, \"red, green\", 01/12/1980, 35, samsung galaxy s6\n"
            ."tony.stark@avengers.com, tony, \"red, gold\", 02/05/1990, 25, samsung note 7\n"
            ."j@something.com, john, \"green\", 01/01/2000, 15, htc m8"
        );
        vfsStream::url('home/test_out', 0777);
        $this->mockSourceCSV = new CSVFile;
        $this->mockOutCSV = new CSVFile;

        $this->mockSourceCSV->setSource('vfs://home/test', ['modifier' => 'r']);
        $this->mockOutCSV->setSource('vfs://home/test_out', ['modifier' => 'w']);
    }

    public function tearDown()
    {
        $this->root = null;
        $this->mockSourceCSV = null;
        $this->mockOutCSV = null;
    }
    /**
     * Tests the execution of percentages grouping exactly by the values in a
     * field
     *
     * @return null
    **/
    public function testItShouldCalculatePercentagesBasedOnExactNames()
    {
        $stats = new Statistics();
        $rule = new Rule();
        $rule = $rule->setField('name')->groupExact();
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages()
            ->execute();
        $result = array_pop($result);
        $this->assertEquals(
            [
                'matt' => 25,
                'matthew' => 25,
                'tony' => 25,
                'john' => 25
            ],
            $result,
            "Execute did not return the expected results"
        );
    }
    /**
     * Tests the execution of percentages grouping the values in a field
     * by numerical range i.e. 10 would be (1 -> 10, 10 -> 20)
     *
     * @return null
    **/
    public function testItShouldCalculatePercentagesBasedOnNumericGroupings()
    {
        $stats = new Statistics();
        $rule = new Rule();
        $rule = $rule->setField('age')->groupNumeric(10);
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages()
            ->execute();
        $result = array_pop($result);
        $this->assertEquals(
            [
                '10, 20' => 25,
                '20, 30' => 50,
                '30, 40' => 25
            ],
            $result,
            "Execute did not return the expected results"
        );
    }
    /**
     * Tests the execution of percentages grouping the values in a field
     * by date
     *
     * @return null
    **/
    public function testItShouldCalculatePercentagesBasedDateGroupings()
    {
        $stats = new Statistics();
        $rule = new Rule();
        $rule = $rule->setField('dob') ->groupDate('d/m/Y', 'Y');
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages()
            ->execute();
        $result = array_pop($result);
        $this->assertEquals(
            [
                '1987' => 25,
                '1980' => 25,
                '1990' => 25,
                '2000' => 25
            ],
            $result,
            "Execute did not return the expected results"
        );
    }

    // TODO : Fix the outfile functionality...
    /**
     * @t/est
    **/
    public function ____testItShouldWritePercentagesToOutfile()
    {
        $stats = new Statistics();
        $rule = new Rule();
        $rule = $rule->setField('dob') ->groupDate('d/m/Y', 'Y');
        $stats->addRule($rule);
        $stats->from($this->mockSourceCSV)
            ->percentages()
            ->out($this->mockOutCSV)
            ->execute();
        $this->assertEquals(
            file_get_contents($this->mockOutCSV->getSourceName()),
            "dob,PERCENT\n1987,25\n1980,25\n1990,25\n2000,25\n",
            file_get_contents($this->mockOutCSV->getSourceName())
        );
    }

    /**
     * Tests the execution calculates by percentages and groups the results by regex
     *
     * @return null
    **/
    public function testItShouldCalculatePercentagesOfPhonesGroupedByRegex()
    {
        $stats = new Statistics();
        $rule = new Rule();
        $rule = $rule->setField('phone')->groupRegex('/^([\w\-]+)/i');
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages()
            ->execute();
        $result = array_pop($result);
        $this->assertEquals(
            [
                'apple' => 25,
                'samsung' => 50,
                'htc' => 25
            ],
            $result,
            "Execute did not return the expected results"
        );
    }

    /**
     * Tests the using of multiple rules to get multiway information sets
     *
     * @return null
    **/
    public function testItShouldReturnMultipleSetsOfResultsGivenMultipleRules()
    {
        $stats = new Statistics();
        $rules = [];
        $rule = new Rule();
        $rule->setField('phone')->groupRegex('/^([\w\-]+)/i');
        $stats->addRule($rule);
        $rule->setField('colour')->groupRegex('/([^,]+)/');
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages()
            ->execute();
        $this->assertEquals(
            [
                [
                    'apple' => 25,
                    'samsung' => 50,
                    'htc' => 25
                ],
                [
                    'red' => 50,
                    'black' => 25,
                    'green' => 25
                ]
            ],
            $result,
            "Execute did not return the expected results"
        );
    }
    /**
     * Tests the using of multiple rules to get multiway information sets
     * with labelled results
     *
     * @return null
    **/
    public function testItShouldReturnMultipleSetsOfResultsGivenMultipleRulesWithLabels()
    {
        $stats = new Statistics();
        $rules = [];
        $rule = new Rule();
        $rule->setField('phone')->groupRegex('/^([\w\-]+)/i')->setLabel('company');
        $stats->addRule($rule);
        $rule = new Rule();
        $rule->setField('colour')->groupRegex('/([^,]+)/')->setLabel('colour');
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages()
            ->execute();
        $this->assertEquals(
            [
                'company' => [
                    'apple' => 25,
                    'samsung' => 50,
                    'htc' => 25
                ],
                'colour' => [
                    'red' => 50,
                    'black' => 25,
                    'green' => 25
                ]
            ],
            $result,
            "Execute did not return the expected results"
        );
    }

    /**
    * Test the tracking of execution time
    *
    * @return null
    **/
    public function testItShouldTrackTheTimeOfExecution()
    {
        $stats = new Statistics();
        $rules = [];
        $rule = new Rule();
        $rule->setField('phone')->groupRegex('/^([\w\-]+)/i');
        $stats->addRule($rule);
        $rule->setField('colour')->groupRegex('/([^,]+)/');
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages()
            ->timer()
            ->execute();
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('timer', $result);
        $this->assertTrue(is_integer($result['timer']['elapsed']));
    }

    /**
     * Test the rounding of percentages to floating point
     *
     * @return null
    **/
    public function testItShouldRoundPercentagesToOneDecimalPoint()
    {
        $stats = new Statistics();
        $rules = [];
        $rule = new Rule();
        $rule->setField('phone')->groupRegex('/^([\w\-]+)/i');
        $stats->addRule($rule);
        $result = $stats->from($this->mockSourceCSV)
            ->percentages(1)
            ->execute();
        $this->assertEquals(
            $result,
            [
                [
                    'apple' => 25.0,
                    'samsung' => 50.0,
                    'htc' => 25.0
                ]
            ]
        );
    }
}
