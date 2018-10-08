<?php

  $test = [
    'title'   => 'Inserting a single data',
    'result'  => false,
    'message' => ''
  ];

  try {

    $data = $database->store( "mysite" )->insert([
      'title' => 'Lorem ipsum dolor sit amet',
      'count' => 2,
      'price' => 43.23,
      'rate' => 'BDT'
    ]);

    // if(!isset($data))
    print_r($data);
    exit();
    
    $test['result'] = true;
  } catch( Exception $e ) {
    $test['result'] = false;
    $test['message'] = $e->getMessage();
  }
