<?php

namespace SleekDB;

use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\InvalidPropertyAccessException;
use SleekDB\Exceptions\IOException;
use Exception;
use Throwable;

class Query
{

  protected $storePath;

  protected $queryBuilderProperties;

  /**
   * @var Cache
   */
  protected $cache;

  protected $cacheTokenArray;


  const DELETE_RETURN_BOOL = 1;
  const DELETE_RETURN_RESULTS = 1;
  const DELETE_RETURN_COUNT = 1;

  protected $primaryKey;

  /**
   * Query constructor.
   * @param QueryBuilder $queryBuilder
   */
  public function __construct(QueryBuilder $queryBuilder)
  {
    $store = $queryBuilder->_getStore();

    $this->storePath = $store->getStorePath();

    $this->primaryKey = $store->getPrimaryKey();

    $this->queryBuilderProperties = $queryBuilder->_getConditionProperties();

    $this->cacheTokenArray = $queryBuilder->_getCacheTokenArray();

    // set cache
    $this->cache = new Cache($this, $this->_getStorePath());
    $this->cache->setLifetime($this->_getCacheLifeTime());
  }


  /**
   * @return Cache
   */
  public function getCache(): Cache
  {
    return $this->cache;
  }

  /**
   * Execute Query and get Results
   * @return array
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   * @throws IOException
   */
  public function fetch(): array
  {
    $this->updateCacheTokenArray(['oneDocument' => false]);

    $results = $this->getCacheContent();

    if($results !== null) {
      return $results;
    }

    $results = $this->findStoreDocuments();

    $this->joinData($results);

    $this->setCacheContent($results);

    return $results;
  }

  /**
   * Check if data is found
   * @return bool
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   * @throws IOException
   */
  public function exists(): bool
  {
    // Return boolean on data exists check.
    return !empty($this->first());
  }

  /**
   * Return the first document.
   * @return array empty array or single document
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   * @throws IOException
   */
  public function first(): array
  {
    $this->updateCacheTokenArray(['oneDocument' => true]);

    $results = $this->getCacheContent();
    if($results !== null) {
      return $results;
    }

    $results = $this->findStoreDocuments(true);

    $this->joinData($results);

    if (count($results) > 0) {
      list($item) = $results;
      $results = $item;
    }

    $this->setCacheContent($results);

    return $results;
  }

  /**
   * Update one or multiple documents, based on current query
   * @param array $updatable
   * @return bool
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidPropertyAccessException
   */
  public function update(array $updatable): bool
  {
    $results = $this->findStoreDocuments();
    // If no documents found return false.
    if (empty($results)) {
      return false;
    }

    $primaryKey = $this->primaryKey;

    foreach ($results as $data) {
      foreach ($updatable as $key => $value) {
        // Do not update the primary key reserved index of a store.
        if ($key !== $primaryKey) {
          $data[$key] = $value;
        }
      }
      $storePath = $this->_getStoreDataPath() . $data[$primaryKey] . '.json';
      if (file_exists($storePath)) {
        // Wait until it's unlocked, then update data.
        $this->_checkWrite($storePath);
        file_put_contents($storePath, json_encode($data), LOCK_EX);
      }
    }
    $this->cache->deleteAllWithNoLifetime();
    return true;
  }

  /**
   * Deletes matched store objects.
   * @param int $returnOption
   * @return bool|array|int
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidPropertyAccessException
   */
  public function delete(int $returnOption = self::DELETE_RETURN_BOOL)
  {
    $results = $this->findStoreDocuments();
    $returnValue = null;

    $primaryKey = $this->primaryKey;

    switch ($returnOption){
      case self::DELETE_RETURN_BOOL:
        $returnValue = !empty($results);
        break;
      case self::DELETE_RETURN_COUNT:
        $returnValue = count($results);
        break;
      case self::DELETE_RETURN_RESULTS:
        $returnValue = $results;
        break;
      default:
        throw new InvalidArgumentException("return option \"$returnOption\" is not supported");
    }

    if (!empty($results)) {
      foreach ($results as $key => $data) {
        $filePath = $this->_getStoreDataPath() . $data[$primaryKey] . '.json';
        if (file_exists($filePath) && false === @unlink($filePath)) {
          throw new IOException(
            'Unable to delete document! 
            Already deleted documents: '.$key.'. 
            Location: "' . $filePath .'"'
          );
        }
      }
    }


    $this->cache->deleteAllWithNoLifetime();

    return $returnValue;
  }

