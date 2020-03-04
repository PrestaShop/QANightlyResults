<?php

use Illuminate\Database\Capsule\Manager;

require __DIR__ . '/../../vendor/autoload.php';
require('../settings.php');
require('../database.php');

//folders
$upgrade_folders_path = __DIR__.'/versions/';

echo "
------------------------------------
Upgrading QANightlyBoard Database...
------------------------------------\n\n";
$version = Manager::table('settings')->where('name', '=', 'version')->first();

echo "Database version:\t ".$version->value."\n";
echo "Current version: \t ".QANB_VERSION."\n";
if ($version->value >= QANB_VERSION) {
    echo "Version up-to-date. Nothing to do here.\n\n";
    exit(0);
}
echo "\n/!\ Upgrade required.\n\n";

for ($i=$version->value; $i < QANB_VERSION; $i++) {
    echo "- Upgrading from $i to ".($i+1)."...\n";
    $j = $i+1;
    //we need to execute what's in the ($i+1) folder if it exists
    $filename = "$j.php";
    if (file_exists($upgrade_folders_path.$filename)) {
        echo "\t- file $filename found, executing...\n";
        $return = include($upgrade_folders_path.$filename);
        if (!$return) {
            exit("Upgrading to version $j failed.");
        }
    }
    echo "\n";
}
