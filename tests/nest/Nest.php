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
  class Nest {

    use NestUtils;

    function __construct($root) {
      $this->root = $root . '/';
      $this->testCase = $this->root . 'nest/test-case/';
      $this->dbStorage = $this->root . 'nest/nest-test-db-store/';
      $this->testStore = $this->dbStorage;
    }

    function getAllTestCases() {
      return array_diff(
        scandir($this->testCase, SCANDIR_SORT_ASCENDING), 
        array('..', '.')
      );
    }

    function translateFileNameToFunctionName($fileName) {
      return trim(str_replace('-', '_', pathinfo($fileName)['filename']));
    }

    function emptyTestStore() {
      $it = new RecursiveDirectoryIterator( $this->testStore, RecursiveDirectoryIterator::SKIP_DOTS);
      $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
      foreach($files as $file) {
        if ($file->isDir()){
          rmdir($file->getRealPath());
        } else {
          unlink($file->getRealPath());
        }
      }
      rmdir( $this->testStore );
    }

    public function runTest() {

      // Import SleekDB.
      require_once __DIR__ . '/../../src/SleekDB.php';

      // Instantiate the object.
      $database = null;

      $total = [
        'success' => 0,
        'failed' => 0,
        'tests' => 0
      ];

      // Empty the test store.
      if ( file_exists( $this->testStore ) ) $this->emptyTestStore();
      foreach ($this->getAllTestCases() as $key => $testCase) {
        $total[ 'tests' ] = $total[ 'tests' ] + 1;
        require_once $this->testCase . $testCase;
        $this->print_warning( $test['title'] );
        if( $test['result'] === true ) {
          $this->print_success( '✔ Test passed.' );
          $total[ 'success' ] = $total[ 'success' ] + 1;
        } else {
          $total[ 'failed' ] = $total[ 'failed' ] + 1;
          $this->print_danger( '✘ Test failed.' );
          $this->print_default( $test[ 'message' ] );
          if (isset($test['expected'])) {
            $this->print_default("Expected:");
            print_r($test['expected']);
          }
          if (isset($test['found'])) {
            $this->print_default("Found:");
            print_r($test['found']);
          }
        }
      }
      echo "~~~~~~~~~~~~~~~~~~~~\n";
      // Test stat
      $this->print_default( "↳ Total tests\t: " . $total[ 'tests' ] );
      if( $total[ 'success' ] > 0 ) $this->print_success( "✔ Total passed\t: " . $total[ 'success' ] );
      if( $total[ 'failed' ] > 0 ) $this->print_danger( "✘ Total failed:\t: " . $total[ 'failed' ] );

    }

  }