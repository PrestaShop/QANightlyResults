<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportMochaCommandTest extends KernelTestCase
{
    public function testImportAutoupgrade(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('nightly:import:mocha');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--platform' => 'cli',
            '--campaign' => 'autoupgrade',
            '--database' => 'mysql',
            'filename' => 'autoupgrade_2024-01-25-develop.json',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertEquals('', $output);
    }

    public function testImportCore(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('nightly:import:mocha');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--platform' => 'chromium',
            '--campaign' => 'functional',
            '--database' => 'mysql',
            'filename' => '2024-01-25-develop.json',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertEquals('', $output);
    }
}