  /**
   * @param string $propertyKey
   * @return mixed
   * @throws InvalidPropertyAccessException
   */
  private function getQueryBuilderProperty(string $propertyKey){
    if(array_key_exists($propertyKey,$this->queryBuilderProperties)){
      return $this->queryBuilderProperties[$propertyKey];
    }
    throw new InvalidPropertyAccessException("Tried to access condition \"$propertyKey\" which is not specified in QueryBuilder as property");
  }

  /**
   * Get results from cache
   * @return array|null
   * @throws IOException
   * @throws InvalidPropertyAccessException
   */
  private function getCacheContent()
  {
    $useCache = $this->getQueryBuilderProperty("useCache");
    $regenerateCache = $this->getQueryBuilderProperty("regenerateCache");

    if($useCache === true){
      $cache = $this->getCache();


      if($regenerateCache === true) {
        $cache->delete();
      }

      $cacheResults = $cache->get();

      if(is_array($cacheResults)) {
        return $cacheResults;
      }
    }
    return null;
  }

  /**
   * Add content to cache
   * @param array $results
   * @throws IOException
   * @throws InvalidPropertyAccessException
   */
  private function setCacheContent(array $results)
  {
    $useCache = $this->getQueryBuilderProperty("useCache");
    if($useCache === true){
      $cache = $this->getCache();
      $cache->set($results);
    }
  }

  /**
   * @param array $results
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   */
  private function joinData(array &$results){
    // Join data.
    $listOfJoins = $this->getQueryBuilderProperty("listOfJoins");
    foreach ($results as $key => $doc) {
      foreach ($listOfJoins as $join) {
        // Execute the child query.
        $joinQuery = ($join['joinFunction'])($doc); // QueryBuilder or result of fetch
        $dataPropertyName =$join['dataPropertyName'];

        // TODO remove SleekDB check in version 3.0
        if($joinQuery instanceof QueryBuilder || $joinQuery instanceof SleekDB){
          $joinResult = $joinQuery->getQuery()->fetch();
        } else if(is_array($joinQuery)){
          // user already fetched the query in the join query function
          $joinResult = $joinQuery;
        } else {
          throw new InvalidArgumentException("Invalid join query.");
        }

        // TODO discuss if that is a good idea -> would be inconsistent
        //  if(count($joinResult) === 1) $joinResult = $joinResult[0];

        // Add child documents with the current document.
        $results[$key][$dataPropertyName] = $joinResult;
      }
    }
  }

  /**
   * @param string $condition
   * @param mixed $fieldValue value of current field
   * @param mixed $value value to check
   * @return bool
   * @throws InvalidArgumentException
   */
  private function verifyWhereConditions(string $condition, $fieldValue, $value): bool
  {
    $condition = strtolower(trim($condition));
    switch ($condition){
      case "=":
        return ($fieldValue === $value);
      case "!=":
        return ($fieldValue !== $value);
      case ">":
        return ($fieldValue > $value);
      case ">=":
        return ($fieldValue >= $value);
      case "<":
        return ($fieldValue < $value);
      case "<=":
        return ($fieldValue <= $value);
      case "not like":
      case "like":

        // escape characters that are part of regular expression syntax
        // https://www.php.net/manual/en/function.preg-quote.php
        // We can not use preg_quote because the following characters are also wildcard characters in sql
        // so we will not escape them: [ ^ ] -
        $charactersToEscape = [".", "\\", "+", "*", "?", "$", "(", ")", "{", "}", "=", "!", "<", ">", "|", ":",  "#"];
        foreach ($charactersToEscape as $characterToEscape){
          $value = str_replace($characterToEscape, "\\".$characterToEscape, $value); // zero or more characters
        }


        $value = str_replace(array('%', '_'), array('.*', '.{1}'), $value); // (zero or more characters) and (single character)
        $pattern = "/^" . $value . "$/i";
        $result = (preg_match($pattern, $fieldValue) === 1);
        return ($condition === "not like") ? !$result : $result;

      case "not in":
      case "in":
        if(!is_array($value)){
          $value = (!is_object($value) && !is_array($value) && !is_null($value)) ? $value : gettype($value);
          throw new InvalidArgumentException("When using \"in\" and \"not in\" you have to check against an array. Got: $value");
        }
        $result = in_array($fieldValue, $value, true);
        return ($condition === "not in") ? !$result : $result;
      default:
        throw new InvalidArgumentException("Condition \"$condition\" is not allowed.");
    }
  }

