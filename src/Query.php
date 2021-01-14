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

  protected $conditions;

  protected $dataDirectory = "";

  /**
   * @var Cache
   */
  protected $cache;


  const DELETE_RETURN_BOOL = 1;
  const DELETE_RETURN_RESULTS = 1;
  const DELETE_RETURN_COUNT = 1;

  private $tokenAddition = [
    'oneDocument' => false
  ];

  /**
   * Query constructor.
   * @param QueryBuilder $queryBuilder
   */
  public function __construct(QueryBuilder $queryBuilder)
  {
    $store = $queryBuilder->_getStore();

    $this->storePath = $store->getStorePath();
    $this->dataDirectory = $store->getDataDirectory();

    $this->conditions = $queryBuilder->_getConditionsArray();

    $this->cache = new Cache($queryBuilder);
  }

  /**
   * @return Cache
   */
  public function getCache(): Cache
  {
    return $this->cache;
  }

  /**
   * @param string $conditionKey
   * @return mixed
   * @throws InvalidPropertyAccessException
   */
  private function getCondition(string $conditionKey){
    if(array_key_exists($conditionKey,$this->conditions)){
      return $this->conditions[$conditionKey];
    }
    throw new InvalidPropertyAccessException("Tried to access condition \"$conditionKey\" which is not specified in QueryBuilder as property");
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
    $results = $this->getCacheContent();

    if(!empty($results)) return $results;

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
   * Get results from cache
   * @param bool $getOneDocument
   * @return array|null
   * @throws IOException
   * @throws InvalidPropertyAccessException
   */
  private function getCacheContent(bool $getOneDocument = false)
  {
    $useCache = $this->getCondition("useCache");
    $regenerateCache = $this->getCondition("regenerateCache");

    $tokenAddition = $this->tokenAddition;

    if($useCache === true){
      $cache = $this->getCache();

      if($getOneDocument === true) $tokenAddition['oneDocument'] = true;

      $cache->setTokenAddition($tokenAddition);

      if($regenerateCache === true) $cache->delete();

      $cacheResults = $cache->get();

      $cache->removeTokenAddition();

      if(is_array($cacheResults)) return $cacheResults;
    }
    return null;
  }

  /**
   * Add content to cache
   * @param array $results
   * @param bool $isOneDocument
   * @throws IOException
   * @throws InvalidPropertyAccessException
   */
  private function setCacheContent(array $results, bool $isOneDocument = false)
  {
    $useCache = $this->getCondition("useCache");
    $tokenAddition = $this->tokenAddition;
    if($useCache === true){
      $cache = $this->getCache();
      if($isOneDocument === true) $tokenAddition['oneDocument'] = true;
      $cache->setTokenAddition($tokenAddition);
      $cache->set($results);
      $cache->removeTokenAddition();
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
    $listOfJoins = $this->getCondition("listOfJoins");
    foreach ($results as $key => $doc) {
      foreach ($listOfJoins as $join) {
        // Execute the child query.
        $joinQuery = ($join['relation'])($doc); // QueryBuilder or result of fetch
        $keyName = $join['name'] ? $join['name'] : $joinQuery->storeName;

        // TODO remove SleekDB check in version 2.0
        if($joinQuery instanceof QueryBuilder || $joinQuery instanceof SleekDB){
          $joinResult = $joinQuery->getQuery()->fetch();
        } else if(is_array($joinQuery)){
          // user already fetched the query in the join query function
          $joinResult = $joinQuery;
        } else {
          throw new InvalidArgumentException("Invalid join query");
        }

        // TODO discuss if that is a good idea -> would be inconsistent
        //  if(count($joinResult) === 1) $joinResult = $joinResult[0];

        // Add child documents with the current document.
        $results[$key][$keyName] = $joinResult;
      }
    }
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
    $results = $this->getCacheContent(true);
    if(!empty($results)) return $results;

    $results = $this->findStoreDocuments(true);

    $this->joinData($results);

    if (count($results) > 0) {
      list($item) = $results;
      $results = $item;
    }

    $this->setCacheContent($results, true);

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
    foreach ($results as $data) {
      foreach ($updatable as $key => $value) {
        // Do not update the _id reserved index of a store.
        if ($key != '_id') {
          $data[$key] = $value;
        }
      }
      $storePath = $this->getStorePath() . 'data/' . $data['_id'] . '.json';
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
        $filePath = $this->getStorePath() . 'data/' . $data['_id'] . '.json';
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
   * @param string $condition
   * @param mixed $fieldValue value of current field
   * @param mixed $value value to check
   * @return bool
   * @throws InvalidArgumentException
   */
  private function verifyWhereConditions(string $condition, $fieldValue, $value): bool
  {
    switch (strtolower(trim($condition))){
      case "=":
        return ($fieldValue == $value);
      case "!=":
        return ($fieldValue != $value);
      case ">":
        return ($fieldValue > $value);
      case ">=":
        return ($fieldValue >= $value);
      case "<":
        return ($fieldValue < $value);
      case "<=":
        return ($fieldValue <= $value);
      case "like":

        // escape characters that are part of regular expression syntax
        // https://www.php.net/manual/en/function.preg-quote.php
        // We can not use preg_quote because the following characters are also wildcard characters in sql
        // so we will not escape them: [ ^ ] -
        $charactersToEscape = [".", "\\", "+", "*", "?", "$", "(", ")", "{", "}", "=", "!", "<", ">", "|", ":",  "#"];
        foreach ($charactersToEscape as $characterToEscape){
          $value = str_replace($characterToEscape, "\\".$characterToEscape, $value); // zero or more characters
        }

        $value = str_replace('%', '.*', $value); // zero or more characters
        $value = str_replace('_', '.{1}', $value); // single character
        $pattern = "/^" . $value . "$/i";
        return (preg_match($pattern, $fieldValue) === 1);

      default:
        throw new InvalidArgumentException("Condition \"$condition\" is not allowed");
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
    $storeDataPath = $this->getStorePath() . 'data/';
    $this->_checkRead($storeDataPath);
    if ($handle = opendir($storeDataPath)) {

      while (false !== ($entry = readdir($handle))) {

        if ($entry == "." || $entry == "..") {
          continue;
        }

        $documentPath = $storeDataPath . $entry;

        $this->_checkRead($documentPath);

        $data = @json_decode(@file_get_contents($documentPath), true); // get document by path

        if (empty($data)) {
          continue;
        }

        $storePassed = true;

        // Append only passed data from this store.

        // Where conditions
        $conditions = $this->getCondition("conditions");
        if(!empty($conditions)) {
          // Iterate each conditions.
          foreach ($conditions as $condition) {
            // Check for valid data from data source.
            try {
              $fieldValue = $this->getNestedProperty($condition['fieldName'], $data);
            } catch (Exception $e) {
              $storePassed = false;
              break;
            }
            $storePassed = $this->verifyWhereConditions($condition['condition'], $fieldValue, $condition['value']);
            if ($storePassed === false) break;
          }
        }

        // where [] or ([] and [] and []) or ([] and [] and [])
        // two dimensional array. first dimension is "or" between each condition, second is "and".
        $orConditions = $this->getCondition("orConditions");
        if ($storePassed === false && !empty($orConditions)) {
          // Check if one condition will allow this document.
          foreach ($orConditions as $conditionsWithAndBetween) { // () or ()
            // Check if a all conditions will allow this document.
            foreach ($conditionsWithAndBetween as $condition){ // () and ()
              try {
                $fieldValue = $this->getNestedProperty($condition['fieldName'], $data);
              } catch (Exception $e) {
                $storePassed = false;
                break;
              }
              $storePassed = $this->verifyWhereConditions($condition['condition'], $fieldValue, $condition['value']);
              if ($storePassed === true) continue;
              break; // one where was false
            }

            // one condition block was true, that means that we dont have to look into the other conditions
            if($storePassed === true) break;
          }
        }

        // IN clause.
        $in = $this->getCondition("in");
        if ($storePassed === true && !empty($in)) {
          foreach ($in as $inClause) {
            try {
              $fieldValue = $this->getNestedProperty($inClause['fieldName'], $data);
            } catch (Exception $e) {
              $storePassed = false;
              break;
            }
            if (!in_array($fieldValue, $inClause['value'])) {
              $storePassed = false;
              break;
            }
          }
        }

        // notIn clause.
        $notIn = $this->getCondition("notIn");
        if ($storePassed === true && !empty($notIn)) {
          foreach ($notIn as $notInClause) {
            try {
              $fieldValue = $this->getNestedProperty($notInClause['fieldName'], $data);
            } catch (Exception $e) {
              break;
            }
            if (in_array($fieldValue, $notInClause['value'])) {
              $storePassed = false;
              break;
            }
          }
        }

        // Distinct data check.
        $distinctFields = $this->getCondition("distinctFields");
        if ($storePassed === true && count($distinctFields) > 0) {
          foreach ($found as $result) {
            foreach ($distinctFields as $field) {
              try {
                $storePassed = ($this->getNestedProperty($field, $result) !== $this->getNestedProperty($field, $data));
              } catch (Throwable $th) {
                continue;
              }
              if ($storePassed === false) break;
            }
            if ($storePassed === false) break;
          }
        }

        if ($storePassed === true) {
          $found[] = $data;

          // if we just check for existence or want to return the first item, we dont need to look for more documents
          if ($getOneDocument === true) break;
        }
      }
      closedir($handle);
    }

    if (count($found) > 0) {
      // Check do we need to sort the data.
      $orderBy = $this->getCondition("orderBy");
      if ($orderBy['order'] !== false) {
        // Start sorting on all data.
        $found = $this->sortArray($orderBy['field'], $found, $orderBy['order']);
      }

      // If there was text search then we would also sort the result by search ranking.
      $searchKeyword = $this->getCondition("searchKeyword");
      if (!empty($searchKeyword)) {
        $found = $this->performSearch($found);
      }

      // Skip data
      $skip = $this->getCondition("skip");
      if (!empty($skip) && $skip > 0) $found = array_slice($found, $skip);

      // Limit data.
      $limit = $this->getCondition("limit");
      if (!empty($limit) && $limit > 0) $found = array_slice($found, 0, $limit);

      $fieldsToSelect = $this->getCondition("fieldsToSelect");
      if (!empty($fieldsToSelect) && count($fieldsToSelect) > 0) {
        $found = $this->applyFieldsToSelect($found);
      }

      $fieldsToExclude = $this->getCondition("fieldsToExclude");
      if (count($fieldsToExclude) > 0) {
        $found = $this->applyFieldsToExclude($found);
      }
    }

    return $found;
  }

  /**
   * @param array $found
   * @return array
   * @throws InvalidPropertyAccessException
   */
  private function applyFieldsToSelect(array $found): array
  {
    $fieldsToSelect = $this->getCondition("fieldsToSelect");
    if (!(count($found) > 0) || !(count($fieldsToSelect) > 0)) {
      return $found;
    }
    foreach ($found as $key => $item) {
      $newItem = [];
      $newItem['_id'] = $item['_id'];
      foreach ($fieldsToSelect as $fieldToSelect) {
        if (array_key_exists($fieldToSelect, $item)) {
          $newItem[$fieldToSelect] = $item[$fieldToSelect];
        }
      }
      $found[$key] = $newItem;
    }
    return $found;
  }

  /**
   * @param array $found
   * @return array
   * @throws InvalidPropertyAccessException
   */
  private function applyFieldsToExclude(array $found): array
  {
    $fieldsToExclude = $this->getCondition("fieldsToExclude");
    if (!(count($found) > 0) || !(count($fieldsToExclude) > 0)) {
      return $found;
    }
    foreach ($found as $key => $item) {
      foreach ($fieldsToExclude as $fieldToExclude) {
        if (array_key_exists($fieldToExclude, $item)) {
          unset($item[$fieldToExclude]);
        }
      }
      $found[$key] = $item;
    }
    return $found;
  }

  /**
   * Sort store objects.
   * @param string $field
   * @param array $data
   * @param string $order
   * @return array
   * @throws InvalidArgumentException
   */
  private function sortArray(string $field, array $data, string $order = 'ASC'): array
  {
    $dryData = [];
    // Get value of the target field.
    foreach ($data as $value) {
      $dryData[] = $this->getNestedProperty($field, $value);
    }
    // Decide the order direction.
    if (strtolower($order) === 'asc') asort($dryData);
    else if (strtolower($order) === 'desc') arsort($dryData);
    // Re arrange the array.
    $finalArray = [];
    foreach ($dryData as $key => $value) {
      $finalArray[] = $data[$key];
    }
    return $finalArray;
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
    if (empty($fieldName)) throw new InvalidArgumentException('fieldName is not allowed to be empty');

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
    $searchKeyword = $this->getCondition("searchKeyword");
    if (empty($data)) return $data;
    $nodesRank = [];
    // Looping on each store data.
    foreach ($data as $key => $value) {
      // Looping on each field name of search-able fields.
      if(!is_array($searchKeyword)) break;
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
            if (isset($nodesRank[$key])) $nodesRank[$key] += $percent;
            else $nodesRank[$key] = $percent;
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
  private function getStorePath(): string
  {
    return $this->storePath;
  }
}