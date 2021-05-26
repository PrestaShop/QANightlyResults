<?php

namespace App\Model;

use Illuminate\Database\Capsule\Manager as DB;

class Execution
{
    /**
     * Return data for the graph
     *
     * @param $version
     * @param $start_date
     * @param $end_date
     *
     * @return mixed
     */
    public static function getGraphData($version, $start_date, $end_date)
    {
        return DB::select('
        SELECT 
            `id`, 
            `start_date`, 
            `end_date`, 
            `version`, 
            `suites`, 
            `tests`, 
            `skipped`, 
            `passes`, 
            `failures`,
            `pending` 
        FROM `execution` 
        WHERE `start_date` >= :start_date 
        AND `start_date` < :end_date
        AND `version` = :version
        ORDER BY `start_date` ASC;', ['start_date' => $start_date, 'end_date' => $end_date, 'version' => $version]);
    }
}
