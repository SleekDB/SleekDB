<?php

namespace SleekDB;

use Closure;
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

  protected $queryConditions = [
    "whereConditions" => [],
    "skip" => 0,
    "limit" => 0,
    "orderBy" => [],
    "search" => [],
    "searchOptions" => [
      "minLength" => 2,
      "scoreKey" => "searchScore",
      "mode" => "or",
      "algorithm" => Query::SEARCH_ALGORITHM["hits"]
    ],
    "fieldsToSelect" => [],
    "fieldsToExclude" => [],
    "groupBy" => [],
    "havingConditions" => [],
    "listOfJoins" => [],
    "distinctFields" => [],
    "useCache" => 1,
    "regenerateCache" => false,
    "cacheLifetime" => 0
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
    $this->store = $store;
    $this->queryConditions["useCache"] = $store->_getUseCache();
    $this->queryConditions["cacheLifetime"] = $store->_getDefaultCacheLifetime();
    $this->queryConditions["searchOptions"] = $store->_getSearchOptions();
  }

  /**
   * Select specific fields
   * @param array $fieldNames
   * @return QueryBuilder
   */
  public function select(array $fieldNames): QueryBuilder
  {
    foreach ($fieldNames as $key => $fieldName) {
      if (is_string($key)) {
        $this->queryConditions["fieldsToSelect"][$key] = $fieldName;
      } else {
        $this->queryConditions["fieldsToSelect"][] = $fieldName;
      }
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
      if (empty($fieldName)) {
        continue;
      }
      if (!is_string($fieldName)) {
        throw new InvalidArgumentException($errorMsg);
      }
      $this->queryConditions["fieldsToExclude"][] = $fieldName;
    }
    return $this;
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

    $this->queryConditions["whereConditions"][] = $conditions;

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

    $this->queryConditions["whereConditions"][] = "or";
    $this->queryConditions["whereConditions"][] = $conditions;

    return $this;
  }

  /**
   * Set the amount of data record to skip.
   * @param int|string $skip
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function skip($skip = 0): QueryBuilder
  {
    if ((!is_string($skip) || !is_numeric($skip)) && !is_int($skip)) {
      throw new InvalidArgumentException("Skip has to be an integer or a numeric string");
    }

    if (!is_int($skip)) {
      $skip = (int) $skip;
    }

    if ($skip < 0) {
      throw new InvalidArgumentException("Skip has to be an integer >= 0");
    }

    $this->queryConditions["skip"] = $skip;

    return $this;
  }

  /**
   * Set the amount of data record to limit.
   * @param int|string $limit
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function limit($limit = 0): QueryBuilder
  {

    if ((!is_string($limit) || !is_numeric($limit)) && !is_int($limit)) {
      throw new InvalidArgumentException("Limit has to be an integer or a numeric string");
    }

    if (!is_int($limit)) {
      $limit = (int) $limit;
    }

    if ($limit <= 0) {
      throw new InvalidArgumentException("Limit has to be an integer > 0");
    }

    $this->queryConditions["limit"] = $limit;

    return $this;
  }

  /**
   * Set the sort order.
   * @param array $criteria to order by. array($fieldName => $order). $order can be "asc" or "desc"
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function orderBy(array $criteria): QueryBuilder
  {
    foreach ($criteria as $fieldName => $order) {

      if (!is_string($order)) {
        throw new InvalidArgumentException('Order has to be a string! Please use "asc" or "desc" only.');
      }

      $order = strtolower($order);

      if (!is_string($fieldName)) {
        throw new InvalidArgumentException("Field name has to be a string");
      }

      if (!in_array($order, ['asc', 'desc'])) {
        throw new InvalidArgumentException('Please use "asc" or "desc" only.');
      }

      $this->queryConditions["orderBy"][] = [
        'fieldName' => $fieldName,
        'order' => $order
      ];
    }

    return $this;
  }

  /**
   * Do a fulltext like search against one or multiple fields.
   * @param string|array $fields one or multiple fieldNames as an array
   * @param string $query
   * @param array $options
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function search($fields, string $query, array $options = []): QueryBuilder
  {
    if (!is_array($fields) && !is_string($fields)) {
      throw new InvalidArgumentException("Fields to search through have to be either a string or an array.");
    }

    if (!is_array($fields)) {
      $fields = (array)$fields;
    }

    if (empty($fields)) {
      throw new InvalidArgumentException('Cant perform search due to no field name was provided');
    }

    if (count($fields) > 100) {
      trigger_error('Searching through more than 100 fields is not recommended and can be resource heavy.', E_USER_WARNING);
    }

    if (!empty($query)) {
      $this->queryConditions["search"] = [
        'fields' => $fields,
        'query' => $query
      ];
      if (!empty($options)) {
        if (array_key_exists("minLength", $options) && is_int($options["minLength"]) && $options["minLength"] > 0) {
          $this->queryConditions["searchOptions"]["minLength"] = $options["minLength"];
        }
        if (array_key_exists("mode", $options) && is_string($options["mode"])) {
          $searchMode = strtolower(trim($options["mode"]));
          if (in_array($searchMode, ["and", "or"])) {
            $this->queryConditions["searchOptions"]["mode"] = $searchMode;
          }
        }
        if (array_key_exists("scoreKey", $options) && (is_string($options["scoreKey"]) || is_null($options["scoreKey"]))) {
          $this->queryConditions["searchOptions"]["scoreKey"] = $options["scoreKey"];
        }
        if (array_key_exists("algorithm", $options) && in_array($options["algorithm"], Query::SEARCH_ALGORITHM, true)) {
          $this->queryConditions["searchOptions"]["algorithm"] = $options["algorithm"];
        }
      }
    }
    return $this;
  }

  /**
   * @param Closure $joinFunction
   * @param string $propertyName
   * @return QueryBuilder
   */
  public function join(Closure $joinFunction, string $propertyName): QueryBuilder
  {
    $this->queryConditions["listOfJoins"][] = [
      'propertyName' => $propertyName,
      'joinFunction' => $joinFunction
    ];
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
        $this->queryConditions["distinctFields"] = array_merge($this->queryConditions["distinctFields"], $fields);
      } else {
        throw new InvalidArgumentException(
          'Field value in distinct() method can not be an associative array, 
          please provide a string or a list of string as a non-associative array.'
        );
      }
    } else if ($fieldType === 'string' && !empty($fields)) {
      $this->queryConditions["distinctFields"][] = trim($fields);
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
    $this->queryConditions["useCache"] = true;
    if ((!is_int($lifetime) || $lifetime < 0) && !is_null($lifetime)) {
      throw new InvalidArgumentException("lifetime has to be int >= 0 or null");
    }
    $this->queryConditions["cacheLifetime"] = $lifetime;
    return $this;
  }

  /**
   * Disable cache for the query.
   * @return QueryBuilder
   */
  public function disableCache(): QueryBuilder
  {
    $this->queryConditions["useCache"] = false;
    return $this;
  }

  /**
   * Re-generate the cache for the query.
   * @return QueryBuilder
   */
  public function regenerateCache(): QueryBuilder
  {
    $this->queryConditions["regenerateCache"] = true;
    return $this;
  }

  /**
   * @return Query
   */
  public function getQuery(): Query
  {
    return new Query($this);
  }

  /**
   * @param array $groupByFields
   * @param string|null $countKeyName
   * @param bool $allowEmpty
   * @return QueryBuilder
   */
  public function groupBy(array $groupByFields, string $countKeyName = null, bool $allowEmpty = false): QueryBuilder
  {
    $this->queryConditions["groupBy"] = [
      "groupByFields" => $groupByFields,
      "countKeyName" => $countKeyName,
      "allowEmpty" => $allowEmpty
    ];
    return $this;
  }

  /**
   * Filter result data of groupBy
   * @param array $criteria
   * @return QueryBuilder
   * @throws InvalidArgumentException
   */
  public function having(array $criteria): QueryBuilder
  {
    if (empty($criteria)) {
      throw new InvalidArgumentException("You need to specify a having clause");
    }
    $this->queryConditions["havingConditions"] = $criteria;
    return $this;
  }

  /**
   * Returns a an array used to generate a unique token for the current query.
   * @return array
   */
  public function _getCacheTokenArray(): array
  {
    $properties = [];

    foreach ($this->queryConditions as $propertyName => $propertyValue) {
      if (!in_array($propertyName, $this->propertiesNotUsedForCacheToken, true)) {
        $properties[$propertyName] = $propertyValue;
      }
    }

    return $properties;
  }

  /**
   * Returns an array containing all information needed to execute an query.
   * @return array
   */
  public function _getConditionProperties(): array
  {

    return $this->queryConditions;
  }

  /**
   * Returns the Store object used to create the QueryBuilder object.
   * @return Store
   */
  public function _getStore(): Store
  {
    return $this->store;
  }

  /**
   * Add "in" condition to filter data.
   * @param string $fieldName
   * @param array $values
   * @return QueryBuilder
   * @throws InvalidArgumentException
   * @deprecated since version 2.4, use where and orWhere instead.
   */
  public function in(string $fieldName, array $values = []): QueryBuilder
  {
    if (empty($fieldName)) {
      throw new InvalidArgumentException('Field name for in clause can not be empty.');
    }

    // Add to conditions with "AND" operation
    $this->queryConditions["whereConditions"][] = [$fieldName, "in", $values];
    return $this;
  }

  /**
   * Add "not in" condition to filter data.
   * @param string $fieldName
   * @param array $values
   * @return QueryBuilder
   * @throws InvalidArgumentException
   * @deprecated since version 2.4, use where and orWhere instead.
   */
  public function notIn(string $fieldName, array $values = []): QueryBuilder
  {
    if (empty($fieldName)) {
      throw new InvalidArgumentException('Field name for notIn clause can not be empty.');
    }

    // Add to conditions with "AND" operation
    $this->queryConditions["whereConditions"][] = [$fieldName, "not in", $values];
    return $this;
  }
}
