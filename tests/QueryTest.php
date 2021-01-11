<?php declare(strict_types=1);

namespace SleekDB\Tests;

use SleekDB\Exceptions\InvalidDataException;
use SleekDB\Tests\TestCases\SleekDBTestCase;

final class QueryTest extends SleekDBTestCase
{

  /**
   * @before
   */
  public function fillStores(){
    foreach ($this->stores as $storeName => $store){
      $store->insertMany(self::DATABASE_DATA[$storeName]);

    }
  }

  public function testCanGetResultWithWhere(){
    $userStore = $this->stores["users"];

    $users = $userStore->where("_id", "=", 1)->fetch();

    $this->assertCount(1, $users);
  }

  public function testCanGetResultWithOrWhere(){
    $userStore = $this->stores["users"];

    $users = $userStore->where("_id", "=", 1)->orWhere("_id", "=", 2)->fetch();

    $this->assertCount(2, $users);
  }

  public function testCanGetResultWithAndConditionBetweenOrWhere(){
    $userStore = $this->stores["users"];

    $users = $userStore->where("_id", "=", 1)->orWhere(["_id", "=", 2], ["_id", "=", 3])->fetch();

    $this->assertCount(1, $users);
  }

  public function testCanGetResultWithMultipleWhere(){
    $userStore = $this->stores["users"];

    $users = $userStore->where("_id", "=", 1)->where("_id", "=", 2)->fetch();

    $this->assertCount(0, $users);
  }

  public function testCanGetResultWithoutSomeFields(){
    $userStore = $this->stores["users"];

    $users = $userStore->except(["_id", "name"])->where("_id", "=", 1)->fetch();

    foreach ($users as $user){
      $this->assertArrayNotHasKey("_id", $user);
      $this->assertArrayNotHasKey("name", $user);
    }
  }

  public function testCanGetResultWithSpecificFields(){
    $userStore = $this->stores["users"];

    $users = $userStore->select(["_id", "price"])->where("_id", "=", 1)->fetch();

    foreach ($users as $user){
      $this->assertArrayHasKey("_id", $user);
      $this->assertArrayHasKey("price", $user);
      $this->assertArrayNotHasKey("name", $user);
    }
  }

  public function testCanGetResultWithInMethod(){
    $userStore = $this->stores["users"];

    $users = $userStore->in("_id",[1,2])->fetch();

    $this->assertCount(2, $users);
  }

  public function testCanGetResultWithNotInMethod(){
    $userStore = $this->stores["users"];

    $users = $userStore->notIn("_id",[1,2])->fetch();

    $this->assertCount(count(self::DATABASE_DATA["users"]) - 2, $users);
  }

  public function testCanGetFirstResult(){
    $userStore = $this->stores["users"];

    $user1 = $userStore->limit(1)->fetch();
    $user2 = $userStore->first();

    $this->assertSame($user1[0], $user2);
  }

//  public function testCanGetFirstResultAfterOrderBy(){
//    $userStore = $this->stores["users"];
//
//    $user1 = $userStore->orderBy("ASC")->limit(1)->fetch();
//    $user2 = $userStore->orderBy("ASC")->first()->fetch();
//
//    $this->assertSame($user1[0], $user2);
//  }

  public function testCanLimitResults(){
    $userStore = $this->stores["users"];

    $users = $userStore->limit(3)->fetch();

    $this->assertCount(3, $users);
  }

  public function testCanOrderBy(){
    $userStore = $this->stores["users"];

    $orderByKey = "_id";

    $users = $userStore->orderBy("ASC", $orderByKey)->fetch();

    $usersLength = count($users);
    for($index = 1; $index < $usersLength; $index++){
      $this->assertGreaterThan($users[$index - 1][$orderByKey], $users[$index][$orderByKey]);
    }

    $users = $userStore->orderBy("DESC", $orderByKey)->fetch();

    $usersLength = count($users);
    for($index = 1; $index < $usersLength; $index++){
      $this->assertLessThan($users[$index - 1][$orderByKey], $users[$index][$orderByKey]);
    }
  }

  public function testResultExists(){

    $userStore = $this->stores["users"];

    $userExists = $userStore->where("_id", "=", 1)->exists();

    $this->assertTrue($userExists);

    $userExists = $userStore->where("_id", "=", 2)->where("_id", "=", 1)->exists();

    $this->assertFalse($userExists);
  }

  public function testCanUseCacheWithNoParameter(){
      $userStore = $this->stores["users"];

      $query = $userStore->where("_id", "=", 1)->useCache()->getQuery();

      $userCache = $query->getCache();

      $result = $query->fetch();

      $cacheResult = $userCache->get();

      $this->assertSame($result, $cacheResult);
  }

    public function testCanUseCacheWithLifetimeNull(){
        $userStore = $this->stores["users"];

        $query = $userStore->where("_id", "=", 1)->useCache(null)->getQuery();

        $userCache = $query->getCache();

        $result = $query->fetch();

        $cacheResult = $userCache->get();

        $this->assertSame($result, $cacheResult);
    }

    public function testCanUseCacheWithLifetimeZero(){
        $userStore = $this->stores["users"];

        $query = $userStore->where("_id", "=", 1)->useCache(0)->getQuery();

        $userCache = $query->getCache();

        $result = $query->fetch();

        $userCache->deleteAllWithNoLifetime();

        sleep(1);

        $cacheResult = $userCache->get();

        $this->assertSame($result, $cacheResult);
    }

    public function testCanUseCacheWithLifetimeInt(){
        $userStore = $this->stores["users"];

        $query = $userStore->where("_id", "=", 1)->useCache(1)->getQuery();

        $userCache = $query->getCache();

        $result = $query->fetch();

        sleep(1);

        $cacheResult = $userCache->get();

        $this->assertSame($result, $cacheResult);

        sleep(1);

        $cacheResult = $userCache->get();

        $this->assertNotSame($result, $cacheResult);
    }


}