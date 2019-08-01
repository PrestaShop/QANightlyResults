<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Base extends CI_Controller
{
    function __construct()
    {
        try {
            parent::__construct();
            $db_obj = $this->load->database('default', TRUE);
            if(!$db_obj->conn_id) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                exit('Unable to connect with database with given db details.');
            }
        } catch(Exception $e) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
            exit('Unable to connect with database with given db details');
        }
    }

    function display($template, $content_data = [], $header_data = [], $footer_data = [])
    {
        $footer_data['GA_key'] = $this->config->item('GA_key');
        $this->load->view('templates/header', $header_data);
        $this->load->view($template, $content_data);
        $this->load->view('templates/footer', $footer_data);
    }
}