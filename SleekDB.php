<?php

  require_once './traits/helpers.php';
  require_once './traits/conditions.php';

  class SleekDB {

    use HelpersTrait, ConditionsTrait;

    protected $root;
    protected $storeName;
    protected $limit;
    protected $skip;
    protected $conditions;
    protected $orderBy;
    protected $searchKeyword;
    protected $makeCache;

    // Initialize the store.
    function __construct( $storeName = false ) {
      $this->init( $storeName );
    }

    public function cache() {
      $query = json_encode( [
        'store' => $this->storeName,
        'limit' => $this->limit,
        'skip' => $this->skip,
        'condition' => $this->conditions,
        'order' => $this->orderBy,
        'search' => $this->searchKeyword
      ] );
      return md5($query);
    }

    // Read store objects.
    public function readStore() {
      return $this->findStore();
    }

    // Creates a new object in the store.
    // The object is a plaintext JSON document.
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

    // Updates matched store objects.
    public function updateStore( $updateable ) {
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
    public function deleteStore() {
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