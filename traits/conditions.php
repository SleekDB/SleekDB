<?php

  /**
   * Coditions trait.
   */
  trait ConditionsTrait {

    public function where( $fieldName = '', $condition = '', $value ) {
      if ( empty( $fieldName ) ) throw new Exception( 'Field name in conditional comparision can not be empty.' );
      if ( empty( $condition ) ) throw new Exception( 'The comparison operator can not be empty.' );
      // Append the condition into the conditions variable.
      $this->conditions[] = [
        'fieldName' => $fieldName,
        'condition' => trim( $condition ),
        'value'     => $value
      ];
      return $this;
    }

    public function skip( $skip = 0 ) {
      if ( $skip === false ) $skip = 0;
      $this->skip = (int) $skip;
      return $this;
    }

    public function limit( $limit = 0 ) {
      if ( $limit === false ) $limit = 0;
      $this->limit = (int) $limit;
      return $this;
    }

    public function orderBy( $order = false, $orderBy = '_id' ) {
      $this->orderBy = [
        'order' => $order,
        'field' => $orderBy
      ];
      return $this;
    }

    public function search( $field = '', $keyword = '' ) {
      if ( empty( $field ) ) throw new Exception( 'Field name cant be empty while search' );
      if ( ! empty( $keyword ) ) $this->searchKeyword = [
        'field'   => (array) $field,
        'keyword' => $keyword
      ];
      return $this;
    }

  }
  