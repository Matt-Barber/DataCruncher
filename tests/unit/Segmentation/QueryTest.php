<?php
namespace mfmbarber\DataCruncher\Tests\Unit\Segmentation;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Helpers\Files\CSVFile as CSVFile;
use mfmbarber\DataCruncher\Segmentation\Query as Query;

use org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamDirectory;

class QueryTest extends TestCase
{

    private $root;

    private $mockSourceCSV;
    private $mockOutCSV;

    /**
     * Generate mock file for us to use, stubbing out the readable, writable and fileExists methods.
     * Our file won't technically exist.
     * @param string    $class_name     The class to mock
     *
     * @return mock
    **/
    private function generateMockFile($class_name)
    {
        $file = $this->getMockBuilder($class_name)
        ->setMethods(['fileExists', 'readable', 'writable'])
        ->getMock();
        $file->method('readable')->willReturn(true);
        $file->method('writable')->willReturn(true);
        $file->method('fileExists')->willReturn(true);
        return $file;
    }

    public function setUp()
    {
        parent::setUp();
        $this->root = vfsStream::setup('home', 0777);
        $file = vfsStream::url('home/test', 0777);
        file_put_contents(
            $file,
            "email, name, colour, dob, age\n"
            ."mfmbarber@test.com, matt, \"black, green, blue\", 24/11/1987, 28\n"
            ."matt.barber@test.com, matthew, \"red, green\", 01/12/1980, 35\n"
            ."tony.stark@avengers.com, tony, \"red, gold\", 02/05/1990, 25\n"
            ."no_name@something.com, , \"green\", 01/01/2000, fifteen"
        );
        vfsStream::url('home/test_out', 0777);
        $this->mockSourceCSV = $this->generateMockFile('mfmbarber\DataCruncher\Helpers\Files\CSVFile');
        $this->mockSourceCSV->setSource('vfs://home/test', ['fileMode' => 'r']);
        $this->mockOutCSV = $this->generateMockFile('mfmbarber\DataCruncher\Helpers\Files\CSVFile');
        $this->mockOutCSV->setSource('vfs://home/test_out', ['fileMode' => 'w']);
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->root = null;
        $this->mockSourceCSV = null;
        $this->mockOutCSV = null;
    }
    /**
     * Tests that query execution returns the appropriate array (structure /
     * values)
     *
     * @dataProvider queryDataProvider
     * @param        array $query_data The data for the test
     * @param        array $expected   The expected result, given the query_data
     *
     * @return null
    **/
    public function testItShouldExecuteValidQuery($query_data, $expected)
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select($query_data['select'])
            ->where($query_data['where'])
            ->condition($query_data['condition'])
            ->value($query_data['value'])
            ->execute();

