<?php

  /**
   * Coditions trait.
   */
  trait ConditionsTrait {

    // Add conditions to filter data.
    public function where( $fieldName = '', $condition = '', $value ) {
      if ( empty( $fieldName ) ) throw new \Exception( 'Field name in where condition can not be empty.' );
      if ( empty( $condition ) ) throw new \Exception( 'The comparison operator can not be empty.' );
      // Append the condition into the conditions variable.
      $this->conditions[] = [
        'fieldName' => $fieldName,
        'condition' => trim( $condition ),
        'value'     => $value
      ];
      return $this;
    }

    public function in ( $fieldName = '', $values = [] ) {
      if ( empty( $fieldName ) ) throw new \Exception( 'Field name for in clause can not be empty.' );
      $values = (array) $values;
      $this->in[] = [
        'fieldName' => $fieldName,
        'value'     => $values
      ];
      return $this;
    }

    public function notIn ( $fieldName = '', $values = [] ) {
      if ( empty( $fieldName ) ) throw new \Exception( 'Field name for notIn clause can not be empty.' );
      $values = (array) $values;
      $this->notIn[] = [
        'fieldName' => $fieldName,
        'value'     => $values
      ];
      return $this;
    }

    // Add or-where conditions to filter data.
    public function orWhere( $fieldName = '', $condition = '', $value ) {
      if ( empty( $fieldName ) ) throw new \Exception( 'Field name in orWhere condition can not be empty.' );
      if ( empty( $condition ) ) throw new \Exception( 'The comparison operator can not be empty.' );
      // Append the condition into the orConditions variable.
      $this->orConditions[] = [
        'fieldName' => $fieldName,
        'condition' => trim( $condition ),
        'value'     => $value
      ];
      return $this;
    }

    // Set the amount of data record to skip.
    public function skip( $skip = 0 ) {
      if ( $skip === false ) $skip = 0;
      $this->skip = (int) $skip;
      return $this;
    }

    // Set the amount of data record to limit.
    public function limit( $limit = 0 ) {
      if ( $limit === false ) $limit = 0;
      $this->limit = (int) $limit;
      return $this;
    }

    // Set the sort order.
    public function orderBy( $order = false, $orderBy = '_id' ) {
      // Validate order.
      $order = strtolower( $order );
      if ( ! in_array( $order, [ 'asc', 'desc' ] ) ) throw new \Exception( 'Invalid order found, please use "asc" or "desc" only.' );
      $this->orderBy = [
        'order' => $order,
        'field' => $orderBy
      ];
      return $this;
    }

    // Do a fulltext like search against more than one field.
    public function search( $field = '', $keyword = '' ) {
      if ( empty( $field ) ) throw new \Exception( 'Cant perform search doe to no field name was provided' );
      if ( ! empty( $keyword ) ) $this->searchKeyword = [
        'field'   => (array) $field,
        'keyword' => $keyword
      ];
      return $this;
    }

    // Re-generate the cache for the query.
    public function makeCache() {
      $this->makeCache = true;
      $this->useCache  = false;
      return $this;
    }

    // Re-use existing cache of the query, if dosent exists 
    // then would make new cache.
    public function useCache() {
      $this->useCache  = true;
      $this->makeCache = false;
      return $this;
    }

    // Delete cache for the current query.
    public function deleteCache() {
      $this->_deleteCache();
      return $this;
    }

    // Delete all cache of the current store.
    public function deleteAllCache() {
      $this->_emptyAllCache();
      return $this;
    }

    // Keep the active query conditions.
    public function keepConditions () {
      $this->shouldKeepConditions = true;
      return $this;
    }

  }
  