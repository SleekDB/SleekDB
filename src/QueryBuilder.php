<?php

namespace SleekDB;

use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\Exceptions\InvalidDataException;
use SleekDB\Exceptions\InvalidStoreBootUpException;
use SleekDB\Exceptions\IOException;

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
   * Add conditions to filter data.
   * @param string $fieldName
   * @param string $condition
   * @param mixed $value
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function where(string $fieldName, string $condition, $value): QueryBuilder
  {
    if (empty($fieldName)) throw new InvalidArgumentException('Field name in where condition can not be empty.');
    if (empty($condition)) throw new InvalidArgumentException('The comparison operator can not be empty.');
    // Append the condition into the conditions variable.
    $this->conditions[] = [
      'fieldName' => $fieldName,
      'condition' => trim($condition),
      'value'     => $value
    ];
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
   * @param string|array|mixed ...$conditions (string fieldName, string condition, mixed value) OR ([string fieldName, string condition, mixed value],...)
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function orWhere(...$conditions): QueryBuilder
  {
    foreach ($conditions as $key => $arg) {
      if ($key > 0) throw new InvalidArgumentException("Allowed: (string fieldName, string condition, mixed value) OR ([string fieldName, string condition, mixed value],...)");
      if (is_array($arg)) {
        // parameters given as arrays for an "or where" with "and" between each condition
        $this->_orWhere($conditions);
        break;
      }
      if (count($conditions) === 3 && is_string($arg) && is_string($conditions[1])) {
        // parameters given as (string fieldName, string condition, mixed value) for a single "or where"
        $this->_orWhere([$conditions]);
        break;
      }
    }

    return $this;
  }

  /**
   * @param array $conditions
   * @throws InvalidArgumentException
   */
  private function _orWhere(array $conditions)
  {

    if (!(count($conditions) > 0)) {
      throw new InvalidArgumentException("You need to specify a where clause");
    }

    $orConditionsWithAnd = [];

    foreach ($conditions as $key => $condition) {

      if (!is_array($condition)) {
        throw new InvalidArgumentException("The where clause has to be an array");
      }

      // the user can pass the conditions as an array or a map
      if (
        count($condition) === 3 && array_key_exists(0, $condition) && array_key_exists(1, $condition)
        && array_key_exists(2, $condition)
      ) {
        // user passed the condition as an array

        $fieldName = $condition[0];
        $whereCondition = trim($condition[1]);
        $value = $condition[2];

      } else {
        // user passed the condition as a map

        if (!array_key_exists("fieldName", $condition) || empty($condition["fieldName"])) {
          throw new InvalidArgumentException("fieldName is required in where clause");
        }
        if (!array_key_exists("condition", $condition) || empty($condition["condition"])) {
          throw new InvalidArgumentException("condition is required in where clause");
        }
        if (!array_key_exists("value", $condition)) {
          throw new InvalidArgumentException("value is required in where clause");
        }

        $fieldName = $condition["fieldName"];
        $whereCondition = trim($condition["condition"]);
        $value = $condition["value"];
      }

      if (empty($fieldName)) {
        throw new InvalidArgumentException("fieldName is required in where clause");
      }
      if (empty($whereCondition)) {
        throw new InvalidArgumentException("condition is required in where clause");
      }

      $orConditionsWithAnd[] = [
        "fieldName" => $fieldName,
        "condition" => $whereCondition,
        "value" => $value
      ];
    }

    if(!empty($orConditionsWithAnd)){
      $this->orConditions[] = $orConditionsWithAnd;
    }
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
   * @param string $order "asc" or "desc"
   * @param string $orderBy
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function orderBy(string $order, string $orderBy = '_id'): QueryBuilder
  {
    // Validate order.
    $order = strtolower($order);
    if (!in_array($order, ['asc', 'desc'])) throw new InvalidArgumentException('Invalid order. Please use "asc" or "desc" only.');
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
   * @throws InvalidDataException
   */
  public function distinct($fields = []): QueryBuilder
  {
    $fieldType = gettype($fields);
    if ($fieldType === 'array') {
      if ($fields === array_values($fields)) {
        // Append fields.
        $this->distinctFields = array_merge($this->distinctFields, $fields);
      } else {
        throw new InvalidDataException(
          'Field value in distinct() method can not be an associative array, 
          please provide a string or a list of string as a non-associative array.'
        );
      }
    } else if ($fieldType === 'string' && !empty($fields)) {
      $this->distinctFields[] = trim($fields);
    } else {
      throw new InvalidDataException(
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
   * @throws InvalidStoreBootUpException
   */
  public function getQuery(): Query
  {
    return new Query($this);
  }

  /**
   * Set DataDirectory for current query.
   * @param string $directory
   * @return QueryBuilder
   * @throws InvalidConfigurationException
   * @throws IOException
   */
  public function setDataDirectory(string $directory): QueryBuilder
  {
    $store = $this->store->setDataDirectory($directory);
    $this->applyStore($store, false);
    return $this;
  }

  /**
   * @return string
   */
  public function getDataDirectory(): string
  {
    return $this->store->getDataDirectory();
  }

  public function getStore(): Store{
      return $this->store;
  }
}