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
echo "Inserted execution #$execution_id\n";

/*
 * MAIN FUNCTION
 */

$current_campaign_name = '';
$current_file_name = '';
function loopThrough($pdo, $suite, $parent_suite_id = null) {
    global $execution_id;
    global $current_campaign_name;
    global $current_file_name;

    if ($current_campaign_name != extractNames($suite->file, 'campaign')) {
        $current_campaign_name = extractNames($suite->file, 'campaign');
        echo "\n-- Changing campaign to $current_campaign_name\n";
    }
    if ($current_file_name != extractNames($suite->file, 'file')) {
        $current_file_name = extractNames($suite->file, 'file');
        echo "---- Changing file to $current_file_name\n";
    }

    $data_suite = [
        'execution_id' => $execution_id,
        'uuid' => $suite->uuid,
        'title' => $suite->title,
        'campaign' => $current_campaign_name,
        'file' => $current_file_name,
        'duration' => $suite->duration,
        'hasSkipped' => $suite->hasSkipped ? 1 :0,
        'hasPasses' => $suite->hasPasses ? 1 :0,
        'hasFailures' => $suite->hasFailures ? 1 :0,
        'totalSkipped' => $suite->totalSkipped,
        'totalPasses' => $suite->totalPasses,
        'totalFailures' => $suite->totalFailures,
        'hasSuites' => $suite->hasSuites ? 1 :0,
        'hasTests' => $suite->hasTests ? 1 :0,
        'parent_id' => $parent_suite_id,
    ];


    //inserting current suite
    $suite_id = insertSuite($pdo, $data_suite);
    echo "------ suite ID $suite_id\n";

    if ($suite_id) {
        //insert tests
        if (sizeof($suite->tests) > 0) {
            echo "------ suite has tests\n";
            foreach($suite->tests as $test) {
                $data_test = [
                    'suite_id' => $suite_id,
                    'uuid' => $test->uuid,
                    'title' => $test->title,
                    'state' => getTestState($test),
                    'duration' => $test->duration,
                    'error_message' => isset($test->err->message) ? sanitize($test->err->message) : null,
                    'stack_trace' => isset($test->err->estack) ? sanitize($test->err->estack) : null,
                    'diff' => isset($test->err->diff) ? sanitize($test->err->diff) : null,
                ];
                $test_id = insertTest($pdo, $data_test);
                echo "-------- test ID $test_id\n";
            }
        }
        //insert children suites
        if (sizeof($suite->suites) > 0) {
            foreach($suite->suites as $s) {
                loopThrough($pdo, $s, $suite_id);
            }
        }
    } else {
        //damn, this suite already exist...
        //we don't want to abort, just log this
        echo "[ERROR] Error inserting into database...\n";
    }
}

//launching the walker
loopThrough($pdo, getOrFail($file_contents, 'suites'));


//update infos about the whole execution
$query = "SELECT 
COUNT(DISTINCT(s.id)) suites,
COUNT(t.id) tests,
SUM(IF(t.state='passed', 1, 0)) passed,
SUM(IF(t.state='failed', 1, 0)) failed,
SUM(IF(t.state='skipped', 1, 0)) skipped

FROM `execution` e
INNER JOIN `suite` s on s.execution_id = e.id
INNER JOIN `test` t on t.suite_id = s.id
WHERE e.id = :execution_id;";

$sth = $pdo->prepare($query);
$sth->execute(['execution_id' => $execution_id]);
$updated_data = $sth->fetch(PDO::FETCH_ASSOC);

$query = "UPDATE execution
SET suites=:suites, tests=:tests, skipped = :skipped, passes=:passes, failures=:failures, insertion_end_date=NOW()
WHERE id=:execution_id;";

query($pdo, $query, [
    'execution_id' => $execution_id,
    'skipped' => $updated_data['skipped'],
    'suites' => $updated_data['suites'],
    'tests' => $updated_data['tests'],
    'passes' => $updated_data['passed'],
    'failures' => $updated_data['failed'],
]);

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
    query($pdo, $query, $object);
    return $pdo->lastInsertId();
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
    query($pdo, $query, $object);
    return $pdo->lastInsertId();
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
    query($pdo, $query, $object);
    return $pdo->lastInsertId();
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
    return 'unknown';
}