  /**
   * @param bool $getOneDocument
   * @return array
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   * @throws IOException
   */
  private function findStoreDocuments(bool $getOneDocument = false): array
  {
    $found = [];
    // Start collecting and filtering data.
    $storeDataPath = $this->_getStoreDataPath();
    $this->_checkRead($storeDataPath);

    $conditions = $this->getQueryBuilderProperty("conditions");
    $distinctFields = $this->getQueryBuilderProperty("distinctFields");

    if ($handle = opendir($storeDataPath)) {

      while (false !== ($entry = readdir($handle))) {

        if ($entry === "." || $entry === "..") {
          continue;
        }

        $documentPath = $storeDataPath . $entry;

        $this->_checkRead($documentPath);

        $data = "";
        $fp = fopen($documentPath, 'rb');
        if(flock($fp, LOCK_SH)){
          $data = @json_decode(@stream_get_contents($fp), true); // get document by path
        }
        flock($fp, LOCK_UN);
        fclose($fp);

        if (empty($data)) {
          continue;
        }

        $storePassed = true;

        // Append only passed data from this store.

        // Process conditions
        if(!empty($conditions)) {
          // Iterate each conditions.
          $storePassed = $this->handleConditions($conditions, $data);
        }

        // TODO remove nested where with version 3.0
        $storePassed = $this->handleNestedWhere($data, $storePassed);

        // Distinct data check.
        if ($storePassed === true && count($distinctFields) > 0) {
          foreach ($found as $result) {
            foreach ($distinctFields as $field) {
              try {
                $storePassed = ($this->getNestedProperty($field, $result) !== $this->getNestedProperty($field, $data));
              } catch (Throwable $th) {
                continue;
              }
              if ($storePassed === false) {
                break;
              }
            }
            if ($storePassed === false) {
              break;
            }
          }
        }

        if ($storePassed === true) {
          $found[] = $data;

          // if we just check for existence or want to return the first item, we dont need to look for more documents
          if ($getOneDocument === true) {
            break;
          }
        }
      }
      closedir($handle);
    }

    // apply additional changes to result like sort and limit

    if (count($found) > 0) {
      // If there was text search then we would also sort the result by search ranking.
      $searchKeyword = $this->getQueryBuilderProperty("searchKeyword");
      if (!empty($searchKeyword)) {
        $found = $this->performSearch($found);
      }
    }

    if(count($found) > 0){
      // sort the data.
      $this->sort($found);

      // Skip data
      $skip = $this->getQueryBuilderProperty("skip");
      if (!empty($skip) && $skip > 0) {
        $found = array_slice($found, $skip);
      }
    }


    if(count($found) > 0) {
      // Limit data.
      $limit = $this->getQueryBuilderProperty("limit");
      if (!empty($limit) && $limit > 0) {
        $found = array_slice($found, 0, $limit);
      }
    }

    if(count($found) > 0){
      // select specific fields
      $this->selectFields($found);

      // exclude specific fields
      $this->excludeFields($found);
    }

    return $found;
  }

