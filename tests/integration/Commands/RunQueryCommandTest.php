<?php
namespace mfmbarber\DataCruncher\Tests\Integration\Command;

use mfmbarber\DataCruncher\Commands\RunQueryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;


class RunQueryCommandTest extends TestCase
{
    private $dir;

    public function setUp()
    {
        $this->dir = getcwd() . '/tests/integration/';
    }

    /**
     * @return null
    **/
    public function testItShouldRunABasicNonTimedQuery()
    {
        $app = new Application();
        $app->add(new RunQueryCommand());

        $command = $app->find('query');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs([3, 11, 'stumbleupon']);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                'source' => $this->dir . 'CSVTests/InputFiles/1000row6columndata.csv'
            ]
        );
        $output = $commandTester->getDisplay();
        $this->assertContains("id,first_name,last_name,email,gender,ip_address,age", $output);
        $this->assertContains("11,Clarence,Dixon,cdixona@stumbleupon.com,Male,216.116.2.195,31", $output);
        $this->assertContains("225,Ashley,Brown,abrown68@stumbleupon.com,Female,8.240.98.205,24", $output);
        $this->assertContains("240,Catherine,Garcia,cgarcia6n@stumbleupon.com,Female,222.24.26.173,43", $output);
        $this->assertContains("852,Sharon,Baker,sbakernn@stumbleupon.com,Female,0.207.225.77,65", $output);
    }

    /**
     * @return null
    **/
    public function testItShouldRunABasicTimedQuery()
    {
        $app = new Application();
        $app->add(new RunQueryCommand());

        $command = $app->find('query');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs([3, 11, 'stumbleupon']);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                'source' => $this->dir . 'CSVTests/InputFiles/1000row6columndata.csv',
                '--timer' => null
            ]
        );
        $output = $commandTester->getDisplay();
        // replace this assertion with a regex
        $this->assertRegExp('/Time\: [\d]{0,}.[\d]{2}/', $output);
    }

    public function testItShouldRunABasciTimedLimitedQuery()
    {

    }

    public function testItShouldWriteToAnOut()
    {

    }


}
