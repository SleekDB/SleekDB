<?php

  $test = [
    'title'   => 'Insert multiple items',
    'result'  => false,
    'message' => ''
  ];

  try {

    $sampleData = json_decode('[
      {
        "name": "Tundra",
        "price": 31,
        "currency": "UZS",
        "country": "Uzbekistan",
        "likes": 8134
      }, {
        "name": "LR3",
        "price": 28,
        "currency": "EUR",
        "country": "Estonia",
        "likes": 94254
      }, {
        "name": "Cougar",
        "price": 80,
        "currency": "CNY",
        "country": "China",
        "likes": 26521
      }, {
        "name": "Tahoe",
        "price": 45,
        "currency": "HNL",
        "country": "Honduras",
        "likes": 99667
      }, {
        "name": "Mustang",
        "price": 91,
        "currency": "IDR",
        "country": "Indonesia",
        "likes": 74375
      }, {
        "name": "Imperial",
        "price": 35,
        "currency": "EUR",
        "country": "Portugal",
        "likes": 49413
      }, {
        "name": "Topaz",
        "price": 92,
        "currency": "CNY",
        "country": "China",
        "likes": 81690
      }, {
        "name": "Camry",
        "price": 90,
        "currency": "CNY",
        "country": "China",
        "likes": 41407
      }, {
        "name": "Escalade EXT",
        "price": 46,
        "currency": "MNT",
        "country": "Mongolia",
        "likes": 63343
      }, {
        "name": "Sonoma",
        "price": 25,
        "currency": "USD",
        "country": "United States",
        "likes": 66508
      }, {
        "name": "NSX",
        "price": 33,
        "currency": "CNY",
        "country": "China",
        "likes": 37646
      }, {
        "name": "Golf",
        "price": 27,
        "currency": "CNY",
        "country": "China",
        "likes": 1125
      }, {
        "name": "928",
        "price": 93,
        "currency": "CNY",
        "country": "China",
        "likes": 98920
      }, {
        "name": "Ranger",
        "price": 79,
        "currency": "CNY",
        "country": "China",
        "likes": 16335
      }, {
        "name": "Town Car",
        "price": 7,
        "currency": "CNY",
        "country": "China",
        "likes": 77307
      }, {
        "name": "3500",
        "price": 43,
        "currency": "BND",
        "country": "Brunei",
        "likes": 20582
      }, {
        "name": "1500",
        "price": 54,
        "currency": "EUR",
        "country": "Portugal",
        "likes": 56481
      }, {
        "name": "Beretta",
        "price": 72,
        "currency": "UZS",
        "country": "Uzbekistan",
        "likes": 53484
      }, {
        "name": "Rally Wagon 3500",
        "price": 60,
        "currency": "ALL",
        "country": "Albania",
        "likes": 36971
      }, {
        "name": "Silverado 1500",
        "price": 89,
        "currency": "EUR",
        "country": "Portugal",
        "likes": 60899
      }
    ]', true);

    $data = $database->insertMany($sampleData);

    // Add the _id propert with provided data.
    $sampleData['_id'] = 1;
    $test['result'] = !!($data == $sampleData);

    if (!$test['result']) {
      $test['expected'] = $sampleData;
      $test['found'] = $data;
    }

  } catch( Exception $e ) {
    $test['result'] = false;
    $test['message'] = $e->getMessage();
  }
