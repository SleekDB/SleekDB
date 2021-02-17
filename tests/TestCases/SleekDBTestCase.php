<?php

namespace SleekDB\Tests\TestCases;

use SleekDB\Store;

class SleekDBTestCase extends SleekDBTestCasePlain
{

  /**
   * @var Store[]
   */
  protected $stores = [];

  /**
   * @before
   */
  public function createStore(){
    foreach (self::DATABASE_DATA as $storeName => $databaseData){
      $this->stores[$storeName] = new Store($storeName, self::DATA_DIR);
    }
  }


  /**
   * @after
   */
  public function deleteStore(){
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