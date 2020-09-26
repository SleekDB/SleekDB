<?php

namespace SleekDB\Traits;

use SleekDB\Exceptions\EmptyConditionException;
use SleekDB\Exceptions\EmptyFieldNameException;
use SleekDB\Exceptions\InvalidOrderException;

/**
   * Coditions trait.
   */
  trait ConditionTrait {

    /**
     * Add conditions to filter data.
     * @param string $fieldName
     * @param string $condition
     * @param mixed $value
     * @return $this
     * @throws EmptyConditionException
     * @throws EmptyFieldNameException
     */
    public function where( $fieldName, $condition, $value ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name in where condition can not be empty.' );
      if ( empty( $condition ) ) throw new EmptyConditionException( 'The comparison operator can not be empty.' );
      // Append the condition into the conditions variable.
      $this->conditions[] = [
        'fieldName' => $fieldName,
        'condition' => trim( $condition ),
        'value'     => $value
      ];
      return $this;
    }

    /**
     * @param string $fieldName
     * @param array $values
     * @return $this
     * @throws EmptyFieldNameException
     */
    public function in ( $fieldName, $values = [] ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name for in clause can not be empty.' );
      $values = (array) $values;
      $this->in[] = [
        'fieldName' => $fieldName,
        'value'     => $values
      ];
      return $this;
    }

    /**
     * @param string $fieldName
     * @param array $values
     * @return $this
     * @throws EmptyFieldNameException
     */
    public function notIn ( $fieldName, $values = [] ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name for notIn clause can not be empty.' );
      $values = (array) $values;
      $this->notIn[] = [
        'fieldName' => $fieldName,
        'value'     => $values
      ];
      return $this;
    }

    /**
     * Add or-where conditions to filter data.
     * @param string $fieldName
     * @param string $condition
     * @param mixed $value
     * @return $this
     * @throws EmptyConditionException
     * @throws EmptyFieldNameException
     */
    public function orWhere( $fieldName, $condition, $value ) {
      if ( empty( $fieldName ) ) throw new EmptyFieldNameException( 'Field name in orWhere condition can not be empty.' );
      if ( empty( $condition ) ) throw new EmptyConditionException( 'The comparison operator can not be empty.' );
      // Append the condition into the orConditions variable.
      $this->orConditions[] = [
        'fieldName' => $fieldName,
        'condition' => trim( $condition ),
        'value'     => $value
      ];
      return $this;
    }

    /**
     * Set the amount of data record to skip.
     * @param int $skip
     * @return $this
     */
    public function skip( $skip = 0 ) {
      if ( $skip === false ) $skip = 0;
      $this->skip = (int) $skip;
      return $this;
    }

    /**
     * Set the amount of data record to limit.
     * @param int $limit
     * @return $this
     */
    public function limit( $limit = 0 ) {
      if ( $limit === false ) $limit = 0;
      $this->limit = (int) $limit;
      return $this;
    }

    /**
     * Set the sort order.
     * @param string $order "asc" or "desc"
     * @param string $orderBy
     * @return $this
     * @throws InvalidOrderException
     */
    public function orderBy( $order, $orderBy = '_id' ) {
      // Validate order.
      $order = strtolower( $order );
      if ( ! in_array( $order, [ 'asc', 'desc' ] ) ) throw new InvalidOrderException( 'Invalid order found, please use "asc" or "desc" only.' );
      $this->orderBy = [
        'order' => $order,
        'field' => $orderBy
      ];
      return $this;
    }

    /**
     * Do a fulltext like search against more than one field.
     * @param string|array $field one fieldName or multiple fieldNames as an array
     * @param string $keyword
     * @return $this
     * @throws EmptyFieldNameException
     */
    public function search( $field, $keyword) {
      if ( empty( $field ) ) throw new EmptyFieldNameException( 'Cant perform search due to no field name was provided' );
      if ( ! empty( $keyword ) ) $this->searchKeyword = [
        'field'   => (array) $field,
        'keyword' => $keyword
      ];
      return $this;
    }

    /**
     * Re-generate the cache for the query.
     * @return $this
     */
    public function makeCache() {
      $this->makeCache = true;
      $this->useCache  = false;
      return $this;
    }

    /**
     * Re-use existing cache of the query, if doesnt exists
     * then would make new cache.
     * @return $this
     */
    public function useCache() {
      $this->useCache  = true;
      $this->makeCache = false;
      return $this;
    }

    /**
     * Delete cache for the current query.
     * @return $this
     */
    public function deleteCache() {
      $this->_deleteCache();
      return $this;
    }

    /**
     * Delete all cache of the current store.
     * @return $this
     */
    public function deleteAllCache() {
      $this->_emptyAllCache();
      return $this;
    }

    /**
     * Keep the active query conditions.
     * @return $this
     */
    public function keepConditions () {
      $this->shouldKeepConditions = true;
      return $this;
    }

  }
  