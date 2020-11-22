<?php

use SleekDB\SleekDB;

$result = true;
$message = '';

try {
  // Insert required data.
  getSleekDB('authors')->insertMany(getData("authors"));
  getSleekDB('posts')->insertMany(getData("posts"));
  getSleekDB('authorBio')->insertMany(getData("authorBio"));

  $cases = [
    [
      'name' => "Join article with author",
      'query' => getSleekDB('authors')
        ->join(function ($author) {
          return SleekDB::store("posts")->where('username', '=', $author['username']);
        })
        ->join(function ($author) {
          return SleekDB::store("authorBio")->where("username", '=', $author['username']);
        }),
      'rows_count' => 10
    ],
    [
      'name' => "Join has yield proper data as child items",
      'query' => getSleekDB('authors')
        ->where('name', '=', 'Smitty')
        ->where('username', '=', 'strow7')
        ->join(function ($author) {
          return SleekDB::store("posts")->where('username', '=', $author['username']);
        })
        ->join(function ($author) {
          return SleekDB::store("authorBio")->where("username", '=', $author['username']);
        }, 'bio'),
      'rows_count' => 1,
      'validator' => function ($data) {
        if (count($data[0]['posts']) !== 2) {
          return "Total joined posts should be 2 item";
        }
        if (count($data[0]['bio']) !== 1) {
          return "Total joined bio should be 1 item";
        }
        return true;
      }
    ],
    [
      'name' => "Join has yield empty data as child items when no joinable document exists",
      'query' => getSleekDB('authors')
        ->where('name', '=', 'Hynda')
        ->where('username', '=', 'hbenian8')
        ->join(function ($author) {
          return SleekDB::store("posts")->where('username', '=', $author['username']);
        })
        ->join(function ($author) {
          return SleekDB::store("authorBio")->where("username", '=', $author['username']);
        }, 'bio'),
      'rows_count' => 1,
      'validator' => function ($data) {
        if (count($data[0]['posts']) !== 0) {
          return "Total joined posts should be 0 item";
        }
        if (count($data[0]['bio']) !== 0) {
          return "Total joined bio should be 0 item";
        }
        return true;
      }
    ]
  ];
} catch (Exception $e) {
  $result = false;
  $message = $e->getMessage();
}

$test = [
  'title'   => 'JOIN documents with parent documents',
  'result'  => $result,
  'message' => $message
];
