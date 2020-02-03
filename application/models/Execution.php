<?php

class Execution extends CI_Model
{

    private $table = 'execution';

    public $id;
    public $ref;
    public $filename;
    public $stats;
    public $start_date;
    public $end_date;
    public $duration;
    public $version;
    public $suites;
    public $tests;
    public $skipped;
    public $passes;
    public $failures;
    public $pending;

    /**
     * Find an execution with its ID
     * @param $id
     * @return mixed
     */
    function find($id)
    {
        return $this->db->query("SELECT * FROM $this->table WHERE id = ?;", [$id])->row();
    }

    /**
     * Insert an execution row
     * @param $data
     * @return mixed
     */
    public function insert($data)
    {
        $this->db->insert($this->table, $data);

        return $this->db->insert_id();
    }

    /**
     * Update a given execution
     * @param $data
     * @param $id
     * @return mixed
     */
    public function update($data, $id)
    {
        return $this->db->update($this->table, $data, "id = $id");
    }

    /**
     * Get all fields from an execution
     * @return mixed
     */
    function getAllInformation()
    {
        $sql = "SELECT id, filename, ref, start_date, end_date, duration, version, suites, tests, skipped, passes, failures, pending 
FROM $this->table 
WHERE start_date > DATE_SUB(NOW(), INTERVAL 20 DAY)
ORDER BY start_date;";

        return $this->db->query($sql);
    }

    /**
     * Get precedent report
     *
     * @param $id
     * @return mixed
     */
    function getPrecedentReport($id)
    {
        $sql = "select e.id
from execution e
where version = (select version from execution et where et.id = ?)
and start_date < (select start_date from execution et where et.id = ?)
ORDER BY start_date DESC
LIMIT 1;";

        return $this->db->query($sql, [$id, $id])->row();
    }

    /**
     * Get data comparaison with another report
     *
     * @param $id
     * @return mixed
     */
    function compareDataWithPrecedentReport($report, $reportToCompareWith)
    {
        $sql = "SELECT 
	s1.execution_id old_execution_id,
	t1.id old_test_id,
	t1.title old_test_title,
    t1.identifier old_test_identifier,
    t1.state old_test_state,
	s2.execution_id current_execution_id,
    t2.id current_test_id,
    t2.title current_test_title,
    t2.identifier current_test_identifier,
    t2.state current_test_state
FROM `test` t1
INNER JOIN `suite` s1 ON s1.id = t1.suite_id AND s1.execution_id = ?

CROSS JOIN `test` t2 ON t2.identifier = t1.identifier AND t2.identifier != '' 
INNER JOIN `suite` s2 ON s2.id = t2.suite_id AND s2.execution_id = ?

WHERE t1.identifier != 'loginBO' 
AND t1.identifier != 'logoutBO'
AND (
    t1.state = 'failed' OR t2.state = 'failed'
    );";

        return $this->db->query($sql, [$report, $reportToCompareWith])->result_array();
    }

