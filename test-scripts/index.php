<?php

  require_once '../src/SleekDB.php';

  $sdb = new \SleekDB\SleekDB( "items" );
  $items = [
    [
      "title" => "Google Pixel 2",
      "about" => "The unlocked Pixel 2 provides..."  
    ],
    [
      "title" => "Google Pixel XL",
      "about" => "The unlocked biggest Pixel 2..."  
    ]
  ];
  $results = $sdb->insertMany( $items ); 
  // print_r($results);
  $res = $sdb->fetch();
  print_r($res);