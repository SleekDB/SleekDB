<?php

$result = true;
$message = '';

try {
  $seed = [
    ["name" => "Foo"],
    [
      "name" => "Bar",
      "cool" => [
        "val" => true
      ]
    ],
    [
      "name" => "Lorem",
      "cool" => [
        "val" => true
      ]
    ],
    [
      "name" => "Ipsum",
      "cool" => false
    ],
    [
      "name" => "Foo",
      "cool" => true
    ],
  ];

  // Insert required data.
  getSleekDB('distinctdata')->insertMany($seed);

  $cases = [
    [
      'name' => "Keep only distincted values",
      'query' => getSleekDB('distinctdata')
        ->distinct('name')
        ->distinct('cool.val')
        ->distinct(['test1', 'test2.cool']),
      'rows_count' => 3,
      'validator' => function ($data) {
        return equalItems([
          ["name" => "Foo"],
          [
            "name" => "Bar",
            "cool" => [
              "val" => true
            ]
          ],
          [
            "name" => "Ipsum",
            "cool" => false
          ]
        ], $data);
      }
    ]
  ];

} catch (Exception $e) {
  $result = false;
  $message = $e->getMessage();
}

$test = [
  'title'   => 'Return distinct values',
  'result'  => $result,
  'message' => $message
];
