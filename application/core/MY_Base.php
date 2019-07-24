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
            exit('Unable to connect with database with given db details : '.$e->getMessage());
        }
    }
}