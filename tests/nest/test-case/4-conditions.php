<?php

use \SleekDB\SleekDB;

$test = [
  'title'   => 'Insert multiple items',
  'result'  => true,
  'message' => ''
];

try {
  $database = SleekDB::store('conditions_users', $this->testStore, [
    'auto_cache' => true,
    'timeout' => 120
  ]);
  $database->insertMany(getData("users"));
  $test['result'] = true;
} catch (Exception $e) {
  $test['result'] = false;
  $test['message'] = $e->getMessage();
}
