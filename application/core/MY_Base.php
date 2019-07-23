<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Base extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
        $db_obj = $this->load->database('default', TRUE);
        if(!$db_obj->conn_id) {
            exit('Unable to connect with database with given db details.');
        }
    }
}