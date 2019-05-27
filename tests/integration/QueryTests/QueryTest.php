<?php
namespace mfmbarber\DataCruncher\Tests\Integration\QueryTests;
use PHPUnit\Framework\TestCase;
use mfmbarber\DataCruncher\Config\Validation;
use mfmbarber\DataCruncher\Helpers\DataSource;
use mfmbarber\DataCruncher\Processor;


class QueryTest extends TestCase
{
    private $dir;

    public function setUp()
    {
        $this->dir = getcwd() . '/tests/integration/';
    }
    public function tearDown()
    {
        Validation::deleteFiles($this->dir.'QueryTests/OutputFiles', ['md']);
    }

    public function testItShouldQueryACSVFile()
    {
        $query = Processor::generate('segmentation', 'query');
        $csv = DataSource::generate('file', 'csv');
        $file = $this->dir . 'CSVTests/InputFiles/1000row6columndata.csv';
        $csv->setSource($file, ['fileMode' => 'r']);
        $result = $query->from($csv)
            ->select(['id', 'email'])
            ->where('ip_address')
            ->condition('CONTAINS')
            ->value('140.11.')
            ->execute();
        $this->assertEquals(
            $result,
            [
                [
                    'id' => 4,
                    'email' => 'asimmons3@infoseek.co.jp'
                ]
            ]

        );
    }

    public function testItShouldQueryAXMLFile()
    {
        $query = Processor::generate('segmentation', 'query');
        $xml = DataSource::generate('file', 'xml', 'record', 'dataset');
        $file = $this->dir . 'XMLTests/InputFiles/1000row6fielddata.xml';
        $xml->setSource($file, ['fileMode' => 'r']);
        $result = $query->from($xml)
            ->select(['id', 'email'])
            ->where('ip_address')
            ->condition('CONTAINS')
            ->value('106.209.')
            ->execute();
        $this->assertEquals(
            $result,
            [
                [
                    'id' => 4,
                    'email' => 'asimpson3@techcrunch.com'
                ]
            ]

        );
    }

    public function testItShouldOutputCSVString()
    {
        $query = Processor::generate('segmentation', 'query');
        $xml = DataSource::generate('file', 'xml', 'record', 'dataset');
        $file = $this->dir . 'XMLTests/InputFiles/1000row6fielddata.xml';
        $xml->setSource($file, ['fileMode' => 'r']);
        $system = DataSource::generate('system', 'csv');
        $result = $query->from($xml)
            ->select(['id', 'email'])
            ->where('ip_address')
            ->condition('CONTAINS')
            ->value('106.209.')
            ->out($system)
            ->execute();
        $this->assertEquals(
            $result['data'],
            "id,email\n".
            "4,asimpson3@techcrunch.com\n"

        );
    }

    public function testItShouldQueryADatabaseTable(){}

    public function testItShouldOutputToCSV()
    {
        $query = Processor::generate('segmentation', 'query');
        $xml = DataSource::generate('file', 'xml', 'record', 'dataset');
        $csv = DataSource::generate('file', 'csv');
        $file = $this->dir . 'XMLTests/InputFiles/1000row6fielddata.xml';
        $o_file = $this->dir . 'QueryTests/OutputFiles/id990to1000.xml';
        $xml->setSource($file, ['fileMode' => 'r']);
        $csv->setSource($o_file, ['fileMode' => 'w']);
        $result = $query->from($xml)
            ->select(['email'])
            ->where('id')
            ->condition('GREATER')
            ->value(999)
            ->out($csv)
            ->execute();
        $this->assertEquals($result['data'], 1);
        $this->assertEquals(
            file_get_contents($o_file),
            "email\n".
            "jboydrr@unesco.org\n"
        );
    }

