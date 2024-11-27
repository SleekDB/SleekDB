<?php

namespace SleekDB\Tests\TestCases;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use SleekDB\Store;

class SleekDBTestCase extends SleekDBTestCasePlain
{

  /**
   * @var Store[]
   */
  protected $stores = [];

  #[Before] public function createStore(): void
  {
    foreach (self::DATABASE_DATA as $storeName => $databaseData){
      $this->stores[$storeName] = new Store($storeName, self::DATA_DIR);
    }
  }


  #[After] public function deleteStore(): void
  {
    foreach ($this->stores as $store){
      $store->deleteStore();
    }
  }

//  /**
//   * @after
//   */
//  public function clearStores(){
//    foreach (self::$stores as $store){
//      $store->delete();
//      $storeData = $store->fetch();
//      $this->assertEmpty($storeData);
//    }
//  }
}