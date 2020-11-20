<?php

use \SleekDB\SleekDB;

$test = [
  'title'   => 'Instaniate and connect to a store',
  'result'  => false,
  'message' => ''
];

try {
  $database = SleekDB::store('mysite', $this->testStore, [
    'auto_cache' => true,
    'timeout' => 120
  ]);
  $test['result'] = true;
} catch (Exception $e) {
  $test['result'] = false;
  $test['message'] = $e->getMessage();
}