    /**
     * Get all versions in database
     * @return mixed
     */
    function getVersions() {
        return $this->db->query("SELECT 
                version, count(id) cpt
                FROM (SELECT * FROM $this->table ORDER BY DATE(start_date) DESC LIMIT 20) a
                GROUP BY version
                ORDER BY cpt DESC;");
    }

    /**
     * Get data to display in graphs
     * @param $criteria
     * @return mixed
     */
    function getCustomData($criteria) {
        $req = "SELECT 
                e.id, e.ref, e.start_date, DATE(e.start_date) custom_start_date,e.end_date, e.skipped, e.passes, e.failures, e.pending,
                SUM(IF(t.state = 'skipped', 1, 0)) totalSkipped, 
                SUM(IF(t.state = 'pending', 1, 0)) totalPending, 
                SUM(IF(t.state = 'passed', 1, 0)) totalPasses, 
                SUM(IF(t.state = 'failed', 1, 0)) totalFailures
            FROM $this->table e
            INNER JOIN suite s ON e.id=s.execution_id
            INNER JOIN test t ON s.id = t.suite_id
            WHERE 1=1
            AND e.version = ?
            AND e.start_date BETWEEN ? AND ?
            ";
        if (isset($criteria['campaign']) && $criteria['campaign'] != '') {
            $req .= "
               AND s.campaign = ?
            ";
        } else {
            unset($criteria['campaign']);
        }
        $req .= "
            GROUP BY e.id, e.start_date, e.end_date, e.skipped, e.passes, e.failures, e.pending";

        return $this->db->query($req, $criteria);
    }

    /**
     * Get precise stats from an execution (to display in graphs)
     * @param $criteria
     * @return mixed
     *
     */
    function getPreciseStats($criteria)
    {
        $req = "SELECT e.id, e.ref, e.start_date, DATE(e.start_date) custom_start_date,e.end_date, e.failures,
            SUM(IF(t.error_message LIKE BINARY 'AssertionError: Expected File%', 1, 0)) file_not_found,
            SUM(IF(t.error_message LIKE 'AssertionError:%' AND t.error_message NOT LIKE 'AssertionError: Expected File%', 1, 0)) value_expected,
            SUM(IF((t.error_message REGEXP 'element(.*) still not existing' OR t.error_message REGEXP 'TimeoutError:*'), 1, 0)) not_visible_after_timeout,
            SUM(IF((t.error_message LIKE '%An element could not%' OR t.error_message LIKE 'Error: No node found for selector*'), 1, 0)) wrong_locator
        FROM $this->table e
        INNER JOIN suite s ON s.execution_id = e.id
        INNER JOIN test t ON t.suite_id = s.id
        WHERE 1=1
        AND e.version = ?
        AND e.start_date BETWEEN ? AND ?
        ";
        if (isset($criteria['campaign']) && $criteria['campaign'] != '') {
            $req .= "
           AND s.campaign = ?
        ";
        } else {
            unset($criteria['campaign']);
        }
        $req .= "
        GROUP BY e.id, e.ref, e.start_date,e.end_date";

        return $this->db->query($req, $criteria);
    }

    /**
     * Get precise stats from an execution
     * @param $execution_id
     * @return mixed
     */
    function getExecutionPreciseStats($execution_id)
    {
        $req = "SELECT e.id, e.ref, e.start_date, DATE(e.start_date) custom_start_date,e.end_date, e.failures,
            SUM(IF(t.error_message LIKE BINARY 'AssertionError: Expected File%', 1, 0)) file_not_found,
            SUM(IF(t.error_message LIKE 'AssertionError:%' AND t.error_message NOT LIKE 'AssertionError: Expected File%', 1, 0)) value_expected,
            SUM(IF((t.error_message REGEXP 'element(.*) still not existing' OR t.error_message REGEXP 'TimeoutError:*'), 1, 0)) not_visible_after_timeout,
            SUM(IF((t.error_message LIKE '%An element could not%' OR t.error_message LIKE 'Error: No node found for selector*'), 1, 0)) wrong_locator
        FROM execution e
        INNER JOIN suite s ON s.execution_id = e.id
        INNER JOIN test t ON t.suite_id = s.id
        WHERE 1=1
        AND execution_id = ?
        GROUP BY e.id";

        return $this->db->query($req, [$execution_id])->row();
    }

    /**
     * Get all informations from an execution
     * @param $execution_id
     * @return mixed
     */
    function getSummaryData($execution_id)
    {
        $req = "SELECT
            COUNT(DISTINCT(s.id)) suites,
            COUNT(t.id) tests,
            SUM(IF(t.state='passed', 1, 0)) passed,
            SUM(IF(t.state='failed', 1, 0)) failed,
            SUM(IF(t.state='skipped', 1, 0)) skipped,
            SUM(IF(t.state='pending', 1, 0)) pending
            
            FROM `execution` e
            INNER JOIN `suite` s on s.execution_id = e.id
            INNER JOIN `test` t on t.suite_id = s.id
            WHERE e.id = ?;";

        return $this->db->query($req, [$execution_id])->row();
    }

    /**
     * Find similar entries in db
     * @param $date
     * @param $version
     * @return mixed
     */
    function findSimilarEntries($date, $version)
    {
        $req = "SELECT id            
            FROM `execution` e
            WHERE version = ?
            AND DATE(start_date) = ?
            ;";

        return $this->db->query($req, [$version, $date])->row();
    }
}
