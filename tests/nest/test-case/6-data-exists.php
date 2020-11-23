<?php

$result = true;
$message = '';

try {
  getSleekDB('dataexists')->insertMany(getData("authorBio"));

  $cases = [
    [
      'name' => "Document should exist",
      'query' => getSleekDB('dataexists')
        ->where('username', '=', 'cthompstone1')
        ->exists(),
      'validator' => function ($data) {
        return $data === true
          ? true
          : "It seems like the document was not found, although it should exist";
      }
    ],
    [
      'name' => "Document does not exist",
      'query' => getSleekDB('dataexists')
        ->where('username', '=', 'dream_maker')
        ->exists(),
      'validator' => function ($data) {
        return $data === false
          ? true
          : "It seems like the document was found, although it should not exist";
      }
    ]
  ];
} catch (Exception $e) {
  $result = false;
  $message = $e->getMessage();
}

$test = [
  'title'   => 'Check if data exists or not',
  'result'  => $result,
  'message' => $message
];
