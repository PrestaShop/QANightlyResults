<?php

namespace App\Controller;

use Exception;
use Illuminate\Database\Capsule\Manager;

class BaseController
{
    /**
     * BaseController constructor.
     * Check environment variables
     *
     * @throws Exception
     */
    public function __construct()
    {
        $environment_variables = [
            'QANB_TOKEN',
        ];
        //verify each required environment variable is set
        foreach ($environment_variables as $var) {
            if (getenv($var) === false) {
                throw new Exception(sprintf('%s environment variable not set.', $var));
            }
        }
        //correct database version ?
        $version = Manager::table('settings')->where('name', '=', 'version')->first();
        $version = $version->value;
        if ($version != QANB_VERSION) {
            throw new Exception('wrong database version, please update.');
        }
    }
}
