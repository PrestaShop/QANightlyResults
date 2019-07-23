<?php

class Suite extends CI_Model
{

    private $table = 'suite';

    public $execution_id;
    public $id;
    public $uuid;
    public $title;
    public $filename;
    public $campaign;
    public $suites;
    public $tests;
    public $file;
    public $duration;
    public $hasSkipped;
    public $hasPasses;
    public $hasFailures;
    public $totalSkipped;
    public $totalPasses;
    public $totalFailures;
    public $hasSuites;
    public $hasTests;
    public $parent_id = null;


    function getCampaigns() {
        return $this->db->query("SELECT DISTINCT(campaign) FROM $this->table WHERE campaign IS NOT NULL AND campaign != '' ORDER BY campaign;");
    }

    public function insert($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    function getAllSuitesByFile($execution_id, $campaign, $file)
    {
        return $this->db->query("SELECT * FROM suite WHERE campaign=? AND file=? AND execution_id=? ORDER BY id", [$campaign, $file, $execution_id]);
    }

    function getAllCampaignsAndFilesByExecutionId($execution_id)
    {
        return $this->db->query(" 
            SELECT
                s.campaign, 
                SUM(IF(t.state = 'skipped', 1, 0)) hasSkipped, 
                SUM(IF(t.state = 'failed', 1, 0)) hasFailed, 
                SUM(IF(t.state = 'passed', 1, 0)) hasPassed, 
                file 
            FROM suite s
            INNER JOIN test t ON t.suite_id = s.id
            WHERE s.execution_id = ?
            AND s.campaign IS NOT NULL 
            GROUP BY s.campaign, s.file 
            ORDER BY s.campaign, s.file", [$execution_id]);
    }

}