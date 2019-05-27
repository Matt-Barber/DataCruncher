<?php
namespace mfmbarber\DataCruncher\Tests\Integration\CSVTests;
use PHPUnit\Framework\TestCase;
use mfmbarber\DataCruncher\Config\Validation;
use mfmbarber\DataCruncher\Helpers\DataSource;
use mfmbarber\DataCruncher\Processor;

/**
 * Test the CSV File object end to end using components
 *
 * @author Matt Barber <mfmbarber@gmail.com>
 * @package DataCruncher : Integration Tests
 *
**/
class CSVTest extends TestCase
{
    private $file;

    public function __construct()
    {
        //parent::construct();
        $this->dir = getcwd() . '/tests/integration/CSVTests';
        $this->file = "{$this->dir}/InputFiles/1000row6columndata.csv";
    }

    public function setup()
    {
        $this->sourceCSV = DataSource::generate('file', 'csv');
        $this->sourceCSV->setSource($this->file, ['fileMode' => 'r']);
        $this->sourceCSV->sort('id', true, false);
    }

    public function tearDown()
    {
        // let's remove any outputfiles.
        Validation::deleteFiles("{$this->dir}/OutputFiles", ['md']);
    }

    public function testItShouldSortTheResultsByString()
    {
        $this->sourceCSV->sort('first_name', false, false);
        $this->sourceCSV->open();
        $row = $this->sourceCSV->getNextDataRow()->current();
        $this->sourceCSV->close();
        $this->assertContains('Aaron', $row);
    }

    public function testItShouldSortTheResultByInt()
    {
        $this->sourceCSV->sort('id', true, false);
        $this->sourceCSV->open();
        $row = $this->sourceCSV->getNextDataRow()->current();
        $this->sourceCSV->close();
        $this->assertEquals(
                $row,
                [
                    'id' => 1,
                    'first_name' => 'Paul',
                    'last_name' => 'Simmons',
                    'email' => 'psimmons0@state.gov',
                    'gender' => 'Male',
                    'ip_address' => '139.134.139.43',
                    'age' => '37'
                ]
        );

    }

