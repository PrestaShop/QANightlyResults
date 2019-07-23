<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hook extends MY_Base {

    public function add()
    {
        //hook to add a json file
        if (!$this->input->get('token') || !$this->input->get('filename')) {
            exit("no enough parameters");
        }

        //let(s check if everything is there
        if (!$this->checkToken($this->input->get('token'))) {
            exit("invalid token");
        }

        //get the file from the GCP API
        $filename = $this->input->get('filename');
        //create URL
        $url = sprintf("https://storage.googleapis.com/prestashop-core-nightly/reports/%s", $filename);
        //retrieve content
        $contents = file_get_contents($url);

        file_put_contents(__DIR__.'/test.txt', 'a');

        var_dump(__DIR__.'/toto.json');

    }

    private function checkToken($token) {
        //TODO : check with enrivonment variable
        return true;
    }
}
