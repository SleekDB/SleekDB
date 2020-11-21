<?php

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
      'query' => getSleekDB('authors')->join(),
      'rows_count' => 1,
      'validator' => function ($data) {
        return $data[0]['_id'] === 1
          ? true
          : "Expected _id is 1 but received " . $data[0]['_id'];
      }
    ],
    [
      'name' => "Join article with author and country with author",
      'query' => getSleekDB('authors'),
      'rows_count' => 1,
      'validator' => function ($data) {
        return $data[0]['_id'] === 1
          ? true
          : "Expected _id is 1 but received " . $data[0]['_id'];
      }
    ]
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
