<?php

class Execution extends CI_Model
{

    private $table = 'execution';

    public $id;
    public $ref;
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

    function find($id)
    {
        return $this->db->query("SELECT * FROM $this->table WHERE id = ?;", [$id])->row();
    }


    function getAllInformation()
    {
        $sql = "SELECT id, ref, start_date, end_date, duration, version, suites, tests, skipped, passes, failures FROM $this->table ORDER BY DATE(start_date) DESC LIMIT 50";
        return $this->db->query($sql);
    }

    function getVersions() {
        return $this->db->query("SELECT 
                version, count(id) cpt
                FROM $this->table
                GROUP BY version
                ORDER BY cpt DESC;");
    }

    function getCustomData($criteria) {
        $req = "SELECT e.id, e.ref, e.start_date, DATE(e.start_date) custom_start_date,e.end_date, e.skipped, e.passes, e.failures,
            SUM(IF(t.state = 'skipped', 1, 0)) totalSkipped, SUM(IF(t.state = 'passed', 1, 0)) totalPasses, SUM(IF(t.state = 'failed', 1, 0)) totalFailures
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
            GROUP BY e.id, e.start_date, e.end_date, e.skipped, e.passes, e.failures";
        return $this->db->query($req, $criteria);
    }

    function getPreciseStats($criteria)
    {
        $req = "SELECT e.id, e.ref, e.start_date, DATE(e.start_date) custom_start_date,e.end_date, e.failures,
            SUM(IF(t.error_message LIKE BINARY 'AssertionError: Expected File%', 1, 0)) file_not_found,
            SUM(IF(t.error_message LIKE 'AssertionError:%' AND t.error_message NOT LIKE 'AssertionError: Expected File%', 1, 0)) value_expected,
            SUM(IF(t.error_message REGEXP 'element(.*) still not existing', 1, 0)) not_visible_after_timeout,
            SUM(IF(t.error_message LIKE '%An element could not%', 1, 0)) wrong_locator,
            SUM(IF(t.error_message LIKE '%invalid session id%', 1, 0)) invalid_session_id,
            SUM(IF(t.error_message LIKE '%chrome not reachable%', 1, 0)) chrome_not_reachable
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

    function getExecutionPreciseStats($execution_id)
    {
        $req = "SELECT e.id,
            SUM(IF(t.error_message LIKE 'AssertionError: Expected File%', 1, 0)) file_not_found, e.failures,
            SUM(IF(t.error_message LIKE 'AssertionError:%' AND t.error_message NOT LIKE 'AssertionError: Expected File%', 1, 0)) value_expected,
            SUM(IF(t.error_message REGEXP 'element(.*) still not existing', 1, 0)) not_visible_after_timeout,
            SUM(IF(t.error_message LIKE '%An element could not%', 1, 0)) wrong_locator,
            SUM(IF(t.error_message LIKE '%invalid session id%', 1, 0)) invalid_session_id,
            SUM(IF(t.error_message LIKE '%chrome not reachable%', 1, 0)) chrome_not_reachable
        FROM execution e
        INNER JOIN suite s ON s.execution_id = e.id
        INNER JOIN test t ON t.suite_id = s.id
        WHERE 1=1
        AND execution_id = ?
        GROUP BY e.id";
        return $this->db->query($req, [$execution_id])->row();
    }
}