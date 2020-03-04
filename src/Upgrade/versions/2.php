<?php

use Illuminate\Database\Capsule\Manager;

function update2() {
    echo "\n Upgrading to version 2...\n";
    try {
        //add the identifier for tests
        Manager::statement('ALTER TABLE `test` ADD `identifier` VARCHAR(300) NOT NULL AFTER `suite_id`;');

        //update the version in database
        Manager::table('settings')->where('name', '=', 'version')->update(['value' => 2]);
    } catch (Exception $e) {
        return false;
    }
    echo "Finished updating database\n\n";
    return true;

}

return update2();
