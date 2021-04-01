<?php

namespace SleekDB\Tests\TestCases;

use PHPUnit\Framework\TestCase;

class SleekDBTestCasePlain extends TestCase
{

  const STORE_NAME = "Teststore";
  const DATA_DIR = "tests/stores";

  const DATABASE_DATA = [
    "users" => [
      [
        "name" => "Tundra",
        "price" => 31,
        "currency" => "UZS",
        "country" => "Uzbekistan",
        "likes" => 8134
      ], [
        "name" => "LR3",
        "price" => 28,
        "currency" => "EUR",
        "country" => "Estonia",
        "likes" => 94254
      ], [
        "name" => "Cougar",
        "price" => 80,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 26521
      ], [
        "name" => "Tahoe",
        "price" => 45,
        "currency" => "HNL",
        "country" => "Honduras",
        "likes" => 99667
      ], [
        "name" => "Mustang",
        "price" => 91,
        "currency" => "IDR",
        "country" => "Indonesia",
        "likes" => 74375
      ], [
        "name" => "Imperial",
        "price" => 35,
        "currency" => "EUR",
        "country" => "Portugal",
        "likes" => 49413
      ], [
        "name" => "Topaz",
        "price" => 92,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 81690
      ], [
        "name" => "Camry",
        "price" => 90,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 41407
      ], [
        "name" => "Escalade EXT",
        "price" => 46,
        "currency" => "MNT",
        "country" => "Mongolia",
        "likes" => 63343
      ], [
        "name" => "Sonoma",
        "price" => 25,
        "currency" => "USD",
        "country" => "United States",
        "likes" => 66508
      ], [
        "name" => "NSX",
        "price" => 33,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 37646
      ], [
        "name" => "Golf",
        "price" => 27,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 1125
      ], [
        "name" => "928",
        "price" => 93,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 98920
      ], [
        "name" => "Ranger",
        "price" => 79,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 16335
      ], [
        "name" => "Town Car",
        "price" => 7,
        "currency" => "CNY",
        "country" => "China",
        "likes" => 77307
      ], [
        "name" => "3500",
        "price" => 43,
        "currency" => "BND",
        "country" => "Brunei",
        "likes" => 20582
      ], [
        "name" => "1500",
        "price" => 54,
        "currency" => "EUR",
        "country" => "Portugal",
        "likes" => 56481
      ], [
        "name" => "Beretta",
        "price" => 72,
        "currency" => "UZS",
        "country" => "Uzbekistan",
        "likes" => 53484
      ], [
        "name" => "Rally Wagon 3500",
        "price" => 60,
        "currency" => "ALL",
        "country" => "Albania",
        "likes" => 36971
      ], [
        "name" => "Silverado 1500",
        "price" => 89,
        "currency" => "EUR",
        "country" => "Portugal",
        "likes" => 60899
      ]
    ],
    "authors" => [
      [
        "name" => "Luke",
        "username" => "lkeeves0",
        "gender" => "Male"
      ], [
        "name" => "Charlie",
        "username" => "cthompstone1",
        "gender" => "Male"
      ], [
        "name" => "Cori",
        "username" => "cpetschelt2",
        "gender" => "Male"
      ], [
        "name" => "Noak",
        "username" => "noliffe3",
        "gender" => "Male"
      ], [
        "name" => "Maegan",
        "username" => "mjulyan4",
        "gender" => "Female"
      ], [
        "name" => "Dulcea",
        "username" => "dsola5",
        "gender" => "Female"
      ], [
        "name" => "Bellina",
        "username" => "bmatches6",
        "gender" => "Female"
      ], [
        "name" => "Smitty",
        "username" => "strow7",
        "gender" => "Male"
      ], [
        "name" => "Hynda",
        "username" => "hbenian8",
        "gender" => "Female"
      ], [
        "name" => "Fairfax",
        "username" => "fcready9",
        "gender" => "Male"
      ]
    ],
    "posts" => [
      [
        "title" => "sem sed",
        "username" => "lkeeves0",
        "likes" => 84,
        "comments" => 74
      ], [
        "title" => "quam pede lobortis",
        "username" => "cthompstone1",
        "likes" => 38,
        "comments" => 73
      ], [
        "title" => "ante ipsum primis",
        "username" => "cthompstone1",
        "likes" => 96,
        "comments" => 79
      ], [
        "title" => "lectus aliquam",
        "username" => "cthompstone1",
        "likes" => 20,
        "comments" => 64
      ], [
        "title" => "lacus at",
        "username" => "strow7",
        "likes" => 15,
        "comments" => 88
      ], [
        "title" => "lorem id",
        "username" => "strow7",
        "likes" => 39,
        "comments" => 77
      ], [
        "title" => "tristique fusce",
        "username" => "dsola5",
        "likes" => 57,
        "comments" => 35
      ], [
        "title" => "mattis egestas",
        "username" => "mjulyan4",
        "likes" => 8,
        "comments" => 89
      ], [
        "title" => "donec ut mauris",
        "username" => "cpetschelt2",
        "likes" => 52,
        "comments" => 85
      ], [
        "title" => "vestibulum proin eu",
        "username" => "fcready9",
        "likes" => 10,
        "comments" => 44
      ]
    ],
    "authorBio" => [
      [
        "username" => "lkeeves0",
        "bio" => "bio test 1",
        "email" => "email1@test.com"
      ],
      [
        "username" => "cthompstone1",
        "bio" => "bio test 2",
        "email" => "email2@test.com"
      ],
      [
        "username" => "strow7",
        "bio" => "bio test 3",
        "email" => "email3@test.com"
      ],
    ],
    "populationStatistics" => [
      [
        "country" => "China",
        "populationGrowth" => -100,
        "employmentPercentage" => 89.52,
      ],
      [
        "country" => "Indonesia",
        "populationGrowth" => -800,
        "employmentPercentage" => 74.25,
      ],
      [
        "country" => "Portugal",
        "populationGrowth" => -2,
        "employmentPercentage" => 49.49,
      ],
    ]
  ];
}