<?php

class Test extends CI_Model
{

    private $table = 'test';

    public $id;
    public $uuid;
    public $title;
    public $state;
    public $duration;
    public $err = [
        'message' => null,
        'diff' => null,
        'estack' => null
    ];
    public $suite_id = null;

    /**
     * Get test data from a suite
     * @param $suite_id
     * @return mixed
     */
    function getBySuiteId($suite_id) {
        return $this->db->query("SELECT * FROM $this->table WHERE suite_id = :suite_id ORDER BY id ASC", [':suite_id' => $suite_id]);
    }

    /**
     * Insert a row
     * @param $data
     * @return mixed
     */
    public function insert($data)
    {
        $this->db->insert($this->table, $data);

        return $this->db->insert_id();
    }

    /**
     * Get all test in a file (for the report)
     * @param $execution_id
     * @param $campaign
     * @param $file
     * @return mixed
     */
    function getAllTestsByFile($execution_id, $campaign, $file)
    {
        return $this->db->query("SELECT t.* FROM test t INNER JOIN suite s ON s.id=t.suite_id WHERE s.campaign=? AND s.file=? AND s.execution_id=?;", [$campaign, $file, $execution_id]);
    }

    /**
     * Get all tests in an execution
     * @param $execution_id
     * @return mixed
     */
    function getAllByExecutionId($execution_id) {
        return $this->db->query("SELECT t.*
        FROM $this->table t 
        INNER JOIN suite s ON t.suite_id = s.id
        WHERE s.execution_id = :execution_id
        ORDER BY t.id;", ['execution_id' => $execution_id]);
    }
}