    public function testItShouldOutputToOrderedCSV()
    {
        $query = Processor::generate('segmentation', 'query');
        $xml = DataSource::generate('file', 'xml', 'record', 'dataset');
        $csv = DataSource::generate('file', 'csv');
        $file = $this->dir . 'XMLTests/InputFiles/1000row6fielddata.xml';
        $o_file = $this->dir . 'QueryTests/OutputFiles/id990to1000.xml';
        $xml->setSource($file, ['fileMode' => 'r']);
        $csv->setSource($o_file, ['fileMode' => 'w']);
        $result = $query->from($xml)
            ->select(['email', 'first_name'])
            ->where('id')
            ->condition('GREATER')
            ->value(950)
            ->orderBy('first_name')
            ->out($csv)
            ->execute();
        $this->assertEquals($result['data'], 50);
        $this->assertEquals(
            file_get_contents($o_file),
            "first_name,email\n".
            "Aaron,abennettqe@ed.gov\n".
            "Alan,akingrd@tiny.cc\n".
            "Alan,asmithrm@wikimedia.org\n".
            "Anne,agarzaqt@msu.edu\n".
            "Benjamin,btaylorqg@hexun.com\n".
            "Bonnie,brodriguezr4@irs.gov\n".
            "Brandon,bhenryrk@gravatar.com\n".
            "Brenda,breynoldsrh@redcross.org\n".
            "Bruce,bmillsqn@technorati.com\n".
            "Bruce,bgibsonre@state.gov\n".
            "Carlos,cjacksonr1@youku.com\n".
            "Carolyn,cstewartra@arizona.edu\n".
            "Catherine,cturnerqs@admin.ch\n".
            "Charles,claneqz@plala.or.jp\n".
            "Chris,chenryqf@networksolutions.com\n".
            "Christopher,cdeanqh@themeforest.net\n".
            "Debra,djamesrb@indiatimes.com\n".
            "Diana,dwallaceql@deliciousdays.com\n".
            "Donald,dharveyrq@bbc.co.uk\n".
            "Donald,dlaner0@washington.edu\n".
            "Douglas,dpalmerqj@thetimes.co.uk\n".
            "Emily,ehicksqm@wix.com\n".
            "Ernest,ebakerqr@un.org\n".
            "Frank,ffieldsr9@businessinsider.com\n".
            "George,gwillisrg@ask.com\n".
            "Gerald,gjacksonqw@wufoo.com\n".
            "James,jkellyqv@utexas.edu\n".
            "Jean,jboydrr@unesco.org\n".
            "Jeffrey,jbanksqp@canalblog.com\n".
            "Jeremy,jgreenqy@joomla.org\n".
            "Jeremy,jgrahamr6@unesco.org\n".
            "Joan,jmoorequ@mayoclinic.com\n".
            "John,jcolemanr2@123-reg.co.uk\n".
            "Johnny,jgrahamrc@drupal.org\n".
            "Kathy,kgreenrn@cbc.ca\n".
            "Kimberly,klopezrl@pinterest.com\n".
            "Larry,lfowlerrj@sohu.com\n".
            "Lillian,lsanchezrf@usda.gov\n".
            "Lillian,lgrantrp@ning.com\n".
            "Lori,lbarnesr5@a8.net\n".
            "Mark,mfrazierqo@constantcontact.com\n".
            "Martha,mhallr3@aol.com\n".
            "Martha,mbellqk@washington.edu\n".
            "Philip,ptuckerro@time.com\n".
            "Richard,rrodriguezqq@cam.ac.uk\n".
            "Ruth,rcarterqx@wordpress.org\n".
            "Scott,sburtonr8@icio.us\n".
            "Shawn,sberryri@bandcamp.com\n".
            "Shirley,sperkinsr7@adobe.com\n".
            "Timothy,tcastilloqi@weather.com\n"
        );
    }

    public function testItShouldOutputToXML()
    {
        $query = Processor::generate('segmentation', 'query');
        $xml = DataSource::generate('file', 'xml', 'record', 'dataset');
        $csv = DataSource::generate('file', 'csv');
        $file = $this->dir . 'CSVTests/InputFiles/1000row6columndata.csv';
        $o_file = $this->dir . 'QueryTests/OutputFiles/id999to1000.xml';
        $csv->setSource($file, ['fileMode' => 'r']);
        $xml->setSource($o_file, ['fileMode' => 'w']);
        $result = $query->from($csv)
            ->select(['email'])
            ->where('id')
            ->condition('GREATER')
            ->value(999)
            ->out($xml)
            ->execute();
        $this->assertEquals($result['data'], 1);
        $this->assertEquals(
            file_get_contents($o_file),
            "<?xml version=\"1.0\"?>\n".
            "<dataset><record><email>fmillsrr@acquirethisname.com</email></record></dataset>\n"
        );

    }

    public function testItShouldOutputToDBTable(){}

    public function testItShouldQueryEquals()
    {
        $query = Processor::generate('segmentation', 'query');
        $xml = DataSource::generate('file', 'xml', 'record', 'dataset');
        $xml->setSource($this->dir . 'XMLTests/InputFiles/1000row6fielddata.xml');
        $result = $query->from($xml)
            ->select(['email'])
            ->where('id')
            ->condition('EQUALS')
            ->value(600)
            ->execute();
        $this->assertEquals(
            $result,
            [
                [
                    'email' => 'egarzagn@nbcnews.com'
                ]
            ]
        );
    }
    public function testItShouldQueryDates(){}
}
