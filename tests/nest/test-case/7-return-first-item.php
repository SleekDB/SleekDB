<?php

$result = true;
$message = '';

try {
  getSleekDB('returnfirst')->insertMany(getData("authorBio"));

  $cases = [
    [
      'name' => "Should return a single document object",
      'query' => getSleekDB('returnfirst')
        ->where('username', '=', 'cthompstone1')
        ->first(),
      'validator' => function ($data) {
        $equal = $data === [
          "username" => "cthompstone1",
          "bio" => "bio test 2",
          "email" => "email2@test.com",
          "_id" => 2
        ];
        return $equal
          ? true
          : "No document data was returned, but it should return an data object";
      }
    ],
    [
      'name' => "Should return an empty object",
      'query' => getSleekDB('returnfirst')
        ->where('username', '=', 'dream_maker')
        ->first(),
      'validator' => function ($data) {
        return empty($data)
          ? true
          : "It seems like the document was found, although it should be empty";
      }
    ]
  ];
} catch (Exception $e) {
  $result = false;
  $message = $e->getMessage();
}

$test = [
  'title'   => 'Return only the first item',
  'result'  => $result,
  'message' => $message
];
