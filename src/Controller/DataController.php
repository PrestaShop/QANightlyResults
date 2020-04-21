<?php
namespace App\Controller;

use App\Model\Execution;
use Illuminate\Database\Capsule\Manager;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class DataController extends BaseController {

    /**
     * Display data for a badge in GitHub
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function badge(Request $request, Response $response):Response {
        $badge_data = $this->getBadgeData($request);
        $badge = [
            'schemaVersion' => 1,
            'label' => $badge_data['branch'],
            'message' => $badge_data['percent'].'% passed',
            'color' => 'green'
        ];

        if ($badge_data['percent'] < 100) {
            $badge['color'] = 'orange';
        }
        if ($badge_data['percent'] < 80) {
            $badge['color'] = 'red';
        }

        $response->getBody()->write(json_encode($badge));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function svg(Request $request, Response $response):Response {
        $badge_data = $this->getBadgeData($request);
        $color = '#76ca00'; //green
        if ($badge_data['percent'] < 100) {
            $color = '#eba400'; //orange
        }
        if ($badge_data['percent'] < 80) {
            $color = '#e00707'; //red
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="190" height="20">
            <g clip-path="url(#a)">
                <path fill="#444" d="M0 0h58v20H0z"/>
                <path fill="'.$color.'" d="M58 0h85v20H58z"/>
            </g>
            <g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="110"> 
                <text x="285" y="140" transform="scale(0.1)" textLength="430">'.$badge_data['branch'].'</text>
                <text x="1000" y="140" transform="scale(.1)" textLength="750">'.$badge_data['percent'].'% passed</text>
            </g>
        </svg>
        ';
        $response->getBody()->write($svg);
        return $response->withHeader('Content-Type', 'image/svg+xml');
    }

    /**
     * Get all data for badges
     * @param $request
     * @return array|null
     */
    private function getBadgeData($request) {
        $badge = null;
        //default values
        $branch = 'develop';
        $date = null;
        //check GET values
        $get_query_params = $request->getQueryParams();
        if (isset($get_query_params['branch']) && trim($get_query_params['branch']) != '') {
            $branch = trim($get_query_params['branch']);
        }

        if (isset($get_query_params['date']) && trim($get_query_params['date']) != ''
            && date('Y-m-d', strtotime($get_query_params['date'])) == $get_query_params['date']) {
            $date = trim($get_query_params['date']);
        }

        if ($date === null) {
            $execution = Manager::table('execution')
                ->where('version', '=', $branch)
                ->orderBy('start_date', 'DESC')
                ->limit(1)
                ->first();
        } else {
            $execution = Manager::table('execution')
                ->where('version', '=', $branch)
                ->whereRaw('DATE(start_date) = ?', [$date])
                ->first();
        }

        if ($execution) {
            $percent = round($execution->passes * 100 / ($execution->tests - $execution->pending - $execution->skipped), 2);
            $badge = [
                'branch' => $branch,
                'percent' => $percent,
            ];
        }
        return $badge;
    }
}
