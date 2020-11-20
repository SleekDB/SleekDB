<?php

$test = [
  'title'   => 'Insert multiple items',
  'result'  => true,
  'message' => ''
];

try {

  $sampleData = getData('users');
  $data = $database->insertMany($sampleData);

  // Validate items count.
  if (count($sampleData) !== count($data)) {
    $test['result'] = false;
    $test['message'] = "Different items has returned on multiple insert";
  } else {
    $validId = idExistsInEveryItem($data);
    if ($validId['result'] !== true) {
      $test['result'] = $validId['result'];
      $test['message'] = $validId['message'];
    }
    $isResultEqual = equalItems($sampleData, $data);
    if ($isResultEqual !== true) {
      $test['result'] = $isResultEqual['result'];
      $test['message'] = $isResultEqual['message'];
    }
  }

  // if (!$test['result']) {
  //   $test['expected'] = $sampleData;
  //   $test['found'] = $data;
  // }

} catch (Exception $e) {
  $test['result'] = false;
  $test['message'] = $e->getMessage();
}
