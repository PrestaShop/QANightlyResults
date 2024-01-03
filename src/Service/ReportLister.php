<?php

namespace App\Service;

class ReportLister
{
    public string $url;

    public function __construct(string $nightlyGCPUrl)
    {
        $this->url = $nightlyGCPUrl;
    }

    public function get(): array
    {
        $return = file_get_contents($this->url);
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
                if (!isset($GCP_files_list[$date][$version])) {
                    $listing[$date][$version] = [];
                }
                $listing[$date][$version][$extension] = $buildName;
            }
        }

        return $listing;
    }
}
