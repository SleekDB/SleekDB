<?php declare(strict_types=1);

namespace SleekDB\Tests;

use SleekDB\Exceptions\InvalidArgumentException;
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
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->where(["_id", "=", 1])->getQuery()->fetch();

    self::assertCount(1, $users);
  }

  public function testCanGetResultWithOrWhere(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->where(["_id", "=", 1])->orWhere(["_id", "=", 2])->getQuery()->fetch();

    self::assertCount(2, $users);
  }

  public function testCanGetResultWithAndConditionBetweenOrWhere(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->where(["_id", "=", 1])->orWhere([["_id", "=", 2], ["_id", "=", 3]])->getQuery()->fetch();

    self::assertCount(1, $users);
  }

  public function testCanGetResultWithMultipleWhere(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->where(["_id", "=", 1])->where(["_id", "=", 2])->getQuery()->fetch();

    self::assertCount(0, $users);
  }

  public function testCanGetResultWithoutSomeFields(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->except(["_id", "name"])->where(["_id", "=", 1])->getQuery()->fetch();

    foreach ($users as $user){
      self::assertArrayNotHasKey("_id", $user);
      self::assertArrayNotHasKey("name", $user);
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

  public function testCanGetResultWithInCondition(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->where(["_id","in",[1,2]])->getQuery()->fetch();

    self::assertCount(2, $users);
  }

  public function testCanGetResultWithNotInCondition(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->where(["_id", "not in", [1,2]])->getQuery()->fetch();

    self::assertCount(count(self::DATABASE_DATA["users"]) - 2, $users);
  }

  public function testCanGetFirstResult(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $user1 = $userQueryBuilder->limit(1)->getQuery()->fetch();
    $user2 = $userQueryBuilder->getQuery()->first();

    self::assertSame($user1[0], $user2);
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
    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->limit(3)->getQuery()->fetch();

    self::assertCount(3, $users);
  }

  public function testCanOrderBy(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $orderByKey = "_id";

    $users = $userQueryBuilder->orderBy([$orderByKey => "asc"])->getQuery()->fetch();

    $usersLength = count($users);
    for($index = 1; $index < $usersLength; $index++){
      self::assertGreaterThan($users[$index - 1][$orderByKey], $users[$index][$orderByKey]);
    }

    $userQueryBuilder = $userStore->createQueryBuilder();

    $users = $userQueryBuilder->orderBy([$orderByKey => "DESC"])->getQuery()->fetch();

    $usersLength = count($users);
    for($index = 1; $index < $usersLength; $index++){
      self::assertLessThan($users[$index - 1][$orderByKey], $users[$index][$orderByKey]);
    }
  }

  public function testResultExists(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $userExists = $userQueryBuilder->where(["_id", "=", 1])->getQuery()->exists();

    self::assertTrue($userExists);

    $userQueryBuilder = $userStore->createQueryBuilder();

    $userExists = $userQueryBuilder->where(["_id", "=", 2])->where(["_id", "=", 1])->getQuery()->exists();

    self::assertFalse($userExists);
  }

  public function testCanUseCacheWithNoParameter(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $query = $userQueryBuilder->where(["_id", "=", 1])->useCache()->getQuery();

    $userCache = $query->getCache();

    $result = $query->fetch();

    $cacheResult = $userCache->get();

    self::assertSame($result, $cacheResult);
  }

  public function testCanUseCacheWithLifetimeNull(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $query = $userQueryBuilder->where(["_id", "=", 1])->useCache(null)->getQuery();

    $userCache = $query->getCache();

    $result = $query->fetch();

    $cacheResult = $userCache->get();

    self::assertSame($result, $cacheResult);
  }

  public function testCanUseCacheWithLifetimeZero(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $query = $userQueryBuilder->where(["_id", "=", 1])->useCache(0)->getQuery();

    $userCache = $query->getCache();

    $result = $query->fetch();

    $userCache->deleteAllWithNoLifetime();

    sleep(1);

    $cacheResult = $userCache->get();

    self::assertSame($result, $cacheResult);
  }

  public function testCanUseCacheWithLifetimeInt(){
    $userStore = $this->stores["users"];
    $userQueryBuilder = $userStore->createQueryBuilder();

    $query = $userQueryBuilder->where(["_id", "=", 1])->useCache(1)->getQuery();

    $userCache = $query->getCache();

    $result = $query->fetch();

    sleep(1);

    $cacheResult = $userCache->get();

    self::assertSame($result, $cacheResult);

    sleep(1);

    $cacheResult = $userCache->get();

    self::assertNotSame($result, $cacheResult);
  }
}