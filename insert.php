<?php

$PDO_USER = 'simon';
$PDO_PASS = 'phpmyadmin';
$PDO_DB = 'prestashop_results';
$PDO_HOST = 'localhost';

$dsn = "mysql:host=$PDO_HOST;dbname=$PDO_DB";

//arguments
if (count($argv) != 2)
{
    die("[ERROR] : You need to pass the file as the first argument\n");
}

$filepath = $argv[1];
if (!is_file($filepath) || !is_readable($filepath)) {
    die("[ERROR] : File $filepath not found or not readable\n");
}

try {
    $file_contents = json_decode(file_get_contents($filepath));
} catch(Exception $e) {
    die("[ERROR] : Can't decode contents of file $filepath\n");
}

try {
    $pdo = new PDO($dsn, $PDO_USER, $PDO_PASS);
} catch(Exception $exception) {
    die("[ERROR] : Can't connect to database : $exception->getMessage()\n");
}

//time for stuff
//create the execution
$stats = getOrFail($file_contents, 'stats');
$execution_data = [
    'ref' => date('YmdHis'),
    'start_date' => date('Y-m-d H:i:s', strtotime($stats->start)),
    'end_date' => date('Y-m-d H:i:s', strtotime($stats->end)),
    'duration' => $stats->duration,
    'version' => getVersion(basename($filepath))
];

$execution_id = insertExecution($pdo, $execution_data);

/*
 * MAIN FUNCTION
 */

$current_campaign_name = '';
$current_file_name = '';
function loopThrough($pdo, $suite, $parent_suite_id = null) {
    global $execution_id;
    global $current_campaign_name;
    global $current_file_name;
    if ($current_campaign_name != extractNames($suite->filename, 'campaign')) {
        $current_campaign_name = extractNames($suite->filename, 'campaign');
    }
    if ($current_file_name != extractNames($suite->filename, 'file')) {
        $current_file_name = extractNames($suite->filename, 'file');
    }

    $data_suite = [
        'execution_id' => $execution_id,
        'uuid' => $suite->uuid,
        'title' => $suite->title,
        'campaign' => $current_campaign_name,
        'file' => $current_file_name,
        'duration' => $suite->duration,
        'hasSkipped' => $suite->hasSkipped,
        'hasPasses' => $suite->hasPasses,
        'hasFailures' => $suite->hasFailures,
        'totalSkipped' => $suite->totalSkipped,
        'totalPasses' => $suite->totalPasses,
        'totalFailures' => $suite->totalFailures,
        'hasSuites' => $suite->hasSuites,
        'hasTests' => $suite->hasTests,
        'parent_id' => $parent_suite_id,
    ];


    //inserting current suite
    $suite_id = insertSuite($pdo, $data_suite);

    if ($suite_id) {
        //insert tests
        if ($suite->hasTests) {
            foreach($suite->tests as $test) {
                $data_test = [
                    'suite_id' => $suite_id,
                    'uuid' => $test->uuid,
                    'title' => $test->title,
                    'state' => getTestState($test),
                    'duration' => $test->duration,
                    'error_message' => $test->error_message,
                    'stack_trace' => $test->stack_trace,
                    'diff' => $test->diff,
                ];
                insertTest($pdo, $data_test);
            }
        }
        //insert children suites
        if ($suite->hasSuites) {
            foreach($suite->hasSuites as $s) {
                loopThrough($pdo, $s, $suite_id);
            }
        }
    } else {
        //damn, this suite already exist...
        //we don't want to abort, just log this
        echo "[WARN] Suite already present in database, skipping...\n";
    }
}

/*
 * SQL FUNCTIONS
 */

/**
 * Insert an execution and return its ID
 * @param $pdo
 * @param $object
 * @return mixed
 */
function insertExecution($pdo, $object)
{
    $query = "INSERT INTO `execution`(`ref`, `start_date`, `end_date`, `duration`, `version`, `suites`, `tests`, `skipped`, `passes`, `failures`)
VALUES (:ref, :start_date, :end_date, :duration, :version, 0, 0, 0, 0, 0);";
    $result = query($pdo, $query, $object);
    return $result->lastInsertId();
}

/**
 * Insert a suite and returns its ID
 * @param $pdo
 * @param $object
 * @return mixed
 */
function insertSuite($pdo, $object)
{
    $query = "INSERT INTO `suite`(`execution_id`, `uuid`, `title`, `campaign`, `file`, `duration`, `hasSkipped`, `hasPasses`, `hasFailures`, `totalSkipped`, `totalPasses`, `totalFailures`, `hasSuites`, `hasTests`, `parent_id`) 
VALUES (:execution_id, :uuid, :title, :campaign, :file, :duration, :hasSkipped, :hasPasses, :hasFailures, :totalSkipped, :totalPasses, :totalFailures, :hasSuites, :hasTests, :parent_id)";
    $result = query($pdo, $query, $object);
    return $result->lastInsertId();
}

/**
 * Insert a test and returns its ID
 * @param $pdo
 * @param $object
 * @return mixed
 */
function insertTest($pdo, $object)
{
    $query = "INSERT INTO `test`(`suite_id`, `uuid`, `title`, `state`, `duration`, `error_message`, `stack_trace`, `diff`) 
VALUES (:suite_id, :uuid, :title, :state, :duration, :error_message, :stack_trace, :diff)";
    $result = query($pdo, $query, $object);
    return $result->lastInsertId();
}

/**
 * Perform a simple prepared query
 * @param $pdo
 * @param $query
 * @param $args
 * @return mixed
 */
function query($pdo, $query, $args)
{
    $req = $pdo->prepare($query);
    return $req->execute($args);
}


/*
 * TOOLS FUNCTIONS
 */

/**
 * Extract th campaign or the filename from the full filepath of the suite
 * @param $filename
 * @param $type
 * @return mixed|null
 */
function extractNames($filename, $type)
{
    if (strlen($filename) > 0) {
        $pattern = '/\/full\/(.*?)\/(.*)/';
        preg_match($pattern, $filename, $matches);
        if ($type == 'campaign') {
            return isset($matches[1]) ? $matches[1] : null;
        }
        if ($type == 'file') {
            return isset($matches[2]) ? $matches[2] : null;
        }
    } else {
        return null;
    }
}

/**
 * Sanitizes text by removing weird characters from error message fields in tests
 * @param $text
 * @return string
 */
function sanitize($text) {
    $StrArr = str_split($text);
    $NewStr = '';
    foreach ($StrArr as $Char) {
        $CharNo = ord($Char);
        if ($CharNo == 163) { $NewStr .= $Char; continue; }
        if ($CharNo > 31 && $CharNo < 127) {
            $NewStr .= $Char;
        }
    }
    return $NewStr;
}


/**
 * Tries to get an attribute of an object, returns null if it doesn't exist
 * @param $contents
 * @param $object
 * @return null
 */
function getOrFail($contents, $object)
{
    if (isset($contents->$object)) {
        return $contents->$object;
    } else {
        return null;
    }
}

/**
 * Get the version from the filename
 * @param $filename
 * @return mixed|string
 */
function getVersion($filename)
{
    $pattern = '/reports_[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*?)\.json/';
    preg_match($pattern, $filename, $matches);
    if (!isset($matches[1]) || $matches[1] == '') {
        return '';
    }
    return $matches[1];
}

/**
 * Get the state of a test, attribute "state" is not always set
 * @param $test
 * @return string
 */
function getTestState($test)
{
    if (isset($test->state)) {
        return $test->state;
    } else {
        if ($test->skipped == true) {
            return 'skipped';
        }
    }
    return 'failed';
}