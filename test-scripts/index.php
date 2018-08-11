<?php

  require_once '../src/SleekDB.php';

  /*
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
    print_r($results);
    $res = $sdb->fetch();
    print_r($res);
  */

  // Create a new instance with required information's.
  $database = new \SleekDB\SleekDB( [
    'data_directory' => '.',
    'auto_cache' => true,
    'timeout' => 120
  ] );

  // Connect to a store. EX: store === SQL Table
  $users = $database->store( 'users' );
  $posts = $database->store( 'posts' );
  $comments = $database->store( 'comments' );

  // Insert a new user.
  $newUser = $users->insert( [ 
    'name' => 'rakibtg', 
    'email' => 'someone@test.com' 
  ] )->exec();

  // Insert a new post.
  $newPost = $posts->insert( [
      'title' => 'Test post',
      'author' => $users->_id
    ] )
  ->exec();

  // Insert a new comment.
  $newComment = $comments->insert()->exec();

  // Get the post.
  $newPost = $posts->join([ 'users', 'comments' ])->fetch();