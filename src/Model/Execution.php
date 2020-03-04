<?php
namespace App\Model;

use Illuminate\Database\Capsule\Manager as DB;

class Execution {

    /**
     * Return data for the graph
     *
     * @param $period
     * @param $version
     * @return mixed
     */
    public static function getGraphData($period, $version) {
        switch ($period) {
            case 'last_two_months':
                $period_sql = 60;
                break;
            case 'last_year':
                $period_sql = 365;
                break;
            default:
                $period_sql = 30;
        }

        return DB::select("
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
        WHERE `start_date` > DATE_SUB(NOW(), INTERVAL :days DAY)
        AND `version` = :version
        ORDER BY `start_date` ASC;", ['days' => $period_sql, 'version' => $version]);
    }
}
