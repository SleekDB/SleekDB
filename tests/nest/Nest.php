<?php

/**
 * Nest is a simple testing utility for SleekDB
 * SleekDB is a open-source NoSQL document database for PHP
 * @url https://sleekdb.github.io/
 * @author RakibTG <rakibtg@gmail.com>
 * Copyright - SleekDB
 */
require_once __DIR__ . '/nest.helpers.php';
require_once __DIR__ . '/nest.utils.php';
require_once __DIR__ . '/nest.data.php';
require_once __DIR__ . '/Console.php';

class Nest
{

  use NestUtils;

  function __construct($root)
  {
    $this->root = $root . '/';
    $this->testCase = $this->root . 'nest/test-case/';
    $this->dbStorage = $this->root . 'nest/nest-test-db-store/';
    $this->testStore = $this->dbStorage;
  }

  function getAllTestCases()
  {
    return array_diff(
      scandir($this->testCase, SCANDIR_SORT_ASCENDING),
      array('..', '.')
    );
  }

  function translateFileNameToFunctionName($fileName)
  {
    return trim(str_replace('-', '_', pathinfo($fileName)['filename']));
  }

  function emptyTestStore()
  {
    $it = new RecursiveDirectoryIterator($this->testStore, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      if ($file->isDir()) {
        rmdir($file->getRealPath());
      } else {
        unlink($file->getRealPath());
      }
    }
    rmdir($this->testStore);
  }

  public function runTest()
  {

    // Import SleekDB.
    require_once __DIR__ . '/../../src/SleekDB.php';

    // Instantiate the object.
    $database = null;

    $total = [
      'success' => 0,
      'failed' => 0,
      'tests' => 0
    ];

    // Greeting
    echo Console::yellow("SleekDB Test Runner\n");

    // Empty the test store.
    if (file_exists($this->testStore)) $this->emptyTestStore();
    foreach ($this->getAllTestCases() as $key => $testCase) {
      $total['tests'] = $total['tests'] + 1;
      require_once $this->testCase . $testCase;
      if (isset($cases)) {
        $caseRunnerOutput = caseRunner($cases);
        if (!$caseRunnerOutput['result']) {
          $test['result'] = $caseRunnerOutput['result'];
          $test['message'] = $caseRunnerOutput['message'];
        }
      }
      echo ($test['result'] ? Console::green('✔ ') : Console::red('✘ ')) . Console::blue($test['title']) . "\n";
      if ($test['result'] === true) {
        $total['success'] = $total['success'] + 1;
      } else {
        $total['failed'] = $total['failed'] + 1;
        echo Console::light_purple("Reason: ");
        echo Console::log($test['message']);
        echo Console::light_purple("Case File: ");
        Console::log(basename($this->testCase . $testCase));
        if (isset($test['expected'])) {
          echo Console::bold("Expected:") . "\n";
          print_r($test['expected']);
        }
        if (isset($test['found'])) {
          echo Console::bold("Found:") . "\n";
          print_r($test['found']);
        }
      }
    }
    echo "\n_____________________\n";
    echo Console::bold("Total tests\t: " . $total['tests']) . "\n";
    if ($total['success'] > 0) {
      echo Console::green("✔ Passed\t: " . $total['success']) . "\n";
    }
    if ($total['failed'] > 0) {
      echo Console::red("✘ Failed\t: " . $total['failed']) . "\n";
    }

    $this->emptyTestStore();
  }
}
