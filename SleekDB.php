<?php

  require_once './traits/helpers.php';
  require_once './traits/conditions.php';
  require_once './traits/caching.php';

  class SleekDB {

    use HelpersTrait, ConditionsTrait, CacheTraits;

    // Initialize the store.
    function __construct( $storeName = false ) {
      $this->init( $storeName );
    }

    // Read store objects.
    public function fetch() {
      // Check if data should be provided from the cache.
      if ( $this->makeCache === true ) return $this->reGenerateCache(); // Re-generate cache.
      else if ( $this->useCache === true ) return $this->useExistingCache(); // Use existing cache else re-generate.
      else return $this->findStore(); // Returns data without looking for cached data.
    }

    // Creates a new object in the store.
    // The object is a plaintext JSON document.
    public function insert( $storeData = false ) {
      // Handle invalid data
      if ( ! $storeData OR empty( $storeData ) ) throw new Exception( 'No data found to store' );
      // Make sure that the data is an array
      if ( ! is_array( $storeData ) ) throw new Exception( 'Storable data must an array' );
      $storeData = $this->writeInStore( $storeData );
      // Check do we need to wipe the cache for this store.
      if ( $this->deleteCacheOnCreate === true ) {
        $this->_emptyAllCache();
      }
      return $storeData;
    }

    // Creates multiple objects in the store.
    public function insertMany( $storeData = false ) {
      // Handle invalid data
      if ( ! $storeData OR empty( $storeData ) ) throw new Exception( 'No data found to store' );
      // Make sure that the data is an array
      if ( ! is_array( $storeData ) ) throw new Exception( 'Storable data must an array' );
      // All results.
      $results = [];
      foreach ( $storeData as $key => $node ) {
        $results[] = $this->writeInStore( $node );
      }
      return $results;
    }

    // Updates matched store objects.
    public function update( $updateable ) {
      // Find all store objects.
      $storeObjects = $this->findStore();
      // If no store object found then return an empty array.
      if ( empty( $storeObjects ) ) return false;
      foreach ( $storeObjects as $data ) {
        foreach ( $updateable as $key => $value ) {
          // Do not update the _id reserved index of a store.
          if( $key != '_id' ) {
            $data[ $key ] = $value;
          }
        }
        $storePath = $this->storeName . '/' . $data[ '_id' ] . '.json';
        if ( file_exists( $storePath ) ) {
          file_put_contents( $storePath, json_encode( $data ) );
        }
      }
      return true;
    }

    // Deletes matched store objects.
    public function delete() {
      // Find all store objects.
      $storeObjects = $this->findStore();
      if ( ! empty( $storeObjects ) ) {
        foreach ( $storeObjects as $data ) {
          if ( ! unlink( $this->storeName . '/' . $data[ '_id' ] . '.json' ) ) {
            throw new Exception( 
              'Unable to delete storage file! 
              Location: "'.$this->storeName . '/' . $data[ '_id' ] . '.json'.'"' 
            );
          }
        }
        return true;
      } else {
        // Nothing found to delete
        throw new Exception( 'Invalid store object found, nothing to delete.' );
      }
    }

  }