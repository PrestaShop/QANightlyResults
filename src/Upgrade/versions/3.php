<?php

use Illuminate\Database\Capsule\Manager;

function update3() {
    echo "\n Upgrading to version 3...\n";
    try {
        //add the identifier for tests
        Manager::statement('ALTER TABLE `test` ADD `identifier` VARCHAR(200) NOT NULL DEFAULT \'\' AFTER `uuid`;');
        Manager::statement('ALTER TABLE `execution` ADD `broken_since_last` INT NULL DEFAULT NULL AFTER `failures`, ADD `fixed_since_last` INT NULL DEFAULT NULL AFTER `broken_since_last`, ADD `equal_since_last` INT NULL DEFAULT NULL AFTER `fixed_since_last`;');

        //update the version in database
        Manager::table('settings')->where('name', '=', 'version')->update(['value' => 3]);
    } catch (Exception $e) {
        return false;
    }
    echo "Finished updating database\n\n";
    return true;

}

return update3();
