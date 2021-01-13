<?php

namespace SleekDB;

use SleekDB\Exceptions\InvalidArgumentException;

class QueryBuilder
{

  /**
   * @var Store
   */
  protected $store;
  /**
   * @var Cache
   */
  protected $cache;


  protected $in = [];
  protected $skip = 0;
  protected $notIn = [];
  protected $limit = 0;
  protected $orderBy = ['order' => false, 'field' => '_id'];
  protected $conditions = [];
  protected $orConditions = []; // two dimensional array. first dimension is "or" between each condition, second is "and".
  protected $searchKeyword = "";

  protected $fieldsToSelect = [];
  protected $fieldsToExclude = [];

  protected $listOfJoins = [];
  protected $distinctFields = [];

  protected $useCache = null;
  protected $regenerateCache = false;
  protected $cacheLifetime = null;

  // will also not be used for cache token
  protected $propertiesNotUsedInConditionsArray = [
    "propertiesNotUsedInConditionsArray",
    "store",
    "cache",
  ];

  protected $propertiesNotUsedForCacheToken = [
    "useCache",
    "regenerateCache",
    "cacheLifetime"
  ];

  /**
   * QueryBuilder constructor.
   * @param Store $store
   */
  public function __construct(Store $store)
  {
    $this->applyStore($store, true);
  }

  /**
   * @param Store $store
   * @param bool $applyCacheConfigOfStore if set to true the default configurations from the store will be applied
   */
  private function applyStore(Store $store, bool $applyCacheConfigOfStore){
    $this->store = $store;
    if($applyCacheConfigOfStore === true){
      $this->useCache = $store->getUseCache();
      $this->cacheLifetime = $store->getDefaultCacheLifetime();
    }
  }

  /**
   * Select specific fields
   * @param string[] $fieldNames
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function select(array $fieldNames): QueryBuilder
  {
    $errorMsg = "If select is used an array containing strings with fieldNames has to be given";
    foreach ($fieldNames as $fieldName) {
      if (empty($fieldName)) continue;
      if (!is_string($fieldName)) throw new InvalidArgumentException($errorMsg);
      $this->fieldsToSelect[] = $fieldName;
    }
    return $this;
  }

  /**
   * Exclude specific fields
   * @param string[] $fieldNames
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function except(array $fieldNames): QueryBuilder
  {
    $errorMsg = "If except is used an array containing strings with fieldNames has to be given";
    foreach ($fieldNames as $fieldName) {
      if (empty($fieldName)) continue;
      if (!is_string($fieldName)) throw new InvalidArgumentException($errorMsg);
      $this->fieldsToExclude[] = $fieldName;
    }
    return $this;
  }

  /**
   * Validates condition and returns a correctly formatted associative array
   * @param $condition
   * @return array
   * @throws InvalidArgumentException
   */
  private function validateCondition($condition): array
  {
    if(count($condition) !== 3 || !array_key_exists(0, $condition) || !array_key_exists(1, $condition)
      || !array_key_exists(2, $condition)){
      throw new InvalidArgumentException("Invalid condition structure.");
    }

    $fieldName = $condition[0];
    $whereCondition = trim($condition[1]);
    $value = $condition[2];

    if (!is_string($fieldName) || $fieldName === "") {
      throw new InvalidArgumentException("fieldName has to be a string and can not be empty");
    }
    if (!is_string($whereCondition) || $whereCondition === "") {
      throw new InvalidArgumentException("condition has to be a string and can not be empty");
    }

    return [
      "fieldName" => $fieldName,
      "condition" => $whereCondition,
      "value" => $value
    ];
  }

