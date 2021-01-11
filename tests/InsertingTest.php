<?php declare(strict_types=1);

namespace SleekDB\Tests;

use SleekDB\Exceptions\InvalidDataException;
use SleekDB\Tests\TestCases\SleekDBTestCase;

final class InsertingTest extends SleekDBTestCase
{

  public function testCanInsertSingleData(){
    $userStore = $this->stores["users"];

    $usersData = self::DATABASE_DATA['users'][0];
    $userStore->insert($usersData);

    $users = $userStore->fetch();
    $this->assertCount(1, $users);
  }

  public function testCanInsertMultipleData(){
    $userStore = $this->stores["users"];

    $usersData = self::DATABASE_DATA['users'];
    $userStore->insertMany($usersData);

    $users = $userStore->fetch();
    $this->assertSameSize($usersData, $users);
  }

  public function testCannotInsertSingleEmptyData(){

    $userStore = $this->stores["users"];
    $this->expectException(InvalidDataException::class);
    $usersData = [];
    $userStore->insert($usersData);

  }

  public function testCannotInsertSingleStringData(){

    $userStore = $this->stores["users"];

    $this->expectException(\TypeError::class);
    $usersData = "This is a String";
    $userStore->insert($usersData);
  }

  public function testCannotInsertMultipleEmptyData(){

    $userStore = $this->stores["users"];
    $this->expectException(InvalidDataException::class);
    $usersData = [];
    $userStore->insertMany($usersData);

  }

  public function testCannotInsertMultipleStringData(){

    $userStore = $this->stores["users"];

    $this->expectException(\TypeError::class);
    $usersData = "This is a String";
    $userStore->insertMany($usersData);
  }

}