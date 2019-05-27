<?php
namespace mfmbarber\DataCruncher\Tests\Unit;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Processor as Processor;

use Faker\Generator;

use mfmbarber\DataCruncher\Segmentation\{Query, Split, Merger};
use mfmbarber\DataCruncher\Analysis\{Statistics, Find, Config};


class ProcessorTest extends TestCase
{
    public function testItShouldGenerateAQueryObject()
    {
        $obj = Processor::generate('segmentation', 'query');
        $this->assertEquals(
            get_class($obj),
            get_class(new Query())
        );
    }

    public function testItShouldGenerateAStatisticsObject()
    {
        $obj = Processor::generate('analysis', 'statistics');
        $this->assertEquals(
            get_class($obj),
            get_class(new Statistics())
        );
    }

    public function testItShouldGenerateAMergerObject()
    {
        $obj = Processor::generate('segmentation', 'merge');
        $this->assertEquals(
            get_class($obj),
            get_class(new Merger())
        );
    }

    public function testItShouldGenerateASplitObject()
    {
        $obj = Processor::generate('segmentation', 'split');
        $this->assertEquals(
            get_class($obj),
            get_class(new Split())
        );
    }

    public function testItShouldGenerateARuleObject()
    {
        $obj = Processor::generate('analysis', 'rule');
        $this->assertEquals(
            get_class($obj),
            get_class(new Config\Rule())
        );
    }
}
