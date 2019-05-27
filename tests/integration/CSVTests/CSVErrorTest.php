<?php
namespace mfmbarber\DataCruncher\Tests\Integration\CSVTests;
use PHPUnit\Framework\TestCase;
use mfmbarber\DataCruncher\Config\Validation;
use mfmbarber\DataCruncher\Helpers\DataSource;
use mfmbarber\DataCruncher\Processor;

use mfmbarber\DataCruncher\Exceptions\InvalidFileException;


class CSVErrorTest extends TestCase
{
    private $file;

    public function __construct()
    {
        $this->dir = getcwd() . '/tests/integration/CSVTests';
        $this->file = "{$this->dir}/InputFiles/1000row6columndata.csv";
    }

    public function setUp()
    {
        $this->sourceCSV = DataSource::generate('file', 'csv');
        $this->sourceCSV->setSource($this->file, ['fileMode' => 'r']);
        $this->sourceCSV->sort('id');
    }

    public function tearDown()
    {
        Validation::deleteFiles("{$this->dir}/OutputFiles", ['md']);
    }

    /**
     * @expectedException        mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage File doesn't exist
    **/
    public function testItShouldFailIfFileDoesntExist()
    {
        $csv = DataSource::generate('file', 'csv');
        $csv->setSource(
            'FakeFile.csv',
            ['fileMode' => 'r']
        );
    }

    /**
     * @expectedException        mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage The file provided is not in the correct format
    **/
    public function testItShouldFailIfFileIsEmpty()
    {
        $csv = DataSource::generate('file', 'csv');
        $csv->setSource(
            "{$this->dir}/InputFiles/Empty.csv",
            ['fileMode' => 'r']
        );

    }

    /**
     * @expectException
     * @expectedExceptionMessage
    **/
    // public function testItShouldFailWritingToNonEmptyFile()
    // {
    //     $csv = DataSource::generate('file', 'csv');
    //     $csv->setSource(
    //         "{$this->dir}/InputFiles/NonEmpty.csv",
    //         ['fileMode' => 'w']
    //     );
    // }

    /**
     * @expectedException           mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage    File is not set to read mode
    **/
    public function testItShouldFailReadingIfSetToWrite()
    {
        $csv = DataSource::generate('file', 'csv');
        $csv->setSource(
            "{$this->dir}/InputFiles/NonEmpty.csv",
            ['fileMode' => 'w']
        );
        $csv->getNextDataRow()->current();

    }

    /**
     * @expectedException           mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage    The file provided is not in the correct format
    **/
    public function testItShouldFailGivenANonCSVFile()
    {
        $csv = DataSource::generate('file', 'csv');
        $csv->setSource(
            "{$this->dir}/InputFiles/NonCSV.csv",
            ['fileMode' => 'r']
        );

    }

    /**
     * @expectedException           \Exception
     * @expectedExceptionMessage    One or more of fandangle is not in id, first_name, last_name, email, gender, ip_address
    **/
    public function testItShouldFailQueryingAHeaderThatDoesntExist()
    {
        $query = Processor::generate('segmentation', 'query');
        $result = $query->from($this->sourceCSV)
            ->select(['fandangle'])
            ->where('email')
            ->condition('CONTAINS')
            ->value('stumbleupon')
            ->execute();
    }

    /**
     * @expectedException           mfmbarber\DataCruncher\Exceptions\InvalidFileException
     * @expectedExceptionMessage    File is not writable
    **/
    public function testItShouldFailIfNoWritePrivilages()
    {
        $csv = DataSource::generate('file', 'csv');
        $csv->setSource(
            "{$this->dir}/InputFiles/EmptyWriteProtected.csv",
            ['fileMode' => 'w']
        );
    }


}
