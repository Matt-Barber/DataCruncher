<?php
/**
 * XML File Tests
 *
 * @package DataCruncher
 * @subpackage tests/unit/Helpers
 * @author matt barber <mfmbarber@gmail.com>
 *
 */

namespace mfmbarber\DataCruncher\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;

use mfmbarber\DataCruncher\Tests\Mocks as Mocks;
use mfmbarber\DataCruncher\Helpers\Files\XMLFile as XMLFile;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class XMLFileTest extends TestCase
{

    private $root;

    private $mockSourceXML;
    private $mockOutXML;

    /**
     * Setup creates a virtual file system before each test with a dummy file
     */
    public function setUp()
    {
        $this->root = vfsStream::setup('home', 0777);
        $file = vfsStream::url('home/test', 0777);
        file_put_contents(
            $file,
            "<?xml version='1.0'?>
            <breakfast_menu>
                <food>
                    <name>Belgian Waffles</name>
                    <price>$5.95</price>
                    <description>
                        Two of our famous Belgian Waffles with plenty of real maple syrup
                    </description>
                    <calories>650</calories>
                </food>
                <food>
                    <name>Strawberry Belgian Waffles</name>
                    <price>$7.95</price>
                    <description>
                        Light Belgian waffles covered with strawberries and whipped cream
                    </description>
                    <calories>900</calories>
                </food>
                <food>
                    <name>Berry-Berry Belgian Waffles</name>
                    <price>$8.95</price>
                    <description>
                        Light Belgian waffles covered with an assortment of fresh berries and whipped cream
                    </description>
                    <calories>900</calories>
                </food>
                <food>
                    <name>French Toast</name>
                    <price>$4.50</price>
                    <description>
                        Thick slices made from our homemade sourdough bread
                    </description>
                    <calories>600</calories>
                </food>
                <food>
                    <name>Homestyle Breakfast</name>
                     <price>$6.95</price>
                    <description>
                        Two eggs, bacon or sausage, toast, and our ever-popular hash browns
                    </description>
                    <calories>950</calories>
                </food>
            </breakfast_menu>"
        );
        vfsStream::url('/home/test_out', 0777);
        $this->mockSourceXML = new XMLFile('food', 'breakfast_menu');
        $this->mockOutXML = new XMLFile('food', 'breakfast_menu');
        $this->mockSourceXML->setSource('vfs://home/test', ['fileMode' => 'r']);
        $this->mockOutXML->setSource('vfs://home/test_out', ['fileMode' => 'w']);
    }

    /**
     * After each test clear down our vfs
     */
    public function tearDown()
    {
        $this->root = null;
        $this->mockSourceXML = null;
        $this->mockOutXML = null;
    }

    /**
     * It should return an array of headers and values
     *
     *
     * @return void
    **/
    public function testItShouldReturnFirstBreakfastMenuElement()
    {
        $this->mockSourceXML->open();
        $this->assertEquals(
            $this->mockSourceXML->getNextDataRow()->current(),
            [
               'name' => 'Belgian Waffles',
               'price' => '$5.95',
               'description' => 'Two of our famous Belgian Waffles with plenty of real maple syrup',
               'calories' => '650'
            ],
            "Get next data row did not return the correct assoc_array"
        );
    }
    /**
     * Unit test, If a file pointer hasn't been opened and a read is attempted
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerInvalidException
     * @expectedExceptionMessage The filepointer is null on this object, use class::open to open a new filepointer
     *
     * @return null
     */
    public function testItShouldThrowAnExceptionIfNotOpen()
    {
        $this->mockOutXML->writeDataRow([]);
    }
    /**
     * It should write the correct data back to the file
     *
     *
     * @return void
     */
    public function testItShouldWriteALineToAnXMLFile()
    {
        vfsStream::url('/home/heros', 0777);
        $testNewXML = new XMLFile('hero', 'avengers');
        $testNewXML->setSource('vfs://home/heros', ['fileMode' => 'w']);
        $testNewXML->open();
        $testNewXML->writeDataRow(
            [
                'name' => 'tony',
                'hero' => 'iron man'
            ]
        );
        $testNewXML->close();
        unset($testNewXML);

        $testNewXML = new XMLFile('hero', 'avengers');
        $testNewXML->setSource('vfs://home/heros', ['fileMode' => 'r']);
        $testNewXML->open();
        $this->assertEquals(
            $testNewXML->getNextDataRow()->current(),
            [
                'name' => 'tony',
                'hero' => 'iron man'
            ],
            "writeDataRow didn't write back the correct data"
        );
    }

    /**
     * Testing Writing to an XMLFile Source
     */
    public function testItShouldReturnFalseIfWritingEmptyRow()
    {
        $this->mockOutXML->open();
        $this->assertFalse(
            $this->mockOutXML->writeDataRow([])
        );
    }
    /**
     * Unit test, If a file pointer hasn't been opened and a write is attempted
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerInvalidException
     * @expectedExceptionMessage The filepointer is null on this object, use class::open to open a new filepointer
     *
     * @return null
     */
    public function testItShouldThrowAnErrorOnWriteBeforeOpen()
    {
        $this->mockOutXML->writeDataRow([]);
    }
    /**
     * Unit test, If a file pointer has been opened and another open is called
     *
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerExistsException
     * @expectedExceptionMessage A filepointer exists on this object, use class::close to close the current pointer
     *
     * @return null
     */
    public function testItShouldThrowAnErrorIfAlreadyOpen()
    {
        // initial open
        $this->mockSourceXML->open();
        // subsequent open
        $this->mockSourceXML->open();
    }

    /**
     * Testing Resetting the filepointer to the start of the file
     */
    public function testItShouldResetTheFilePointerToTheStartOfTheFile()
    {
        $this->mockSourceXML->open();
        $this->mockSourceXML->getNextDataRow()->current();
        $this->mockSourceXML->getNextDataRow()->next();
        $this->mockSourceXML->reset();
        $this->assertEquals(
            $this->mockSourceXML->getNextDataRow()->current(),
            [
               'name' => 'Belgian Waffles',
               'price' => '$5.95',
               'description' => 'Two of our famous Belgian Waffles with plenty of real maple syrup',
               'calories' => '650'
            ]
        );
    }

    /**
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerInvalidException
     * @expectedExceptionMessage The filepointer is null on this object, use class::open to open a new filepointer
     */
    public function testItShouldThrowAnErrorIfResetBeforeSet()
    {
        $this->mockSourceXML->reset();
    }

    /**
     * @expectedException        mfmbarber\DataCruncher\Exceptions\FilePointerInvalidException
     * @expectedExceptionMessage The filepointer is null on this object, use class::open to open a new filepointer
     */
    public function testItShouldThrowAnErrorIfCloseBeforeOpen()
    {
        $this->mockSourceXML->close();
    }
}
