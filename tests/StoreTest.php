<?php declare(strict_types=1);

namespace SleekDB\Tests;

use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\SleekDB;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Tests\TestCases\SleekDBTestCasePlain;


final class StoreTest extends SleekDBTestCasePlain
{

  public function testCanCreateAndDeleteStore(){
    $store = SleekDB::store(self::STORE_NAME, self::DATA_DIR);
    $this->assertInstanceOf(SleekDB::class, $store);

    $storePath = self::DATA_DIR."/".self::STORE_NAME;

    $this->assertDirectoryExists($storePath);
    $this->assertDirectoryIsWritable($storePath);
    $this->assertDirectoryIsReadable($storePath);

    $cachePath = $storePath."/cache";
    $this->assertDirectoryExists($cachePath);
    $this->assertDirectoryIsWritable($cachePath);
    $this->assertDirectoryIsReadable($cachePath);

    $dataPath = $storePath."/data";
    $this->assertDirectoryExists($dataPath);
    $this->assertDirectoryIsWritable($cachePath);
    $this->assertDirectoryIsReadable($cachePath);

    $counterFile = $storePath."/_cnt.sdb";
    $this->assertFileExists($counterFile);
    $this->assertFileIsWritable($counterFile);
    $this->assertFileIsReadable($counterFile);

    $store->deleteStore();
    $this->assertDirectoryNotExists($storePath);
  }

  public function testCannotCreateStoreWithEmptyStoreName(){
    $this->expectException(InvalidArgumentException::class);
    SleekDB::store("", self::DATA_DIR);
  }

  public function testCannotCreateStoreWithOptionsNull(){
    $this->expectException(\TypeError::class);
    SleekDB::store(self::STORE_NAME, self::DATA_DIR, null);
  }

  public function testCannotCreateStoreWithOptionsString(){
    $this->expectException(\TypeError::class);
    SleekDB::store(self::STORE_NAME, self::DATA_DIR, "");
  }

  public function testCannotCreateStoreWithOptionsObject(){
    $this->expectException(\TypeError::class);
    SleekDB::store(self::STORE_NAME, self::DATA_DIR, (new \stdClass()));
  }

  public function testCannotCreateStoreWithOptionsInteger(){
    $this->expectException(\TypeError::class);
    SleekDB::store(self::STORE_NAME, self::DATA_DIR, 1);
  }
}