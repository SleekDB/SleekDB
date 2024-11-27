<?php declare(strict_types=1);

namespace SleekDB\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use SleekDB\Cache;
use SleekDB\Classes\CacheHandler;
use SleekDB\Classes\ConditionsHandler;
use SleekDB\Classes\DocumentFinder;
use SleekDB\Classes\DocumentReducer;
use SleekDB\Classes\DocumentUpdater;
use SleekDB\Classes\IoHelper;
use SleekDB\Classes\NestedHelper;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Query;
use SleekDB\QueryBuilder;
use SleekDB\Store;
use SleekDB\Tests\TestCases\SleekDBTestCase;
use TypeError;

#[CoversClass(Cache::class)]
#[CoversClass(CacheHandler::class)]
#[CoversClass(ConditionsHandler::class)]
#[CoversClass(DocumentFinder::class)]
#[CoversClass(DocumentReducer::class)]
#[CoversClass(DocumentUpdater::class)]
#[CoversClass(Query::class)]
#[CoversClass(QueryBuilder::class)]
#[CoversClass(Store::class)]
#[CoversClass(IoHelper::class)]
#[CoversClass(NestedHelper::class)]
final class InsertingTest extends SleekDBTestCase
{

  public function testCanInsertSingleData(){
    $userStore = $this->stores["users"];

    $usersData = self::DATABASE_DATA['users'][0];
    $userStore->insert($usersData);

    $users = $userStore->findAll();
    self::assertCount(1, $users);
  }

  public function testCanInsertMultipleData(){
    $userStore = $this->stores["users"];

    $usersData = self::DATABASE_DATA['users'];
    $users = $userStore->insertMany($usersData);
    $usersFetched = $userStore->findAll();
    self::assertSameSize($usersData, $users);
    self::assertSameSize($usersFetched, $users);
  }

  public function testCannotInsertSingleEmptyData(){

    $userStore = $this->stores["users"];
    $this->expectException(InvalidArgumentException::class);
    $usersData = [];
    $userStore->insert($usersData);

  }

  public function testCannotInsertSingleStringData(){

    $userStore = $this->stores["users"];

    $this->expectException(TypeError::class);
    $usersData = "This is a String";
    $userStore->insert($usersData);
  }

  public function testCannotInsertMultipleEmptyData(){

    $userStore = $this->stores["users"];
    $this->expectException(InvalidArgumentException::class);
    $usersData = [];
    $userStore->insertMany($usersData);

  }

  public function testCannotInsertMultipleStringData(){

    $userStore = $this->stores["users"];

    $this->expectException(TypeError::class);
    $usersData = "This is a String";
    $userStore->insertMany($usersData);
  }

  public function testCannotInsertDocumentWithPrimaryKey(){

    $userStore = $this->stores["users"];

    $this->expectException(IdNotAllowedException::class);
    $userStore->insert(["_id" => 3, "test" => "test"]);
  }


}