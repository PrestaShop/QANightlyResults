<?php

namespace App\Controller;

use App\Model\Execution;
use Illuminate\Database\Capsule\Manager;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class GraphController extends BaseController
{
    /**
     * Display statistics data to show in a graph
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function index(Request $request, Response $response): Response
    {
        //possible values
        $parameters = $this->getParameters();
        //check GET values
        $get_query_params = $request->getQueryParams();
        $period = $parameters['periods']['default'];
        $version = $parameters['versions']['default'];
        if (isset($get_query_params['period']) && $this->isValidParameter($get_query_params['period'], $parameters['periods']['values'])) {
            $period = $get_query_params['period'];
        }
        if (isset($get_query_params['version']) && $this->isValidParameter($get_query_params['version'], $parameters['versions']['values'])) {
            $version = $get_query_params['version'];
        }

        switch ($period) {
            case 'last_two_months':
                $start_date = date('Y-m-d', strtotime(' -60 days'));
                $end_date = date('Y-m-d', strtotime(' +1 days'));
                break;
            case 'last_year':
                $start_date = date('Y-m-d', strtotime(' -1 years'));
                $end_date = date('Y-m-d', strtotime(' +1 days'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime(' -30 days'));
                $end_date = date('Y-m-d', strtotime(' +1 days'));
        }

        if (isset($get_query_params['start_date']) && date('Y-m-d', strtotime($get_query_params['start_date'])) == $get_query_params['start_date']) {
            $start_date = $get_query_params['start_date'];
        }
        if (isset($get_query_params['end_date']) && date('Y-m-d', strtotime($get_query_params['end_date'])) == $get_query_params['end_date']) {
            $end_date = $get_query_params['end_date'];
        }

        //get the data
        $executions = Execution::getGraphData($version, $start_date, $end_date);

        $response->getBody()->write(json_encode($executions));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Retrieve the list of all the available parameters
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function parameters(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode($this->getParameters()));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Format a list of all the parameters to use in all methods
     *
     * @return array
     */
    private function getParameters()
    {
        //versions
        $versions_possible_values = ['develop'];
        $versions_possible_values_from_base = Manager::table('execution')
            ->select('version')
            ->groupBy('version')
            ->get();
        if ($versions_possible_values_from_base) {
            foreach ($versions_possible_values_from_base as $v) {
                if (!in_array($v->version, $versions_possible_values)) {
                    $versions_possible_values[] = $v->version;
                }
            }
        }
        $versions_values = [];
        foreach ($versions_possible_values as $v) {
            $versions_values[] = [
                'name' => ucfirst($v),
                'value' => $v,
            ];
        }
        //periods
        $periods_values = [
            [
                'name' => 'Last 30 days',
                'value' => 'last_month',
            ],
            [
                'name' => 'Last 60 days',
                'value' => 'last_two_months',
            ],
            [
                'name' => 'Last 12 months',
                'value' => 'last_year',
            ],
        ];

        return [
            'periods' => [
                'type' => 'select',
                'name' => 'period',
                'values' => $periods_values,
                'default' => $periods_values[0]['value'],
            ],
            'versions' => [
                'type' => 'select',
                'name' => 'version',
                'values' => $versions_values,
                'default' => $versions_values[0]['value'],
            ],
        ];
    }

    /**
     * Check is the parameter is valid
     *
     * @param $parameter
     * @param $values
     *
     * @return bool
     */
    private function isValidParameter($parameter, $values)
    {
        foreach ($values as $value) {
            if ($parameter == $value['value']) {
                return true;
            }
        }

        return false;
    }
}
