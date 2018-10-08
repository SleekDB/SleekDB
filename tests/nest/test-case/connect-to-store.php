<?php

  $test = [
    'title'   => 'Connecting to the store "mysite"',
    'result'  => false,
    'message' => ''
  ];

  try {
    $database->store( "mysite" );
    $test['result'] = true;
  } catch( Exception $e ) {
    $test['result'] = false;
    $test['message'] = $e->getMessage();
  }
