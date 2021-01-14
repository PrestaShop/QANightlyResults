<?php

use Illuminate\Database\Capsule\Manager;

function update3() {
    echo "\n Upgrading to version 3...\n";
    try {

        $tables = ['execution', 'settings', 'suite', 'test'];
        foreach ($tables as $table) {
            // convert tables to utf8mb4
            Manager::statement('ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8mb4;');
        }
        //change "browser" to a more generic "platform" column
        Manager::statement('ALTER TABLE `execution` CHANGE `browser` `platform` VARCHAR(50) NOT NULL DEFAULT \'chromium\';');

        //update the version in database
        Manager::table('settings')->where('name', '=', 'version')->update(['value' => 3]);
    } catch (Exception $e) {
        return false;
    }
    echo "Finished updating database\n\n";
    return true;

}

return update3();
