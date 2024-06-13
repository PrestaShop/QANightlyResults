<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportPlaywrightCommandTest extends KernelTestCase
{
    public function testImportBlockwislist(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('nightly:import:playwright');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--platform' => 'chromium',
            '--campaign' => 'blockwishlist',
            '--database' => 'mysql',
            'filename' => 'blockwishlist_2024-01-25-develop.json',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertEquals('', $output);
    }
}
