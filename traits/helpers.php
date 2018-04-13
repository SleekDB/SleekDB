<?php

  /**
   * Collections of method that helps to manage the data.
   */
  trait HelperTrait {
    
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

    public function lookForStore( $searchBy = [], $skip = false, $limit = false ) {
      $found = [];
      $iterationCount = 0;
      $objectCount = 0;
      if ( $skip === false ) $skip = 0;
      for ( $i = 0; $i <= $this->getLastStoreId(); $i++ ) {
        $data = $this->getStoreById( $i );
        if ( ! empty( $data ) ) {
          // Incrementing the iteration count.
          $iterationCount++;
          // Skip data.
          if ( $iterationCount > $skip ) {
            // Filter data found.
            if ( $searchBy === '*' ) {
              // Append all data of this store.
              $found[] = $data;
              // Increment total object count.
              $objectCount++;
            } else {
              // Append only matched data from this store.
              $isStoreUpdatable = true;
              foreach ( $searchBy as $key => $value ) {
                if ( isset( $data[ $key ] ) ) {
                  if ( $data[ $key ] != $value ) $isStoreUpdatable = false;
                } else {
                  $isStoreUpdatable = false;
                }
              }
              // Check if current store is updatable or not.
              if ( $isStoreUpdatable === true ) {
                $found[] = $data;
                // Increment total object count.
                $objectCount++;
              }
            }
            // Check if we are at the limit.
            if ( $limit !== false ) {
              if ( $objectCount === $limit ) {
                // Stopping looking for more data.
                break;
              }
            }
          }
        }
      }
      return $found;
    }

    public function sortArray( $field, $data, $order = 'ASC' ) {
      $dryData = [];
      if( is_array( $data ) ) {
        foreach ( $data as $value ) {
          foreach( explode( '.', $field ) as $i ) {
            // If the field do not exists then insert an empty string.
            if ( ! isset( $value[ $i ] ) ) {
              $value = '';
              break;
            } else {
              $value = $value[ $i ];
            }
          }
          // Store the value of the property.
          $dryData[] = $value;
        }
      }
      if ( strtolower( $order ) === 'asc' ) asort( $dryData );
      else if ( strtolower( $order ) === 'desc' ) arsort( $dryData );
      // Re arrange the array.
      $finalArray = [];
      foreach ( $dryData as $key => $value) {
        $finalArray[] = $data[ $key ];
      }
      return $finalArray;
    } 

  }
  