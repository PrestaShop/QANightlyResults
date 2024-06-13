<?php

namespace App\Command;

use App\Service\ReportMochaImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'nightly:import:mocha')]
class ImportMochaCommand extends Command
{
    private ReportMochaImporter $reportImporter;

    private string $nightlyReportPath;

    public function __construct(ReportMochaImporter $reportImporter, string $nightlyReportPath)
    {
        parent::__construct();

        $this->reportImporter = $reportImporter;
        $this->nightlyReportPath = $nightlyReportPath;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filename', InputArgument::REQUIRED)
            ->addOption('platform', 'p', InputOption::VALUE_REQUIRED, '', ReportMochaImporter::FILTER_PLATFORMS[0], ReportMochaImporter::FILTER_PLATFORMS)
            ->addOption('campaign', 'c', InputOption::VALUE_REQUIRED, '', ReportMochaImporter::FILTER_CAMPAIGNS[0], ReportMochaImporter::FILTER_CAMPAIGNS)
            ->addOption('database', 'd', InputOption::VALUE_REQUIRED, '', ReportMochaImporter::FILTER_DATABASES[0], ReportMochaImporter::FILTER_DATABASES)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        preg_match(ReportMochaImporter::REGEX_FILE, $input->getArgument('filename'), $matchesVersion);
        if (!isset($matchesVersion[1]) || strlen($matchesVersion[1]) < 1) {
            $output->writeln(sprintf(
                '<error>Version found not correct (%s) from filename %s</error>',
                $matchesVersion[1],
                $input->getArgument('filename')
            ));

            return Command::FAILURE;
        }

        $fileContent = @file_get_contents($this->nightlyReportPath . 'reports/' . $input->getArgument('filename'));
        if (!$fileContent) {
            $output->writeln('<error>Unable to retrieve content from GCP URL</error>');

            return Command::FAILURE;
        }

        $jsonContent = json_decode($fileContent);
        if (!$jsonContent) {
            $output->writeln('<error>Unable to decode JSON data</error>');

            return Command::FAILURE;
        }

        $startDate = \DateTime::createFromFormat(
            \DateTime::RFC3339_EXTENDED,
            $jsonContent->stats->start
        );

        $this->reportImporter->import(
            $input->getArgument('filename'),
            $input->getOption('platform'),
            $input->getOption('database'),
            $input->getOption('campaign'),
            $matchesVersion[1],
            $startDate,
            $jsonContent
        );

        return Command::SUCCESS;
    }
}
