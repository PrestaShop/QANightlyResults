<?php

declare(strict_types=1);

namespace App\Model;

use Illuminate\Database\Capsule\Manager as DB;

class Execution
{
    /**
     * Return data for the graph
     *
     * @param string $version
     * @param string $start_date
     * @param string $end_date
     *
     * @return mixed
     */
    public static function getGraphData(string $version, string $start_date, string $end_date)
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
