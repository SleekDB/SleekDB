<?php

  $test = [
    'title'   => 'Inserting a single data',
    'result'  => false,
    'message' => ''
  ];

  try {

    $sampleData = [
      'title' => 'Lorem ipsum dolor sit amet',
      'count' => 2,
      'price' => 43.23,
      'rate' => 'BDT'
    ];

    $data = $database->insert($sampleData);

    // Add the _id propert with provided data.
    $sampleData['_id'] = 1;
    $test['result'] = !!($data == $sampleData);

    if (!$test['result']) {
      $test['expected'] = $sampleData;
      $test['found'] = $data;
    }

  } catch( Exception $e ) {
    $test['result'] = false;
    $test['message'] = $e->getMessage();
  }