  /**
   * @param array $element condition or operation
   * @param array $data
   * @return bool
   * @throws InvalidArgumentException
   */
  private function handleConditions(array $element, array &$data): bool
  {
    if(empty($element)){
      throw new InvalidArgumentException("Malformed where statement! Where statements can not contain empty arrays.");
    }
    if(array_keys($element) !== range(0, (count($element) - 1))){
      throw new InvalidArgumentException("Malformed where statement! Associative arrays are not allowed.");
    }
    // element is a where condition
    if(is_string($element[0]) && is_string($element[1])){
      if(count($element) !== 3){
        throw new InvalidArgumentException("Where conditions have to be [fieldName, condition, value]");
      }

      $fieldValue = $this->getNestedProperty($element[0], $data);

      return $this->verifyWhereConditions($element[1], $fieldValue, $element[2]);
    }

    // element is an array "brackets"

    // prepare results array - example: [true, "and", false]
    $results = [];
    foreach ($element as $value){
      if(is_array($value)){
        $results[] = $this->handleConditions($value, $data);
      } else if (is_string($value)){
        $results[] = $value;
      } else {
        $value = (!is_object($value) && !is_array($value) && !is_null($value)) ? $value : gettype($value);
        throw new InvalidArgumentException("Invalid nested where statement element! Expected condition or operation, got: \"$value\"");
      }
    }

    // first result as default value
    $returnValue = array_shift($results);

    if(is_bool($returnValue) === false){
      throw new InvalidArgumentException("Malformed where statement! First part of the statement have to be a condition.");
    }

    // used to prioritize the "and" operation.
    $orResults = [];

    // use results array to get the return value of the conditions within the bracket
    while(!empty($results) || !empty($orResults)){

      if(empty($results)) {
        if($returnValue === true){
          // we need to check anymore, because the result of true || false is true
          break;
        }
        // $orResults is not empty.
        $nextResult = array_shift($orResults);
        $returnValue = $returnValue || $nextResult;
        continue;
      }

      $operationOrNextResult = array_shift($results);

      if(is_string($operationOrNextResult)){
        $operation = $operationOrNextResult;

        if(empty($results)){
          throw new InvalidArgumentException("Malformed where statement! Last part of a condition can not be a operation.");
        }
        $nextResult = array_shift($results);

        if(!is_bool($nextResult)){
          throw new InvalidArgumentException("Malformed where statement! Two operations in a row are not allowed.");
        }
      } else if(is_bool($operationOrNextResult)){
        $operation = "AND";
        $nextResult = $operationOrNextResult;
      } else {
        throw new InvalidArgumentException("Malformed where statement! A where statement have to contain just operations and conditions.");
      }

      if(!in_array(strtolower($operation), ["and", "or"])){
        $operation = (!is_object($operation) && !is_array($operation) && !is_null($operation)) ? $operation : gettype($operation);
        throw new InvalidArgumentException("Expected 'and' or 'or' operator got \"$operation\"");
      }

      // prepare $orResults execute after all "and" are done.
      if(strtolower($operation) === "or"){
        $orResults[] = $returnValue;
        $returnValue = $nextResult;
        continue;
      }

      $returnValue = $returnValue && $nextResult;

    }

    return $returnValue;
  }

  /**
   * @param array $element
   * @param array $data
   * @return bool
   * @throws InvalidArgumentException
   * @deprecated since version 2.3. use _handleWhere instead
   */
  private function _nestedWhereHelper(array $element, array &$data): bool
  {
    // TODO remove nested where with v3.0
    // element is a where condition
    if(array_keys($element) === range(0, (count($element) - 1)) && is_string($element[0])){
      if(count($element) !== 3){
        throw new InvalidArgumentException("Where conditions have to be [fieldName, condition, value]");
      }

      $fieldValue = $this->getNestedProperty($element[0], $data);

      return $this->verifyWhereConditions($element[1], $fieldValue, $element[2]);
    }

    // element is an array "brackets"

    // prepare results array - example: [true, "and", false]
    $results = [];
    foreach ($element as $value){
      if(is_array($value)){
        $results[] = $this->_nestedWhereHelper($value, $data);
      } else if (is_string($value)){
        $results[] = $value;
      } else {
        $value = (!is_object($value) && !is_array($value)) ? $value : gettype($value);
        throw new InvalidArgumentException("Invalid nested where statement element! Expected condition or operation, got: \"$value\"");
      }
    }

    if(count($results) < 3){
      throw new InvalidArgumentException("Malformed nested where statement! A condition consists of at least 3 elements.");
    }

    // first result as default value
    $returnValue = array_shift($results);

    // use results array to get the return value of the conditions within the bracket
    while(!empty($results)){
      $operation = array_shift($results);
      $nextResult = array_shift($results);

      if(((count($results) % 2) !== 0)){
        throw new InvalidArgumentException("Malformed nested where statement!");
      }

      if(!is_string($operation) || !in_array(strtolower($operation), ["and", "or"])){
        $operation = (!is_object($operation) && !is_array($operation)) ? $operation : gettype($operation);
        throw new InvalidArgumentException("Expected 'and' or 'or' operator got \"$operation\"");
      }

      if(strtolower($operation) === "and"){
        $returnValue = $returnValue && $nextResult;
      } else {
        $returnValue = $returnValue || $nextResult;
      }
    }

    return $returnValue;
  }



