<?php

  namespace SleekDB;

  require_once __DIR__ . '/traits/helpers.php';
  require_once __DIR__ . '/traits/conditions.php';
  require_once __DIR__ . '/traits/caching.php';

  class SleekDB {

    use \HelpersTrait, \ConditionsTrait, \CacheTraits;

    // Initialize the database.
    function __construct( $dataDir = '', $configurations = false ) {
      // Define the root path of SleekDB.
      $this->root = __DIR__;
      // Add data dir.
      $configurations[ 'data_directory' ] = $dataDir;
      // Initialize SleekDB
      $this->init( $configurations );
    }

    // Initialize the store.
    public static function store( $storeName = false, $dataDir, $options = false ) {
      if ( !$storeName OR empty( $storeName ) ) throw new \Exception( 'Store name was not valid' );
      $_dbInstance = new \SleekDB\SleekDB( $dataDir, $options );
      $_dbInstance->storeName = $storeName;
      // Boot store.
      $_dbInstance->bootStore();
      // Initialize variables for the store.
      $_dbInstance->initVariables();
      return $_dbInstance;
    }

    // Read store objects.
    public function fetch() {
      $fetchedData = null;
      // Check if data should be provided from the cache.
      if ( $this->makeCache === true ) {
        $fetchedData = $this->reGenerateCache(); // Re-generate cache.
      }
      else if ( $this->useCache === true ) {
        $fetchedData = $this->useExistingCache(); // Use existing cache else re-generate.
      }
      else {
        $fetchedData = $this->findStoreDocuments(); // Returns data without looking for cached data.
      }
      $this->initVariables(); // Reset state.
      return $fetchedData;
    }

    // Creates a new object in the store.
    // The object is a plaintext JSON document.
    public function insert( $storeData = false ) {
      // Handle invalid data
      if ( ! $storeData OR empty( $storeData ) ) throw new \Exception( 'No data found to store' );
      // Make sure that the data is an array
      if ( ! is_array( $storeData ) ) throw new \Exception( 'Storable data must an array' );
      $storeData = $this->writeInStore( $storeData );
      // Check do we need to wipe the cache for this store.
      if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
      return $storeData;
    }

    // Creates multiple objects in the store.
    public function insertMany( $storeData = false ) {
      // Handle invalid data
      if ( ! $storeData OR empty( $storeData ) ) throw new \Exception( 'No data found to insert in the store' );
      // Make sure that the data is an array
      if ( ! is_array( $storeData ) ) throw new \Exception( 'Data must be an array in order to insert in the store' );
      // All results.
      $results = [];
      foreach ( $storeData as $key => $node ) {
        $results[] = $this->writeInStore( $node );
      }
      // Check do we need to wipe the cache for this store.
      if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
      return $results;
    }

    // Updates matched store objects.
    public function update( $updateable ) {
      // Find all store objects.
      $storeObjects = $this->findStoreDocuments();
      // If no store object found then return an empty array.
      if ( empty( $storeObjects ) ) {
        $this->initVariables(); // Reset state.
        return false;
      }
      foreach ( $storeObjects as $data ) {
        foreach ( $updateable as $key => $value ) {
          // Do not update the _id reserved index of a store.
          if( $key != '_id' ) {
            $data[ $key ] = $value;
          }
        }
        $storePath = $this->storePath . 'data/' . $data[ '_id' ] . '.json';
        if ( file_exists( $storePath ) ) {
          // Wait until it's unlocked, then update data.
          file_put_contents( $storePath, json_encode( $data ), LOCK_EX );
        }
      }
      // Check do we need to wipe the cache for this store.
      if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
      $this->initVariables(); // Reset state.
      return true;
    }

    // Deletes matched store objects.
    public function delete() {
      // Find all store objects.
      $storeObjects = $this->findStoreDocuments();
      if ( ! empty( $storeObjects ) ) {
        foreach ( $storeObjects as $data ) {
          if ( ! unlink( $this->storePath . 'data/' . $data[ '_id' ] . '.json' ) ) {
            $this->initVariables(); // Reset state.
            throw new \Exception( 
              'Unable to delete storage file! 
              Location: "'.$this->storePath . 'data/' . $data[ '_id' ] . '.json'.'"' 
            );
          }
        }
        // Check do we need to wipe the cache for this store.
        if ( $this->deleteCacheOnCreate === true ) $this->_emptyAllCache();
        $this->initVariables(); // Reset state.
        return true;
      } else {
        // Nothing found to delete
        $this->initVariables(); // Reset state.
        return true;
        // throw new \Exception( 'Invalid store object found, nothing to delete.' );
      }
    }

    // Deletes a store and wipes all the data and cache it contains.
    public function deleteStore() {
      $it = new \RecursiveDirectoryIterator( $this->storePath, \RecursiveDirectoryIterator::SKIP_DOTS );
      $files = new \RecursiveIteratorIterator( $it, \RecursiveIteratorIterator::CHILD_FIRST );
      foreach( $files as $file ) {
        if ( $file->isDir() ) rmdir( $file->getRealPath() );
        else unlink( $file->getRealPath() );
      }
      return rmdir( $this->storePath );
    }

  }