    /**
     * As a user I want to query a CSV file and get back an array
     *
     * @return null
    **/
    public function testItShouldReturnAnArrayOnceQueried()
    {
        $query = Processor::generate('segmentation', 'query');
        $result = $query->from($this->sourceCSV)
            ->select(['id', 'first_name'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->execute();
        $this->assertEquals(count($result), 4);

    }

    /**
     * As a user I want to query a CSV file and get back a CSV file
     *
     * @return null
    **/
    public function testItShouldCreateAnOutFileOnceQueried()
    {
        $query = Processor::generate('segmentation', 'query');
        $out = DataSource::generate('file', 'csv');
        $out->setSource(
            "{$this->dir}/OutputFiles/EmailContainsStumbleUpon.csv",
            ['fileMode' => 'w']
        );
        $result = $query->from($this->sourceCSV)
            ->select(['id', 'first_name'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->out($out)
            ->execute();
        $this->assertEquals(
            file_get_contents(
                "{$this->dir}/OutputFiles/EmailContainsStumbleUpon.csv"
            ),
            "id,first_name\n".
            "11,Clarence\n".
            "225,Ashley\n".
            "240,Catherine\n".
            "852,Sharon\n"
        );
    }

    /**
     * As a user I want to query a CSV file and get back the results as a file
     * calculating elapsed time and memory. This should take less than 10 miliseconds
     *
     * @return null
    **/
    public function testItShouldReturnAnExecutionTimeAndMemoryUsage()
    {
        $query = Processor::generate('segmentation', 'query');
        $out = Datasource::generate('file', 'csv');
        $out->setSource(
            "{$this->dir}/OutputFiles/EmailContainsStumbleUpon.csv",
            ['fileMode' => 'w']
        );
        $result = $query->from($this->sourceCSV)
            ->select(['id', 'first_name', 'ip_address'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->timer()
            ->out($out)
            ->execute();
        $this->assertTrue(isset($result['timer']));
        $this->assertTrue(
            isset($result['timer']['elapsed']) &&
            isset($result['timer']['memory'])
        );
        $this->assertTrue(
            $result['timer']['elapsed'] < 500,
            "Timer took longer than 500 miliseconds - somethings up!"
        );
    }

    /**
     * As a user I want to query a CSV file, and get back the results as an XML file
     *
     * @return null
    **/
    public function testItShouldReturnAnXMLFile()
    {
        $query = Processor::generate('segmentation', 'query');
        $out = DataSource::generate('file', 'xml', 'person', 'people');
        $out->setSource(
            "{$this->dir}/OutputFiles/EmailContainsStumbleUpon.xml",
            ['fileMode' => 'w']
        );
        $result = $query->from($this->sourceCSV)
            ->select(['id', 'first_name'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->out($out)
            ->execute();
        $xml = preg_replace('/\s+/', '', file_get_contents(
            "{$this->dir}/OutputFiles/EmailContainsStumbleUpon.xml"
        ));
        $expected_xml = preg_replace('/\s+/', '', "
        <?xml version=\"1.0\"?>
        <people>
            <person>
                <id>11</id>
                <first_name>Clarence</first_name>
            </person>
            <person>
                <id>225</id>
                <first_name>Ashley</first_name>
            </person>
            <person>
                <id>240</id>
                <first_name>Catherine</first_name>
            </person>
            <person>
                <id>852</id>
                <first_name>Sharon</first_name>
            </person>
        </people>");
        $this->assertEquals($xml, $expected_xml);
    }

    /**
     *  As a user I want to get the statitics of a CSV file using one rule
     *
     * @return null
    **/
    public function testItShouldReturnStatistics()
    {
        $stats = Processor::generate('analysis', 'statistics');
        $rule = Processor::generate('analysis', 'rule');
        $rule = $rule->setField('gender')->groupExact();
        $stats->addRule($rule);
        $result = $stats->from($this->sourceCSV)
            ->execute();
        $result = array_pop($result);
        $this->assertEquals(
            [
                'Male' => 534,
                'Female' => 466
            ],
            $result,
            "Execute did not return the expected results"
        );

    }

    /**
     * As a user I want to get the statistics of a CSV file using multiple rules
     * @return null
    **/
    public function testItShouldReturnMultipleStatistics()
    {
        $stats = Processor::generate('analysis', 'statistics');

        $rule = Processor::generate('analysis', 'rule');
        $rule = $rule->setField('gender')->groupExact();
        $stats->addRule($rule);

        $rule = Processor::generate('analysis', 'rule');
        $rule = $rule->setField('email')->groupRegex('/(((?<=\.)\w{2,4}\.\w{2,4}|(?<=\.)\w{2,4}))$/i');
        $stats->addRule($rule);

        $result = $stats->from($this->sourceCSV)
            ->execute();
        $this->assertEquals(
            $result[0]['Male'],
            534,
            "Execute did not return the expected results"
        );
        $this->assertEquals(
            $result[1]['gov'],
            57,
            "Execute did not return the expected results"
        );
    }

    /**
     * As a user I want to query a CSV and get back the results as a CSV file, but with different mappings
     *
     * @return null
    **/
    public function testItShouldRemapTheOutput()
    {
        $query = Processor::generate('segmentation', 'query');
        $out = Datasource::generate('file', 'csv');
        $out->setSource(
            "{$this->dir}/OutputFiles/EmailContainsStumbleUpon.csv",
            ['fileMode' => 'w']
        );
        $result = $query->from($this->sourceCSV)
            ->select(['id', 'first_name', 'ip_address'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->out($out)
            ->mappings([
                'first_name' => 'Forename',
                'ip_address' => 'IP'
            ])
            ->execute();
        $this->assertEquals(
                file_get_contents("{$this->dir}/OutputFiles/EmailContainsStumbleUpon.csv"),
                "id,Forename,IP\n".
                "11,Clarence,216.116.2.195\n".
                "225,Ashley,8.240.98.205\n".
                "240,Catherine,222.24.26.173\n".
                "852,Sharon,0.207.225.77\n"
        );


    }

    /**
     * As a user I want to query a CSV file and get back the results as a sorted CSV file
     *
     * @return null
    **/
    public function testItShouldSortTheResults()
    {
        $query = Processor::generate('segmentation', 'query');
        $out = Datasource::generate('file', 'csv');
        $out->setSource(
            "{$this->dir}/OutputFiles/EmailContainsStumbleUpon.csv",
            ['fileMode' => 'w']
        );
        $result = $query->from($this->sourceCSV)
            ->select(['id', 'email'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->out($out)
            ->orderBy('email')
            ->execute($out);
        $this->assertEquals(
            file_get_contents("{$this->dir}/OutputFiles/EmailContainsStumbleUpon.csv"),
            "id,email\n".
            "225,abrown68@stumbleupon.com\n".
            "11,cdixona@stumbleupon.com\n".
            "240,cgarcia6n@stumbleupon.com\n".
            "852,sbakernn@stumbleupon.com\n",
            "Doesn't meet expected response"
        );
    }

    /**
     * As a user I want to limit the amount of results from a queried CSV file as an array
     *
     * @return null
    **/
    public function testItShouldLimitTheAmountOfResults()
    {
        $query = Processor::generate('segmentation', 'query');
        $result = $query->from($this->sourceCSV)
            ->select(['id'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->limit(1)
            ->execute();
        $this->assertEquals(
            $result,
            [['id' => 11]]
        );

    }

    /**
     * As a user I want to merge two CSV files together into a single file, based on the email column
     *
     * @return null
    **/
    public function testItShouldMergeTwoFiles()
    {
        $merger = Processor::generate('segmentation', 'merge');
        $csv = Datasource::generate('file', 'csv');
        $csv->setSource(
            "{$this->dir}/InputFiles/MergeRows.csv",
            ['fileMode' => 'r']
        );
        $result = $merger->from($this->sourceCSV)
            ->from($csv)
            ->using('email')
            ->execute();
        $this->assertEquals(
            $result,
            [
                [
                    "id" => '1',
                    "first_name" => "Paul",
                    "last_name" => "Simmons",
                    "email" => "psimmons0@state.gov",
                    "gender" => "Male",
                    "ip_address" => "139.134.139.43",
                    "age" => '37',
                    'colour' => 'red'
                ]
            ]
        );
    }

}
