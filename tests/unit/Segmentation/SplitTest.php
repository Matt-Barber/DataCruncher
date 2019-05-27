<?php
namespace mfmbarber\DataCruncher\Tests\Unit\Segmentation;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Segmentation\Split as Split;
use mfmbarber\DataCruncher\Segmentation\Query as Query;
use mfmbarber\DataCruncher\Helpers\Files\CSVFile as CSVFile;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class SplitTest extends TestCase
{
    private $root;

    private $mockSourceCSV;
    private $mockOutFiles = [];

    public function setUp()
    {
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
        $this->mockSourceCSV = new CSVFile();
        $this->mockSourceCSV->setSource('vfs://home/test', ['fileMode' => 'r']);
        foreach (range(0, 4) as $number) {
            vfsStream::url("home/test_out_$number", 0777);
            $outfile = new CSVFile();
            $outfile->setSource("vfs://home/test_out_$number", ['fileMode' => 'w']);
            $this->mockOutFiles[] = $outfile;
        }
    }

    public function tearDown()
    {
        $this->root = null;
        $this->mockSourceCSV = null;
        $this->mockOutFiles = [];
    }
    /**
     * @test
    **/
    public function executeSplitHorizontal()
    {
        $split = new Split();

        $result = $split->fromSource($this->mockSourceCSV)
            ->horizontal(2)
            ->execute();

        $expected = [
            [
                [
                    'email' => 'mfmbarber@test.com',
                    'name' => 'matt',
                    'colour' => 'black, green, blue',
                    'dob' => '24/11/1987',
                    'age' => 28
                ],
                [
                    'email' => 'matt.barber@test.com',
                    'name' => 'matthew',
                    'colour' => 'red, green',
                    'dob' => '01/12/1980',
                    'age' => 35
                ]
            ],
            [
                [
                    'email' => 'tony.stark@avengers.com',
                    'name' => 'tony',
                    'colour' => 'red, gold',
                    'dob' => '02/05/1990',
                    'age' => 25
                ],
                [
                    'email' => 'no_name@something.com',
                    'name' => '',
                    'colour' => 'green',
                    'dob' => '01/01/2000',
                    'age' => 'fifteen'
                ],
            ]
        ];
        $this->assertEquals(
            $result,
            $expected,
            'Execute didn\'t split the data as expected'
        );
    }
    /**
     * @test
    **/
    public function executeSplitVertical()
    {
        $split = new Split();
        $result = $split->fromSource($this->mockSourceCSV)
            ->vertical(['email, name', 'email, age'])
            ->execute();
        $expected = [
            [
                [
                    'email' => 'mfmbarber@test.com',
                    'name' => 'matt'
                ],
                [
                    'email' => 'matt.barber@test.com',
                    'name' => 'matthew'
                ],
                [
                    'email' => 'tony.stark@avengers.com',
                    'name' => 'tony'
                ],
                [
                    'email' => 'no_name@something.com',
                    'name' => ''
                ]
            ],
            [
                [
                    'email' => 'mfmbarber@test.com',
                    'age' => '28'
                ],
                [
                    'email' => 'matt.barber@test.com',
                    'age' => '35'
                ],
                [
                    'email' => 'tony.stark@avengers.com',
                    'age' => '25'
                ],
                [
                    'email' => 'no_name@something.com',
                    'age' => 'fifteen'
                ]
            ],

        ];
        $this->assertEquals(
            $result,
            $expected,
            'The vertical split wasn\'t correct'
        );
    }
    /**
     * @test
    **/
    public function splittingHorizontalReturnsRowCountPerFile()
    {
        $split = new Split();
        $outfiles = [$this->mockOutFiles[0], $this->mockOutFiles[1]];
        $result = $split->fromSource($this->mockSourceCSV)
            ->horizontal(2)
            ->execute($outfiles);
        $this->assertEquals(
            $result,
            [
                0 => 2,
                1 => 2
            ],
            'Expected result not returned'
        );
    }

    /**
     * @test
    **/
    public function splittingHorizontalWritesLinesToFile()
    {
        $split = new Split();
        $outfiles = [$this->mockOutFiles[0], $this->mockOutFiles[1]];
        $result = $split->fromSource($this->mockSourceCSV)
            ->horizontal(2)
            ->execute($outfiles);
        $this->assertEquals(
            file_get_contents($outfiles[0]->getSourceName()),
            "email,name,colour,dob,age\n"
            ."mfmbarber@test.com,matt,\"black, green, blue\",24/11/1987,28\n"
            ."matt.barber@test.com,matthew,\"red, green\",01/12/1980,35\n",
            "Outfile doesn't contain correct data\n"
        );
    }

    /**
     * @test
    **/
    public function splittingVerticalWritesLinesToFile()
    {
        $split = new Split();
        $outfiles = [$this->mockOutFiles[0], $this->mockOutFiles[1]];
        $result = $split->fromSource($this->mockSourceCSV)
            ->vertical(
                [
                    ['email', 'name'],
                    ['email', 'age']
                ]
            )
            ->execute($outfiles);
        $this->assertEquals(
            file_get_contents($outfiles[1]->getSourceName()),
            "email,age\n"
            ."mfmbarber@test.com,28\n"
            ."matt.barber@test.com,35\n"
            ."tony.stark@avengers.com,25\n"
            ."no_name@something.com,fifteen\n",
            "Outfile doesn't contain correct data"
        );
    }
    /**
     * @test
     * @expectedException           InvalidArgumentException
     * @expectedExceptionMessage    Ensure 2 outputs are provided for vertical split, and x for horizontal split
    **/
    public function incorrectAmountOfOutfilesThrowsException()
    {
        $split = new Split();
        $outfiles = [$this->mockOutFiles[0]];
        $result = $split->fromSource($this->mockSourceCSV)
            ->horizontal(2)
            ->execute($outfiles);
    }

    /**
     * @test
     * @expectedException           InvalidArgumentException
     * @expectedExceptionMessage    Size expected to be an integer
     **/
    public function sizeMustBeAnIntegerThrowsException()
    {
        $split = new Split();
        $split->fromSource($this->mockSourceCSV)
           ->horizontal('a')
           ->execute();
    }

    /**
     * @test
     * @expectedException           InvalidArgumentException
     * @expectedExceptionMessage    Ensure 2 outputs are provided for vertical split, and x for horizontal split
    **/
    public function amountOfOutfilesMustMatchGroupingsThrowsException()
    {
        $split = new Split();
        $split->fromSource($this->mockSourceCSV)
            ->vertical([['email', 'name'], ['email', 'age']])
            ->execute([$this->mockOutFiles[0]]);
    }
}
