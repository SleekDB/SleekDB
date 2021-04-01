<?php declare(strict_types=1);

namespace SleekDB\Tests;

use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Tests\TestCases\SleekDBTestCase;

final class SelectTest extends SleekDBTestCase
{

  /**
   * @before
   */
  public function fillStores(){
    foreach ($this->stores as $storeName => $store){
      $store->insertMany(self::DATABASE_DATA[$storeName]);
    }
  }

  public function testCanGetResultWithSpecificFields(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->select(["_id", "price"])->where(["_id", "=", 1])->getQuery()->fetch();

    foreach ($users as $user){
      self::assertArrayHasKey("_id", $user);
      self::assertArrayHasKey("price", $user);
      self::assertArrayNotHasKey("name", $user);
    }
  }

  public function testConcatFunction(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $usersReference = $userQueryBuilder
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    $users = $userQueryBuilder
      ->select(["test" => ["CONCAT" => [" ", "name", "country", "likes"]]])
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    foreach ($users as $key => $user){
      $referenceUser = $usersReference[$key];
      $expectedString = $referenceUser["name"] . " " . $referenceUser["country"] . " " . $referenceUser["likes"];
      self::assertSame($expectedString, $user["test"]);
    }
  }

  public function testLengthFunction(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $usersReference = $userQueryBuilder
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    $users = $userQueryBuilder
      ->select(["test" => ["LENGTH" => "name"]])
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    foreach ($users as $key => $user){
      $referenceUser = $usersReference[$key];
      $expected = strlen($referenceUser["name"]);
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testLowerFunction(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $usersReference = $userQueryBuilder
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    $users = $userQueryBuilder
      ->select(["test" => ["LOWER" => "name"]])
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    foreach ($users as $key => $user){
      $referenceUser = $usersReference[$key];
      $expected = strtolower($referenceUser["name"]);
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testPositionFunction(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $usersReference = $userQueryBuilder
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    $users = $userQueryBuilder
      ->select(["test" => ["POSITION" => ["d", "name"]]])
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    foreach ($users as $key => $user){
      $referenceUser = $usersReference[$key];
      $expected = (strpos($referenceUser["name"], "d") + 1);
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testUpperFunction(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $usersReference = $userQueryBuilder
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    $users = $userQueryBuilder
      ->select(["test" => ["UPPER" => "name"]])
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    foreach ($users as $key => $user){
      $referenceUser = $usersReference[$key];
      $expected = strtoupper($referenceUser["name"]);
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testAbsFunction(){
    $populationStore = $this->stores["populationStatistics"];
    $populationQueryBuilder = $populationStore->createQueryBuilder();

    $reference = $populationQueryBuilder
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    $users = $populationQueryBuilder
      ->select(["test" => ["ABS" => "populationGrowth"]])
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    foreach ($users as $key => $user){
      $referenceDoc = $reference[$key];
      $expected = abs($referenceDoc["populationGrowth"]);
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testRoundFunction(){
    $populationStore = $this->stores["populationStatistics"];
    $populationQueryBuilder = $populationStore->createQueryBuilder();

    $reference = $populationQueryBuilder
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    $users = $populationQueryBuilder
      ->select(["test" => ["ROUND" => ["employmentPercentage", 1]]])
      ->where(["_id", "=", 1])
      ->getQuery()
      ->fetch();

    foreach ($users as $key => $user){
      $referenceDoc = $reference[$key];
      $expected = round($referenceDoc["employmentPercentage"], 1);
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testAvgFunction(){
    $store = $this->stores["users"];
    $queryBuilder = $store->createQueryBuilder();

    $reference = $queryBuilder
      ->getQuery()
      ->fetch();

    $users = $queryBuilder
      ->select(["test" => ["AVG" => "likes"]])
      ->getQuery()
      ->fetch();

    // calculate average
    $expected = 0;
    foreach ($reference as $referenceDoc){
      $expected += $referenceDoc["likes"];
    }
    $expected /= (count($reference) + 1);

    foreach ($users as $key => $user){
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testMaxFunction(){
    $store = $this->stores["users"];
    $queryBuilder = $store->createQueryBuilder();

    $reference = $queryBuilder
      ->getQuery()
      ->fetch();

    $users = $queryBuilder
      ->select(["test" => ["MAX" => "likes"]])
      ->getQuery()
      ->fetch();

    // calculate average
    $max = -INF;
    foreach ($reference as $referenceDoc){
      if($referenceDoc["likes"] > $max){
        $max = $referenceDoc["likes"];
      }
    }
    $expected = $max;

    foreach ($users as $key => $user){
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testMinFunction(){
    $store = $this->stores["users"];
    $queryBuilder = $store->createQueryBuilder();

    $reference = $queryBuilder
      ->getQuery()
      ->fetch();

    $users = $queryBuilder
      ->select(["test" => ["MIN" => "likes"]])
      ->getQuery()
      ->fetch();

    // calculate average
    $min = INF;
    foreach ($reference as $referenceDoc){
      if($referenceDoc["likes"] < $min){
        $min = $referenceDoc["likes"];
      }
    }
    $expected = $min;

    foreach ($users as $key => $user){
      self::assertSame($expected, $user["test"]);
    }
  }

  public function testSumFunction(){
    $store = $this->stores["users"];
    $queryBuilder = $store->createQueryBuilder();

    $reference = $queryBuilder
      ->getQuery()
      ->fetch();

    $users = $queryBuilder
      ->select(["test" => ["SUM" => "likes"]])
      ->getQuery()
      ->fetch();

    // calculate average
    $expected = 0;
    foreach ($reference as $referenceDoc){
      $expected += $referenceDoc["likes"];
    }

    foreach ($users as $key => $user){
      self::assertSame($expected, $user["test"]);
    }
  }

}