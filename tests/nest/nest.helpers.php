<?php

use \SleekDB\SleekDB;

function equalItems($arrayWithoutId, $arrayWithId, $removeId = true)
{
  $dataMatched = false;
  foreach ($arrayWithId as $key => $value) {
    if ($removeId) {
      unset($value['_id']);
    }
    foreach ($arrayWithoutId as $key => $singleArrayWithoutId) {
      if ($value === $singleArrayWithoutId) {
        $dataMatched = true;
        break;
      }
    }
  }
  if (!$dataMatched) {
    return [
      'result' => false,
      'message' => 'Data mismatched'
    ];
  }

  return true;
}

function idExistsInEveryItem($items)
{
  $test = [
    'result' => true,
    'message' => ''
  ];
  foreach ($items as $key => $value) {
    if (!isset($value['_id']) || gettype($value['_id']) !== 'integer') {
      $test['result'] = false;
      $test['message'] = "Can not find _id property in an item fetched after multiple insert";
      break;
    }
  }
  return $test;
}

function getSleekDB($store, $autoCache = false)
{
  return SleekDB::store($store, __DIR__ . "/nest-test-db-store/", [
    'auto_cache' => $autoCache
  ]);
}

function caseRunner($cases)
{
  $message = '';
  $result = true;
  foreach ($cases as $key => $case) {
    $data = ($case['query'])->fetch();

    // Check rows count
    if (isset($case['rows_count'])) {
      if (count($data) !== $case['rows_count']) {
        $message = $case['name'] . "\nExpected row count is " . $case['rows_count'] . " but received " . count($data);
        $result = false;
        break;
      }
    }

    // Check validator
    if (isset($case['validator'])) {
      $validator = ($case['validator'])($data);
      if ($validator !== true) {
        $message = $case['name'] . "\n" . $validator;
        $result = false;
        break;
      }
    }
  }
  return [
    'message' => $message,
    'result' => $result
  ];
}
