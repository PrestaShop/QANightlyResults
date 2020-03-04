<?php
namespace App\Controller;

use App\Model\Execution;
use Illuminate\Database\Capsule\Manager;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class DataController extends BaseController {

    /**
     * Display data for a badge in GitHub
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function badge(Request $request, Response $response):Response {
        $badge = null;
        //default values
        $version = 'develop';
        $date = null;
        //check GET values
        $get_query_params = $request->getQueryParams();
        if (isset($get_query_params['version']) && trim($get_query_params['version']) != '') {
            $version = trim($get_query_params['version']);
        }

        if (isset($get_query_params['date']) && trim($get_query_params['date']) != ''
            && date('Y-m-d', strtotime($get_query_params['date'])) == $get_query_params['date']) {
            $date = trim($get_query_params['date']);
        }

        if ($date === null) {
            $execution = Manager::table('execution')
                ->where('version', '=', $version)
                ->orderBy('start_date', 'DESC')
                ->limit(1)
                ->first();
        } else {
            $execution = Manager::table('execution')
                ->where('version', '=', $version)
                ->whereRaw('DATE(start_date) = ?', [$date])
                ->first();
        }

        if ($execution) {
            $percent = round($execution->passes * 100 / ($execution->tests - $execution->pending - $execution->skipped), 2);
            $badge = [
                'schemaVersion' => 1,
                'label' => 'Nightly - '.$version,
                'message' => $percent.'% passed',
                'color' => 'green',
            ];
            if ($percent < 100) {
                $badge['color'] = 'orange';
            }
            if ($percent < 80) {
                $badge['color'] = 'red';
            }
        }
        $response->getBody()->write(json_encode($badge));
        return $response;
    }
}
