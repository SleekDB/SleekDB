<?php

  /**
   * Collections of method that helps to manage the data.
   */
  trait HelpersTrait {

    public function init( $storeName ) {
      if ( ! $storeName OR empty( $storeName ) ) throw new Exception( 'Invalid store name provided' );
      // Define the root path of FawlDB
      $this->root = __DIR__ . '/../';
      // Define the store path
      $this->storeName = $this->root . '/store/data_store/' . $storeName;
      // Create the store if it is no already created
      if ( ! file_exists( $this->storeName ) ) mkdir( $this->storeName );
      // Set a default limit
      $this->limit = 0;
      // Set a default skip
      $this->skip = 0;
      // Set default conditions
      $this->conditions = [];
      // Set default group by value
      $this->orderBy = [
        'order' => false,
        'field' => '_id'
      ];
      // Set the default search keyword as an empty string.
      $this->searchKeyword = '';
    }
    
    public function getStoreId() {
      $counter = (int) file_get_contents( './store/system_index/counter.sdb' );
      $counter++;
      file_put_contents( './store/system_index/counter.sdb', $counter );
      return $counter;
    }

    public function getLastStoreId() {
      return (int) file_get_contents( './store/system_index/counter.sdb' );
    }

    public function getStoreById( $id ) {
      $store = $this->storeName . '/' . $id . '.json';
      if ( file_exists( $store ) ) {
        $data = json_decode( file_get_contents( $store ), true );
        if ( $data !== false ) return $data;
      }
      return [];
    }

    public function findStore() {
      $found          = [];
      $lastStoreId    = $this->getLastStoreId();
      $searchRank     = [];
      // Sort found result.
      if ( $this->orderBy[ 'order' ] !== false ) {
        // Get all store objects.
        $stores = [];
        for ( $i = 0; $i <= $lastStoreId; $i++ ) {
          $rawStore = $this->getStoreById( $i );
          if ( ! empty( $rawStore ) ) $stores[] = $rawStore;
        }
        // Start sorting on all data.
        $stores = $this->sortArray( $this->orderBy[ 'field' ], $stores, $this->orderBy[ 'order' ] );
      }
      // Filter data.
      for ( $i = 0; $i <= $lastStoreId; $i++ ) {
        if ( $this->orderBy[ 'order' ] === false ) {
          // We dont need to order the data, it will be a natural sort.
          $data = $this->getStoreById( $i );
        } else {
          // We should have a sorted data array.
          $data = $stores[ $i ];
        }
        if ( ! empty( $data ) ) {
          // Filter data found.
          if ( empty( $this->conditions ) ) {
            // Append all data of this store.
            $found[] = $data;
          } else {
            // Append only passed data from this store.
            $storePassed = true;
            // Iterate each conditions.
            foreach ( $this->conditions as $condition ) {
              // Check for valid data from data source.
              $validData = true;
              $fieldValue = '';
              try {
                $fieldValue = $this->getNestedProperty( $condition[ 'fieldName' ], $data );
              } catch( Exception $e ) {
                $validData   = false;
                $storePassed = false;
              }
              if( $validData === true ) {
                // Check the type of rule.
                if ( $condition[ 'condition' ] === '=' ) {
                  // Check equal.
                  if ( $fieldValue != $condition[ 'value' ] ) $storePassed = false;
                } else if ( $condition[ 'condition' ] === '!=' ) {
                  // Check not equal.
                  if ( $fieldValue == $condition[ 'value' ] ) $storePassed = false;
                } else if ( $condition[ 'condition' ] === '>' ) {
                  // Check greater than.
                  if ( $fieldValue <= $condition[ 'value' ] ) $storePassed = false;
                } else if ( $condition[ 'condition' ] === '>=' ) {
                  // Check greater equal.
                  if ( $fieldValue < $condition[ 'value' ] ) $storePassed = false;
                } else if ( $condition[ 'condition' ] === '<' ) {
                  // Check less than.
                  if ( $fieldValue >= $condition[ 'value' ] ) $storePassed = false;
                } else if ( $condition[ 'condition' ] === '<=' ) {
                  // Check less equal.
                  if ( $fieldValue > $condition[ 'value' ] ) $storePassed = false;
                }
                // Do a text search if provided.
                if ( ! empty( $this->searchKeyword ) ) {
                  // Search can be made for more than one field.
                  // Check if a matched field was found to search.
                  if ( in_array( $condition[ 'fieldName' ], $this->searchKeyword[ 'field' ] ) ) {
                    similar_text( $fieldValue, $this->searchKeyword, $perc );
                    if ( $perc > 50 ) {
                      // Check if current store object already has a value, if so then add the new value.
                      if ( isset( $searchRank[ $data ] ) ) $searchRank[ $data ] += $perc;
                      else $searchRank[ $data ] = $perc;
                    } else {
                      $storePassed = false;
                    }
                  }
                }
              }
            }
            // Check if current store is updatable or not.
            if ( $storePassed === true ) {
              // Append data to the found array.
              $found[] = $data;
            }
          }
        }
      }
      print_r( $this->searchKeyword );
      print_r( $searchRank );
      if ( count( $found ) > 0 ) {
        // If there was text search then we would also sort the result by search ranking.
        if ( ! empty( $this->searchKeyword ) ) {
          $searchRank = arsort( $searchRank );
          print_r( $searchRank );
        }
        // Skip data
        if ( $this->skip > 0 ) $found = array_slice( $found, $this->skip );
        // Limit data.
        if ( $this->limit > 0 ) $found = array_slice( $found, 0, $this->limit );
      }
      return $found;
    }

    public function sortArray( $field, $data, $order = 'ASC' ) {
      $dryData = [];
      // Check if data is an array.
      if( is_array( $data ) ) {
        // Get value of the target field.
        foreach ( $data as $value ) {
          $dryData[] = $this->getNestedProperty( $field, $value );
        }
      }
      // Descide the order direction.
      if ( strtolower( $order ) === 'asc' ) asort( $dryData );
      else if ( strtolower( $order ) === 'desc' ) arsort( $dryData );
      // Re arrange the array.
      $finalArray = [];
      foreach ( $dryData as $key => $value) {
        $finalArray[] = $data[ $key ];
      }
      return $finalArray;
    }

    public function getNestedProperty( $field = '', $data ) {
      if( is_array( $data ) AND ! empty( $field ) ) {
        // Dive deep step by step.
        foreach( explode( '.', $field ) as $i ) {
          // If the field do not exists then insert an empty string.
          if ( ! isset( $data[ $i ] ) ) {
            $data = '';
            throw new Exception( '"'.$i.'" index was not found in the provided data array' );
            break;
          } else {
            // The index is valid, collect the data.
            $data = $data[ $i ];
          }
        }
        return $data;
      }
    }

    private function performSerach( $data = [] ) {
      if ( empty( $data ) ) return $data;
    }
    
  }
  