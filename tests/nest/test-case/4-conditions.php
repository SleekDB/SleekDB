<?php

$result = true;
$message = '';

try {
  // Insert required data.
  getSleekDB('where_users')->insertMany(getData("users"));

  $cases = [
    [
      'name' => "Find document by it's _id, when id is 1",
      'query' => getSleekDB('where_users')->where('_id', '=', 1),
      'rows_count' => 1,
      'validator' => function ($data) {
        return $data[0]['_id'] === 1
          ? true
          : "Expected _id is 1 but received " . $data[0]['_id'];
      }
    ],
    [
      'name' => "Find document by it's _id, when id is 2",
      'query' => getSleekDB('where_users')->where('_id', '=', 2),
      'rows_count' => 1,
      'validator' => function ($data) {
        return $data[0]['_id'] === 2
          ? true
          : "Expected _id is 2 but received " . $data[0]['_id'];
      }
    ],
    [
      "name" => "Find document with multiple where conditions",
      "query" => getSleekDB('where_users')
        ->where('_id', '=', 1)
        ->where("name", "=", "Tundra"),
      "rows_count" => 1
    ],
    [
      "name" => "Find document with orWhere condition",
      "query" => getSleekDB('where_users')
        ->where('_id', '=', 1)
        ->orWhere("name", "=", "SleekDB"),
      "rows_count" => 1
    ],
    [
      "name" => "Find document with multiple orWhere condition",
      "query" => getSleekDB('where_users')
        ->where('_id', '=', 1)
        ->orWhere("name", "=", "SleekDB")
        ->orWhere("name", "=", "Cougar"),
      "rows_count" => 2
    ],
    [
      "name" => "Find document with greater than condition",
      "query" => getSleekDB('where_users')->where('price', '>', 90),
      "rows_count" => 3
    ],
    [
      "name" => "Find document with less than condition",
      "query" => getSleekDB('where_users')->where('price', '<', 20),
      "rows_count" => 1
    ],
    [
      "name" => "Find document where country is Estonia and United States",
      "query" => getSleekDB('where_users')->where('price', '<', 20),
      "rows_count" => 1
    ],
  ];
} catch (Exception $e) {
  $result = false;
  $message = $e->getMessage();
}

$test = [
  'title'   => 'Where Conditions',
  'result'  => $result,
  'message' => $message
];
