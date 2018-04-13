<?php

  require_once './traits/helpers.php';

  class SleekDB {

    use HelperTrait;

    protected $root;
    protected $storeName;

    // Initialize the store.
    function __construct( $storeName = false ) {
      if ( ! $storeName OR empty( $storeName ) ) throw new Exception( 'Invalid store name provided' );
      // Define the root path of FawlDB
      $this->root = __DIR__;
      // Define the store path
      $this->storeName = $this->root . '/store/data_store/' . $storeName;
      // Create the store if it is no already created
      if ( ! file_exists( $this->storeName ) ) mkdir( $this->storeName );
    }

    public function readStore( $searchBy = '*', $skip = false, $limit = false, $orderBy = false ) {
      return $this->lookForStore( $searchBy, $skip, $limit );
    }

    public function createStore( $storeData = false ) {
      // Handle invalid data
      if ( ! $storeData OR empty( $storeData ) ) throw new Exception( 'No data found to store' );
      // Make sure that the data is an array
      if ( ! is_array( $storeData ) ) throw new Exception( 'Storable data must an array' );
      // Cast to array
      $storeData = (array) $storeData;
      // Check if it has _id key
      if ( isset( $storeData[ '_id' ] ) ) throw new Exception( 'The _id index is reserved by SleekDB, please delete 
        the _id key and try again' );
      $id = $this->getStoreId();
      // Debugging purposes
      echo __DIR__ . ' : ' . $id;
      // Add the system ID with the store data array.
      $storeData[ '_id' ] = $id;
      // Prepare storable data
      $storableJSON = json_encode( $storeData );
      if ( $storableJSON === false ) throw new Exception( 'Unable to encode the data array, 
        please provide a valid PHP associative array' );
      // Define the store path
      $storePath = $this->storeName . '/' . $id . '.json';
      file_put_contents( $storePath, $storableJSON );
      return $storeData;
    }

    public function updateStore( $searchBy, $updateable ) {
      // Find all store objects.
      $storeObjects = $this->lookForStore( $searchBy );
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

    public function deleteStore( $searchBy ) {
      // Find all store objects.
      $storeObjects = $this->lookForStore( $searchBy );
      if ( ! empty( $storeObjects ) ) {
        foreach ( $storeObjects as $data ) {
          unlink( $this->storeName . '/' . $data[ '_id' ] . '.json' );
        }
        return true;
      } else {
        // Nothing found to delete
        return false;
      }
    }

  }