        $this->assertEquals(
            $expected,
            $result,
            "Execute did not return the expected results"
        );
    }
    /**
     * Tests the limiting of results, sometimes we might just want back a sample
     * set of data.
     *
     * @return null
     **/
    public function testItShouldLimitTheAmountOfResults()
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select(['email'])
            ->where('email')
            ->condition('contains')
            ->value('test.com')
            ->limit(1)
            ->execute();

        $this->assertEquals(
            count($result),
            1,
            'Result returned more than 10'
        );
    }
    /**
     * Allows us to remap the field names from the original headers to whatever we choose.
     *
     * @return null
    **/
    public function testItShouldLetUsRemapTheResultFields()
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select(['email'])
            ->where('email')
            ->condition('contains')
            ->value('test.com')
            ->mappings(['email' => 'EMAIL ADDRESS'])
            ->limit(1)
            ->execute();

        $this->assertTrue(in_array('EMAIL ADDRESS', array_keys($result[0])));
    }

    /**
     * Tests that query (with dates) execution returns the appropriate array
     * (struture / values)
     *
     * @dataProvider queryDateDataProvider
     * @param        array $query_data The data for the test
     * @param        array $expected   The expected result, given the query_data
     *
     * @return null
    **/
    public function testItShouldHandleDateQueries($query_data, $expected)
    {
        $query = new Query();

        // This improves readability over call_user_func_array
        $whereField = $query_data['where'][0];
        $whereFormat = $query_data['where'][1];
        $matchVal = $query_data['value'][0];
        $matchFormat = $query_data['value'][1];

        $result = $query->from($this->mockSourceCSV)
            ->select($query_data['select'])
            ->condition($query_data['condition'])
            ->where($whereField, $whereFormat)
            ->value($matchVal, $matchFormat)
            ->execute();
        $this->assertEquals(
            $expected,
            $result,
            "Execute did not return the expected results"
        );
    }
    /**
     * Unit test, date format or value in value clause of query is incorrect
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\InvalidDateValueException
     * @expectedExceptionMessage Couldn't create datetime object from value/dateFormat - please check
     *
     * @return null
    **/
    public function testItShouldThrowAnErrorGivenAMalformedDate()
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select(['dob'])
            ->condition('after')
            ->where('dob', 'd/m/Y')
            ->value('not a date', 'Y')
            ->execute();
    }
    /**
     * Given the wrong data type for the select method, throw error
     *
     * @expectedException        TypeError
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionIfSelectIsNotAnArray()
    {
        $query = new Query();

        $result = $query->from($this->mockSourceCSV)
            ->select('dob')
            ->condition('equals')
            ->where('name')
            ->value('matt')
            ->execute();
    }

    /**
     * Given the wrong type of array, throw error
     *
     * @expectedException         mfmbarber\DataCruncher\Exceptions\ParameterTypeException
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionIfSelectIsAnAssocArray()
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select(['a' => 'b'])
            ->condition('equals')
            ->where('name')
            ->value('matt')
            ->execute();
    }

    /**
     * @expectedException           \Exception
     * @expectedExceptionMessage    One or more of toaster is not in email, name, colour, dob, age
    **/
    public function testItShouldThrowAnExceptionIfFieldIsNotInSourceBeforeSource()
    {
        $query = new Query();
        $query->select(['toaster'])->from($this->mockSourceCSV);
    }

    /**
     * @expectedException            \Exception
     * @expectedExceptionMessage     One or more of toaster is not in email, name, colour, dob, age
    **/
    public function testItShouldThrowAnExceptionIfFieldIsNotInSourceAfterSource()
    {
        $query = new Query();
        $query->from($this->mockSourceCSV)->select(['toaster']);
    }

    /**
     * Given the wrong data type for the where method, throw error
     *
     * @expectedException        TypeError
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionIfWhereIsNotAPrimitive()
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select(['dob'])
            ->condition('equals')
            ->where(['name'])
            ->value('matt')
            ->execute();
    }
    /**
     * Given an incorrect condition throw an InvalidValueException
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\InvalidValueException
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionIfConditionDoesntExist()
    {
        $query = new Query();

        $result = $query->from($this->mockSourceCSV)
            ->select(['dob'])
            ->condition('might be like')
            ->where('name')
            ->value('matt')
            ->execute();
    }
    /**
     * Tests that execution can write to a file, and return a count of the rows
     * affected
     *
     * @return null
    **/
    public function testItShouldWriteToAFileAndReturnResultCount()
    {
        $query = new Query();

        $result = $query->from($this->mockSourceCSV)
            ->select(['email'])
            ->where('name')
            ->condition('contains')
            ->value('matt')
            ->out($this->mockOutCSV)
            ->execute();

        $this->assertEquals(
            ['data' => 2],
            $result,
            "Execute did not return the expected results"
        );
    }

    /**
    * Tests that the time of execution can be tracked with an optional Parameter
    *
    * @return null
    **/
    public function testItShouldBeAbleToTrackExecutionTime()
    {
        $query = new Query();

        $result = $query->from($this->mockSourceCSV)
            ->select(['email'])
            ->where('name')
            ->condition('contains')
            ->value('matt')
            ->timer()
            ->execute();
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('timer', $result);
        $this->assertTrue(is_integer($result['timer']['elapsed']));
    }

    /**
     * Tests that a wildcard can be used to get all the fields
     * @return void
    **/
    public function testItShouldAllowWildCardSelect()
    {
        $query = new Query();

        $result = $query->from($this->mockSourceCSV)
            ->select()
            ->where('name')
            ->condition('contains')
            ->value('tony')
            ->execute();
        $this->assertEquals(
            $result,
            [
                [
                    'email' => 'tony.stark@avengers.com',
                    'name' => 'tony',
                    'colour' => 'red, gold',
                    'dob' => '02/05/1990',
                    'age' => '25'
                ]
            ]
        );
    }

    /**
     * Test that ordering the results is valid
     *
     * @return void
    **/
    public function testItShouldOrderResults()
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select(['name', 'age'])
            ->where('colour')
            ->condition('contains')
            ->value('red')
            ->orderBy('age')
            ->execute();
        $this->assertEquals(
            $result,
            [
                [
                    'name' => 'tony',
                    'age' => 25
                ],
                [
                    'name' => 'matthew',
                    'age' => 35
                ]
            ]
        );
    }
    /**
     * Test that the results are distinct
     *
     * @return void
    **/
    public function testItShouldReturnDistinct()
    {
        $query = new Query();
        $result = $query->from($this->mockSourceCSV)
            ->select(['name', 'age'])
            ->where('colour')
            ->condition('contains')
            ->value('red')
            ->distinct()
            ->execute();
        $this->assertEquals(
            [
                [
                    'name' => 'matthew',
                    'age' => 35
                ],
                [
                    'name' => 'tony',
                    'age' => 25
                ]

            ],
            $result
        );
    }

    /**
     * Data provider for executeContains
     *
     * @return array
    **/
    public function queryDataProvider()
    {
        return [
            // Query and expected response - array of rows
            [
                [
                    // Test the equals condition, returns single field
                    'select' => ['email'],
                    'where' => 'name',
                    'condition' => 'equals',
                    'value' => 'matt'
                ],
                [
                    ['email' => 'mfmbarber@test.com']
                ]
            ],
            [
                [
                    // Test the contains condition, returns multiple fields
                    'select' => ['name', 'age'],
                    'where' => 'colour',
                    'condition' => 'contains',
                    'value' => 'red'
                ],
                [
                    ['name' => 'matthew', 'age' => 35],
                    ['name' => 'tony', 'age' => 25]
                ]
            ],
            [
                [
                    // Test the greater than condition
                    'select' => ['name', 'dob'],
                    'where' => 'age',
                    'condition' => 'greater',
                    'value' => 30
                ],
                [
                    ['name' => 'matthew', 'dob' => '01/12/1980']
                ]
            ],
            [
                [
                    // Test the less than condition
                    'select' => ['name'],
                    'where' => 'age',
                    'condition' => 'less',
                    'value' => 30
                ],
                [
                    ['name' => 'matt'],
                    ['name' => 'tony'],
                    ['name' => '']
                ]
            ],
            [
                [
                    // Test the less than condition
                    'select' => ['age'],
                    'where' => 'name',
                    'condition' => 'not',
                    'value' => 'tony'
                ],
                [
                    ['age' => '28'],
                    ['age' => '35'],
                    ['age' => 'fifteen']
                ]
            ],
            [
                [
                    // Test the in condition
                    'select' => ['email', 'colour'],
                    'where' => 'name',
                    'condition' => 'in',
                    'value' => ['matt', 'tony']
                ],
                [
                    [
                        'email' => 'mfmbarber@test.com',
                        'colour' => 'black, green, blue'
                    ],
                    [
                        'email' => 'tony.stark@avengers.com',
                        'colour' => 'red, gold'
                    ]
                ]
            ],
            [
                [
                    // Test the in condition (string)
                    'select' => ['email', 'colour'],
                    'where' => 'name',
                    'condition' => 'in',
                    'value' => 'matt, tony'
                ],
                [
                    [
                        'email' => 'mfmbarber@test.com',
                        'colour' => 'black, green, blue'
                    ],
                    [
                        'email' => 'tony.stark@avengers.com',
                        'colour' => 'red, gold'
                    ]
                ]            ],
            [
                [
                    // Test the empty condition
                    'select' => ['colour', 'dob'],
                    'where' => 'name',
                    'condition' => 'empty',
                    'value' => null
                ],
                [
                    ['colour' => 'green', 'dob' => '01/01/2000']
                ]
            ],
            [
                [
                    // Test the not empty condition
                    'select' => ['dob'],
                    'where' => 'name',
                    'condition' => 'not_empty',
                    'value' => null
                ],
                [
                    ['dob' => '24/11/1987'],
                    ['dob' => '01/12/1980'],
                    ['dob' => '02/05/1990']
                ]
            ],
        ];
    }
    /**
     * Data provider for executeQueryDatesWorkCorrectly
     *
     * @return array
    **/
    public function queryDateDataProvider()
    {
        return [
            [
                [
                    // Test the after condition
                    'select' => ['email'],
                    'where' => ['dob', 'd/m/Y'],
                    'condition' => 'after',
                    'value' => ['1995', 'Y']
                ],
                [
                    ['email' => 'no_name@something.com']
                ]
            ],
            [
                [
                    // Test the after condition
                    'select' => ['email'],
                    'where' => ['dob', 'd/m/Y'],
                    'condition' => 'before',
                    'value' => ['1990', 'Y']
                ],
                [
                    ['email' => 'mfmbarber@test.com'],
                    ['email' => 'matt.barber@test.com']
                ]
            ],
            [
                [
                    // Test the between condition
                    'select' => ['email'],
                    'where' => ['dob', 'd/m/Y'],
                    'condition' => 'between',
                    'value' => [['1980', '1999'], 'Y']
                ],
                [
                    ['email' => 'mfmbarber@test.com'],
                    ['email' => 'matt.barber@test.com'],
                    ['email' => 'tony.stark@avengers.com']
                ]
            ],
            [
                [
                    // Test the not between condition
                    'select' => ['email'],
                    'where' => ['dob', 'd/m/Y'],
                    'condition' => 'not_between',
                    'value' => [['1985', '1995'], 'Y']
                ],
                [
                    ['email' => 'matt.barber@test.com'],
                    ['email' => 'no_name@something.com']
                ]
            ],
            [
                [
                    // Test the on condition
                    'select' => ['email'],
                    'where' => ['dob', 'd/m/Y'],
                    'condition' => 'on',
                    'value' => ['01/01/2000', 'd/m/Y']
                ],
                [
                    ['email' => 'no_name@something.com']
                ]
            ]
        ];
    }

}
