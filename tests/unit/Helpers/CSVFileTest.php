<?php
namespace mfmbarber\DataCruncher\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Helpers\Files\CSVFile as CSVFile;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class CSVFileTest extends TestCase
{
    private $root;

    private $mockSourceCSV;

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
        );
        $this->mockSourceCSV = new CSVFile();
        $this->mockSourceCSV->setSource('vfs://home/test', ['fileMode' => 'r']);
    }

    public function tearDown()
    {
        $this->root = null;
        $this->mockSourceCSV = null;
    }
    /**
     * @expectedException           mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage    File is not readable
    **/
    public function testItShouldFailIfNoReadPrivilages()
    {
        vfsStream::newFile('locked', 0000)
            ->withContent('Locked File')
            ->at($this->root);
        $csv = new CSVFile();
        $csv->setSource('vfs://home/locked', ['fileMode' => 'r']);
    }

    /**
     * Tests that once assigned the source name can be retrieved
     *
     * @return null
     **/
    public function testItShouldReturnValidSourceName()
    {
        $this->assertEquals(
            $this->mockSourceCSV->getSourceName(),
            'vfs://home/test',
            'Name isn\'t set correctly'
        );
    }

    /**
     * Unit test, retrieving associative array of headers & values from a
     * data stream
     *
     *
     * @return null
     **/
    public function testItShouldReturnTheLinesInSequence()
    {
        $result = [];
        try {
            $this->mockSourceCSV->open();
        } catch (Exceptions\FilePointerExistsException $e) {
            // The stream is already open
        }
        foreach ($this->mockSourceCSV->getNextDataRow() as $validRowCount => $row) {
            $result[] = $row;
        }
        $this->mockSourceCSV->close();
        $this->assertEquals(
            [
                [
                    'email' => 'mfmbarber@test.com',
                    'name' => 'matt',
                    'colour' => 'black, green, blue',
                    'dob' => '24/11/1987',
                    'age' => '28'
                ],
                [
                    'email' => 'matt.barber@test.com',
                    'name' => 'matthew',
                    'colour' => 'red, green',
                    'dob' => '01/12/1980',
                    'age' => '35'
                ],
                [
                    'email' => 'tony.stark@avengers.com',
                    'name' => 'tony',
                    'colour' => 'red, gold',
                    'dob' => '02/05/1990',
                    'age' => '25'
                ]
            ],
            $result
        );
    }

    /**
     * Unit test, Either a file doesn't exist, or isn't readable - throws
     * matching exception with message
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage File doesn't exist
     *
     * @return null
    **/
    public function testItShouldThrowInvalidFileException()
    {
        $sourceFile = new CSVFile();
        $sourceFile->setSource('FakeFile.csv', ['fileMode' => 'r']);
    }

    /**
     * Unit test, If a file pointer hasn't been opened and a close is attempted
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerInvalidException
     * @expectedExceptionMessage The filepointer is null on this object, use class::open to open a new filepointer
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionIfNotOpen()
    {
        $sourceFile = new CSVFile();
        $sourceFile->close();
    }

    /**
     * Unit test, If a file pointer hasn't been opened and a close is attempted
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerExistsException
     * @expectedExceptionMessage A filepointer exists on this object, use class::close to close the current pointer
     *
     * @return null
    **/
    public function testItShouldThrowAnErrorIfAlreadyOpen()
    {
        $csv = new CSVFile();
        $csv->setSource('vfs://home/test', ['fileMode' => 'r']);
        $csv->open();
        $csv->open();
    }

    /**
     * Unit test, If a file pointer hasn't been opened and a reset is attempted
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerInvalidException
     * @expectedExceptionMessage The filepointer is null on this object, use class::open to open a new filepointer
     *
     * @return null
    **/
    public function testItShouldThrowAnErrorIfResetBeforeSet()
    {
        $sourceFile = new CSVFile();
        $sourceFile->reset();
    }
    /**
     * Unit test, If a file pointer hasn't been opened and a write is attempted
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerInvalidException
     * @expectedExceptionMessage The filepointer is null on this object, use class::open to open a new filepointer
     *
     * @return null
    **/
    public function testItShouldThrowAnErrorOnWriteBeforeOpen()
    {
        $sourceFile = new CSVFile();
        $sourceFile->writeDataRow(['email' => 'test@test.com']);
    }

    /**
     * Test that get headers returns an array of headers
     *
     * @return null
    **/
    public function testItShouldReturnHeaders()
    {
        $headers = $this->mockSourceCSV->getHeaders();
        $this->assertEquals(
            ['email', 'name', 'colour', 'dob', 'age'],
            $headers,
            'The headers read don\'t match those expected'
        );
    }

    /**
     * Unit test, if a CSV file isn't a CSV file
     *
     * @expectedException         mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage  The file provided is not in the correct format
     *
     * @return null
    **/
    public function testItShouldThrowAnExceptionIfMalformed()
    {
        $file = vfsStream::url('home/invalidCSV', 0777);
        file_put_contents(
            $file,
            "This is an information set\n"
            ."email, name, colour, dob, age\n"
            ."mfmbarber@test.com, matt, \"black, green, blue\", 24/11/1987, 28\n"
            ."matt.barber@test.com, matthew, \"red, green\", 01/12/1980, 35\n"
            ."tony.stark@avengers.com, tony, \"red, gold\", 02/05/1990, 25\n"
            ."\n\n"
            ."DPA Rules apply"
        );
        $this->mockSourceCSV = new CSVFile();
        $this->mockSourceCSV->setSource('vfs://home/invalidCSV', ['fileMode' => 'r']);

    }
    public function testItShouldAllowUsToPeekAtData()
    {
        $this->mockSourceCSV->open();
        $a = $this->mockSourceCSV->getNextDataRow(true)->current();
        $b = $this->mockSourceCSV->getNextDataRow()->current();
        $this->mockSourceCSV->close();
        $this->assertEquals($a, $b);
    }
}
