<?php
namespace mfmbarber\DataCruncher\Tests\Unit\Segmentation;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Segmentation\Merger as Merger;
use mfmbarber\DataCruncher\Helpers\Files\CSVFile as CSVFile;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class MergerTest extends TestCase
{
    private $root;

    private $mockSourceCSV;
    private $mockMergeCSV;

    public function setUp()
    {
        $this->root = vfsStream::setup('home', 0777);
        $sourceFile = vfsStream::url('home/test', 0777);
        file_put_contents(
            $sourceFile,
            "email, name\n"
            ."mfmbarber@test.com, matt \n"
            ."matt.barber@test.com, matthew\n"
            ."tony.stark@avengers.com, tony\n"
            ."no_name@something.com, "
        );
        $mergeFile = vfsStream::url('home/test_out', 0777);
        file_put_contents(
            $mergeFile,
            "email, occupation\n"
            ."mfmbarber@test.com, Developer\n"
            ."matt.barber@test.com, Support\n"
            ."tony.stark@avengers.com, IronMan\n"
            ."hulk@avengers.com, Scientist"
        );
        $this->mockSourceCSV = new CSVFile();
        $this->mockMergeCSV = new CSVFile();
        $this->mockSourceCSV->setSource('vfs://home/test', ['fileMode' => 'r']);
        $this->mockMergeCSV->setSource('vfs://home/test_out', ['fileMode' => 'r']);
    }

    public function tearDown()
    {
        $this->root = null;
        $this->mockSourceCSV = null;
        $this->mockMergeCSV = null;
    }
    /**
     * It should merge two CSVs on the Email Field Correctly.
    **/
    public function testItMergesTwoSourcesOnEmailCorrectly()
    {
        $merger = new Merger();

        $result = $merger->from($this->mockSourceCSV)
            ->from($this->mockMergeCSV)
            ->using('email')
            ->execute();
        $expected = [
            [
                'email' => 'mfmbarber@test.com',
                'name' => 'matt',
                'occupation' => 'Developer'
            ],
            [
                'email' => 'matt.barber@test.com',
                'name' => 'matthew',
                'occupation' => 'Support'
            ],
            [
                'email' => 'tony.stark@avengers.com',
                'name' => 'tony',
                'occupation' => 'IronMan'
            ]
        ];
        $this->assertEquals(
            $result,
            $expected,
            'Merging didn\'t correctly output'
        );
    }

    /**
     * Given no sources return an invalid argument exception
     *
     * @expectedException        InvalidArgumentException
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionWithNoSources()
    {
        $merger = new Merger();
        $merger->using('email')->execute();
    }

    /**
     * Given a field that doesn't exist in the sources
     * throw an invalid argument exception
     *
     * @expectedException        InvalidArgumentException
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionIfTheFieldDoesntExist()
    {
        $merger = new Merger();
        $merger->from($this->mockSourceCSV)
            ->from($this->mockMergeCSV)
            ->using('foo')
            ->execute();
    }

}