  /**
   * Add conditions to filter data.
   * @param array $conditions
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function where(array $conditions): QueryBuilder
  {
    if (empty($conditions)) {
      throw new InvalidArgumentException("You need to specify a where clause");
    }

    $justOneCondition = false; // the user provided one where condition

    foreach ($conditions as $condition) {

      if (!is_array($condition)) {
        // the user provided just one where clause
        $condition = $conditions;
        $justOneCondition = true;
      }

      $this->conditions[] = $this->validateCondition($condition);

      if($justOneCondition === true) break;
    }

    return $this;
  }

  /**
   * Add "in" condition to filter data.
   * @param string $fieldName
   * @param array $values
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function in(string $fieldName, array $values = []): QueryBuilder
  {
    if (empty($fieldName)) throw new InvalidArgumentException('Field name for in clause can not be empty.');
    $this->in[] = [
      'fieldName' => $fieldName,
      'value'     => $values
    ];
    return $this;
  }

  /**
   * Add "not in" condition to filter data.
   * @param string $fieldName
   * @param array $values
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function notIn(string $fieldName, array $values = []): QueryBuilder
  {
    if (empty($fieldName)) throw new InvalidArgumentException('Field name for notIn clause can not be empty.');
    $this->notIn[] = [
      'fieldName' => $fieldName,
      'value'     => $values
    ];
    return $this;
  }

  /**
   * Add or-where conditions to filter data.
   * @param array $conditions array(array(string fieldName, string condition, mixed value) [, array(...)])
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function orWhere(array $conditions): QueryBuilder
  {

    if (empty($conditions)) {
      throw new InvalidArgumentException("You need to specify a where clause");
    }

    $orConditionsWithAnd = [];

    $justOneCondition = false; // the user provided one where condition

    foreach ($conditions as $condition) {

      // the user provided just one where clause
      if (!is_array($condition)) {
        $condition = $conditions;
        $justOneCondition = true;
      }

      $orConditionsWithAnd[] = $this->validateCondition($condition);

      if($justOneCondition === true) break;
    }

    if(!empty($orConditionsWithAnd)){
      $this->orConditions[] = $orConditionsWithAnd;
    }

    return $this;
  }

  /**
   * Set the amount of data record to skip.
   * @param int $skip
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function skip(int $skip = 0): QueryBuilder
  {
    if($skip < 0){
      throw new InvalidArgumentException("Skip has to be an integer >= 0");
    }

    $this->skip = $skip;

    return $this;
  }

  /**
   * Set the amount of data record to limit.
   * @param int $limit
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function limit($limit = 0): QueryBuilder
  {
    if($limit <= 0){
      throw new InvalidArgumentException("Limit has to be an integer > 0");
    }

    $this->limit = $limit;

    return $this;
  }

  /**
   * Set the sort order.
   * @param array $criteria to order by. array($fieldName => $order). $order can be "asc" or "desc"
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function orderBy( array $criteria): QueryBuilder
  {
    // Validate order.
    $order = "";
    $fieldName = "";
    foreach ($criteria as $fieldName => $order){

      if(!is_string($order))
        throw new InvalidArgumentException('Order has to be a string! Please use "asc" or "desc" only.');

      $order = strtolower($order);

      if(!is_string($fieldName))
        throw new InvalidArgumentException("Field name has to be a string");

      // TODO allow multiple order criteria
      break;
    }

    if (!in_array($order, ['asc', 'desc']))
      throw new InvalidArgumentException('Please use "asc" or "desc" only.');

    $this->orderBy = [
      'field' => $fieldName,
      'order' => $order
    ];
    return $this;
  }

  /**
   * Do a fulltext like search against more than one field.
   * @param string|array $field one fieldName or multiple fieldNames as an array
   * @param string $keyword
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function search($field, string $keyword): QueryBuilder
  {
    if (empty($field)) throw new InvalidArgumentException('Cant perform search due to no field name was provided');
    if (!empty($keyword)) $this->searchKeyword = [
      'field'   => (array) $field,
      'keyword' => $keyword
    ];
    return $this;
  }

  /**
   * @param callable $joinedStore
   * @param string $dataPropertyName
   * @return QueryBuilder
   */
  public function join(callable $joinedStore, string $dataPropertyName): QueryBuilder
  {
    if (is_callable($joinedStore)) {
      $this->listOfJoins[] = [
        'relation' => $joinedStore,
        'name' => $dataPropertyName
      ];
    }
    return $this;
  }


  /**
   * Return distinct values.
   * @param array|string $fields
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function distinct($fields = []): QueryBuilder
  {
    $fieldType = gettype($fields);
    if ($fieldType === 'array') {
      if ($fields === array_values($fields)) {
        // Append fields.
        $this->distinctFields = array_merge($this->distinctFields, $fields);
      } else {
        throw new InvalidArgumentException(
          'Field value in distinct() method can not be an associative array, 
          please provide a string or a list of string as a non-associative array.'
        );
      }
    } else if ($fieldType === 'string' && !empty($fields)) {
      $this->distinctFields[] = trim($fields);
    } else {
      throw new InvalidArgumentException(
        'Field value in distinct() is invalid.'
      );
    }
    return $this;
  }

  /**
   * Use caching for current query
   * @param null|int $lifetime time to live as int in seconds or null to regenerate cache on every insert, update and delete
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function useCache(int $lifetime = null): QueryBuilder
  {
    $this->useCache  = true;
    if(!is_null($lifetime) && (!is_int($lifetime) || $lifetime < 0)){
      throw new InvalidArgumentException("lifetime has to be int >= 0 or null");
    }
    $this->cacheLifetime = $lifetime;
    return $this;
  }

  /**
   * Disable cache for the query.
   * @return QueryBuilder
   */
  public function disableCache(): QueryBuilder
  {
    $this->useCache  = false;
    return $this;
  }

  /**
   * @return null|int
   */
  public function getCacheLifetime(){
    return $this->cacheLifetime;
  }

  /**
   * @return bool
   */
  public function getUseCache(): bool
  {
    return $this->useCache;
  }

  /**
   * This method would make a unique token for the current query.
   * We would use this hash token as the id/name of the cache file.
   * @return string
   */
  public function getCacheToken(): string
  {
    $properties = [];
    $conditionsArray = $this->_getConditionsArray();

    foreach ($conditionsArray as $propertyName => $propertyValue){
      if(!in_array($propertyName, $this->propertiesNotUsedForCacheToken)){
        $properties[$propertyName] = $propertyValue;
      }
    }

    return md5(json_encode($properties));
  }

  /**
   * @return array
   */
  public function _getConditionsArray(): array
  {
    $allProperties = get_object_vars($this);
    $properties = [];

    foreach ($allProperties as $propertyName => $propertyValue){
      if(!in_array($propertyName, $this->propertiesNotUsedInConditionsArray)){
        $properties[$propertyName] = $propertyValue;
      }
    }

    return $properties;
  }

  /**
   * Re-generate the cache for the query.
   * @return QueryBuilder
   */
  public function regenerateCache(): QueryBuilder
  {
    $this->regenerateCache = true;
    return $this;
  }

  /**
   * @return Query
   */
  public function getQuery(): Query
  {
    return new Query($this);
  }

  public function _getStore(): Store{
      return $this->store;
  }
}