  /**
   * @param array $data
   * @param bool $storePassed
   * @return bool
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   * @deprecated since version 2.3, use handleConditions instead.
   */
  private function handleNestedWhere(array $data, bool $storePassed): bool
  {
    // TODO remove nested where with v3.0
    $nestedWhere = $this->getQueryBuilderProperty("nestedWhere");

    if(empty($nestedWhere)){
      return $storePassed;
    }

    // the outermost operation specify how the given conditions are connected with other conditions,
    // like the ones that are specified using the where, orWhere, in or notIn methods
    $outerMostOperation = (array_keys($nestedWhere))[0];
    $nestedConditions = $nestedWhere[$outerMostOperation];

    // specifying outermost is optional and defaults to "and"
    $outerMostOperation = (is_string($outerMostOperation)) ? strtolower($outerMostOperation) : "and";

    // if the document already passed the store with another condition, we dont need to check it.
    if($outerMostOperation === "or" && $storePassed === true){
      return true;
    }

    return $this->_nestedWhereHelper($nestedConditions, $data);
  }

  /**
   * @param array $found
   * @throws InvalidPropertyAccessException
   */
  private function excludeFields(array &$found){
    $fieldsToExclude = $this->getQueryBuilderProperty("fieldsToExclude");
    if (!empty($fieldsToExclude) && count($fieldsToExclude) > 0) {
      foreach ($found as $key => $item) {
        foreach ($fieldsToExclude as $fieldToExclude) {
          if (array_key_exists($fieldToExclude, $item)) {
            unset($item[$fieldToExclude]);
          }
        }
        $found[$key] = $item;
      }
    }
  }

  /**
   * @param array $found
   * @throws InvalidPropertyAccessException
   */
  private function selectFields(array &$found){

    $primaryKey = $this->primaryKey;

    $fieldsToSelect = $this->getQueryBuilderProperty("fieldsToSelect");
    if (!empty($fieldsToSelect) && count($fieldsToSelect) > 0) {
      foreach ($found as $key => $item) {
        $newItem = [];
        $newItem[$primaryKey] = $item[$primaryKey];
        foreach ($fieldsToSelect as $fieldToSelect) {
          if (array_key_exists($fieldToSelect, $item)) {
            $newItem[$fieldToSelect] = $item[$fieldToSelect];
          }
        }
        $found[$key] = $newItem;
      }
    }
  }

  /**
   * @param array $found
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   */
  private function sort(array &$found){
    $orderBy = $this->getQueryBuilderProperty("orderBy");
    if (!empty($orderBy)) {

      $resultSortArray = [];

      foreach ($orderBy as $orderByClause){
        // Start sorting on all data.
        $order = $orderByClause['order'];
        $fieldName = $orderByClause['fieldName'];

        $arrayColumn = [];
        // Get value of the target field.
        foreach ($found as $value) {
          $arrayColumn[] = $this->getNestedProperty($fieldName, $value);
        }

        $resultSortArray[] = $arrayColumn;

        // Decide the order direction.
        // order will be asc or desc (check is done in QueryBuilder class)
        $resultSortArray[] = ($order === 'asc') ? SORT_ASC : SORT_DESC;

      }

      if(!empty($resultSortArray)){
        $resultSortArray[] = &$found;
        array_multisort(...$resultSortArray);
      }
      unset($resultSortArray);
    }
  }

