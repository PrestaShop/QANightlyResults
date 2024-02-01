<?php

namespace App\Service;

class ReportLister
{
    public string $nightlyReportPath;

    public function __construct(string $nightlyReportPath)
    {
        $this->nightlyReportPath = $nightlyReportPath;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function get(): array
    {
        $return = @file_get_contents($this->nightlyReportPath);
        if (!$return) {
            return [];
        }

        $listing = [];

        $xml = new \SimpleXMLElement($return);
        foreach ($xml->Contents as $content) {
            $buildName = (string) $content->Key;

            foreach (['xml', 'zip'] as $extension) {
                if (strpos($buildName, '.' . $extension) === false) {
                    continue;
                }

                // Extract version and date
                preg_match(
                    '/([0-9]{4}-[0-9]{2}-[0-9]{2})-([A-z0-9\.]*)-prestashop_(.*)\.' . $extension . '/',
                    $buildName,
                    $matches
                );
                if (count($matches) !== 4) {
                    continue;
                }

                $date = $matches[1];
                $version = $matches[2];
                if (!isset($listing[$date][$version])) {
                    $listing[$date][$version] = [];
                }
                $listing[$date][$version][$extension] = $buildName;
            }
        }

        return $listing;
    }
}
