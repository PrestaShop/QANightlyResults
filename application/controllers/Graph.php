<?php
class Graph extends MY_Base {

    public function index()
    {
        $this->load->model('Execution');
        $this->load->model('Suite');
        $this->load->model('Test');

        $this->load->helper('url');

        $versions = $this->Execution->getVersions();

        $campaigns = $this->Suite->getCampaigns();

        //GET DATA
        $selected_version   = is_null($this->input->get('version')) ? $versions->row()->version : $this->input->get('version');
        $selected_campaign  = is_null($this->input->get('campaign')) ? null : $this->input->get('campaign');
        $start_date         = is_null($this->input->get('start_date')) ? date('Y-m-d', strtotime('-2 weeks')) : $this->input->get('start_date');
        $end_date           = is_null($this->input->get('end_date')) ? date('Y-m-d') : $this->input->get('end_date');

        $graph_data = $this->Execution->getCustomData([
            'version' => $selected_version,
            'start_date' => $start_date,
            'end_date' => date('Y-m-d', strtotime($end_date) + 3600*24),
            'campaign' => $selected_campaign
        ])->result_array();

        $detailed_graph_data = $this->Execution->getPreciseStats([
            'version' => $selected_version,
            'start_date' => $start_date,
            'end_date' => date('Y-m-d', strtotime($end_date) + 3600*24),
            'campaign' => $selected_campaign
        ])->result_array();


        $content_data = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'selected_campaign' => $selected_campaign,
            'selected_version' => $selected_version,
            'versions' => $versions,
            'campaigns' => $campaigns,
            'graph_data' => $graph_data,
            'detailed_graph_data' => $detailed_graph_data
        ];


        $header_data = [
            'title'     => "Nightlies reports stats",
            'js'        => ['https://cdn.jsdelivr.net/npm/chart.js@2.8.0/dist/Chart.min.js']
        ];

        $this->display('graph', $content_data, $header_data);
    }
}
