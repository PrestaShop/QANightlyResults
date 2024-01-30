<?php

namespace App\Command;

use App\Service\ReportImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'nightly:import')]
class ImportCommand extends Command
{
    private ReportImporter $reportImporter;

    private string $nightlyGCPUrl;

    public function __construct(ReportImporter $reportImporter, string $nightlyGCPUrl)
    {
        parent::__construct();

        $this->reportImporter = $reportImporter;
        $this->nightlyGCPUrl = $nightlyGCPUrl;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filename', InputArgument::REQUIRED)
            ->addOption('platform', 'p', InputOption::VALUE_REQUIRED, '', ReportImporter::FILTER_PLATFORMS[0], ReportImporter::FILTER_PLATFORMS)
            ->addOption('campaign', 'c', InputOption::VALUE_REQUIRED, '', ReportImporter::FILTER_CAMPAIGNS[0], ReportImporter::FILTER_CAMPAIGNS)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/', $input->getArgument('filename'), $matchesVersion);
        if (!isset($matchesVersion[1]) || strlen($matchesVersion[1]) < 1) {
            $output->writeln(sprintf(
                '<error>Version found not correct (%s) from filename %s</error>',
                $matchesVersion[1],
                $input->getArgument('filename')
            ));

            return Command::FAILURE;
        }

        $fileContent = @file_get_contents($this->nightlyGCPUrl . 'reports/' . $input->getArgument('filename'));
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
            ReportImporter::FORMAT_DATE_MOCHA6,
            $jsonContent->stats->start
        );

        $this->reportImporter->import(
            $input->getArgument('filename'),
            $input->getOption('platform'),
            $input->getOption('campaign'),
            $matchesVersion[1],
            $startDate,
            $jsonContent,
            ReportImporter::FORMAT_DATE_MOCHA6
        );

        return Command::SUCCESS;
    }
}
