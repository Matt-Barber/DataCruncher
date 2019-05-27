<?php
namespace mfmbarber\DataCruncher\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Helpers\System\CSV as CSV;
use mfmbarber\DataCruncher\Helpers\Files\CSVFile;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

use mfmbarber\DataCruncher\Segmentation\Query as Query;

class CSVSystemTest extends TestCase
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
        $this->mockSourceCSV = new CSV();
        //$this->mockSourceCSV->setSource('vfs://home/test', ['fileMode' => 'r']);
    }

    public function tearDown()
    {
        $this->root = null;
        $this->mockSourceCSV = null;
    }

    public function testItShouldReturnHeaders()
    {

    }

    public function testItShouldReturnTheLinesInSequence()
    {

    }

}