  /**
   * Get nested properties of a store object.
   * @param string $fieldName
   * @param array $data
   * @return mixed
   * @throws InvalidArgumentException
   */
  private function getNestedProperty(string $fieldName, array $data)
  {

    $fieldName = trim($fieldName);
    if (empty($fieldName)) {
      throw new InvalidArgumentException('fieldName is not allowed to be empty');
    }

    // Dive deep step by step.
    foreach (explode('.', $fieldName) as $i) {

      // If the field does not exists we return null;
      if (!isset($data[$i])) {
        return null;
      }

      // The index is valid, collect the data.
      $data = $data[$i];
    }

    return $data;
  }

  /**
   * Do a search in store objects. This is like a doing a full-text search.
   * @param array $data
   * @return array
   * @throws InvalidPropertyAccessException
   */
  private function performSearch(array $data = []): array
  {

    // TODO apply custom key -> search rank, so the user can use that in order by!

    $searchKeyword = $this->getQueryBuilderProperty("searchKeyword");
    if (empty($data)) {
      return $data;
    }
    $nodesRank = [];
    // Looping on each store data.
    foreach ($data as $key => $value) {
      // Looping on each field name of search-able fields.
      if(!is_array($searchKeyword)) {
        break;
      }
      foreach ($searchKeyword['field'] as $field) {
        try {
          $nodeValue = $this->getNestedProperty($field, $value);
          // The searchable field was found, do comparison against search keyword.
          $percent = 0;
          if(is_string($nodeValue)){
            similar_text(strtolower($nodeValue), strtolower($searchKeyword['keyword']), $percent);
          }
          if ($percent > 50) {
            // Check if current store object already has a value, if so then add the new value.
            if (isset($nodesRank[$key])) {
              $nodesRank[$key] += $percent;
            } else {
              $nodesRank[$key] = $percent;
            }
          }
        } catch (Exception $e) {
          continue;
        }
      }
    }
    if (empty($nodesRank)) {
      // No matched store was found against the search keyword.
      return [];
    }
    // Sort nodes in descending order by the rank.
    arsort($nodesRank);
    // Map original nodes by the rank.
    $nodes = [];
    foreach ($nodesRank as $key => $value) {
      $nodes[] = $data[$key];
    }
    return $nodes;
  }

  /**
   * @param string $path
   * @throws IOException
   */
  private function _checkWrite(string $path)
  {
    // Check if PHP has write permission
    if (!is_writable($path)) {
      throw new IOException(
        "Document or directory is not writable at \"$path\". Please change permission."
      );
    }
  }

  /**
   * @param string $path
   * @throws IOException
   */
  private function _checkRead(string $path)
  {
    // Check if PHP has read permission
    if (!is_readable($path)) {
      throw new IOException(
        "Document or directory is not readable at \"$path\". Please change permission."
      );
    }
  }

  /**
   * @return string
   */
  private function _getStorePath(): string
  {
    return $this->storePath;
  }

  /**
   * Returns path to location of content
   * @return string
   */
  private function _getStoreDataPath(): string
  {
    return $this->_getStorePath().'data/';
  }

  /**
   * Returns a reference to the array used for cache token generation
   * @return array
   */
  public function &_getCacheTokenArray(): array
  {
    return $this->cacheTokenArray;
  }

  /**
   * @return mixed
   */
  private function _getCacheLifeTime()
  {
    try{
      return $this->getQueryBuilderProperty('cacheLifetime');
    } catch (InvalidPropertyAccessException $exception){
      return null;
    }
  }

  /**
   * @param array $tokenUpdate
   */
  private function updateCacheTokenArray(array $tokenUpdate)
  {
    if(empty($tokenUpdate)) {
      return;
    }

    $cacheTokenArray = $this->_getCacheTokenArray();

    foreach ($tokenUpdate as $key => $value){
      $cacheTokenArray[$key] = $value;
    }

    $this->cacheTokenArray = $cacheTokenArray;
  }

}