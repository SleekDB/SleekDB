<?php

  /**
   * Nest is a simple testing utility for SleekDB
   * SleekDB is a open-source NoSQL document database for PHP
   * @url https://sleekdb.github.io/
   * @author RakibTG <rakibtg - at - gmail>
   * Copyright - SleekDB
   */

  class Nest {

    function __construct($root) {
      $this->root = $root . '/';
      $this->testCase = $this->root . 'nest/test-case/';
      $this->dbStorage = $this->root . 'nest/test-db-storage/';
    }

    function getAllTestCases() {
      return array_diff(scandir($this->testCase), array('..', '.'));
    }

    function translateFileNameToFunctionName($fileName) {
      return trim(str_replace('-', '_', pathinfo($fileName)['filename']));
    }

    public function runTest() {
      foreach ($this->getAllTestCases() as $key => $testCase) {
        require_once $this->testCase . $testCase;
        echo $title . "\n";
        $this->translateFileNameToFunctionName($testCase)();
      }
    }

  }