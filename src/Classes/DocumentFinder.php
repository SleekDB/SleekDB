<?php


namespace SleekDB\Classes;


use Exception;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;
use SleekDB\Query;
use SleekDB\Store;

/**
 * Class DocumentFinder
 * Find documents
 */
class DocumentFinder
{
  protected $storePath;
  protected $queryBuilderProperties;
  protected $primaryKey;

  public function __construct(string $storePath, array $queryBuilderProperties, string $primaryKey)
  {
    $this->storePath = $storePath;
    $this->queryBuilderProperties = $queryBuilderProperties;
    $this->primaryKey = $primaryKey;
  }

  /**
   * @param bool $getOneDocument
   * @param bool $reduceAndJoinPossible
   * @return array
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function findDocuments(bool $getOneDocument, bool $reduceAndJoinPossible): array
  {
    $queryBuilderProperties = $this->queryBuilderProperties;
    $dataPath = $this->getDataPath();
    $primaryKey = $this->primaryKey;

    $found = [];
    // Start collecting and filtering data.
    IoHelper::checkRead($dataPath);

    $conditions = $queryBuilderProperties["whereConditions"];
    $distinctFields = $queryBuilderProperties["distinctFields"];
    $nestedWhereConditions = $queryBuilderProperties["nestedWhere"];
    $listOfJoins = $queryBuilderProperties["listOfJoins"];
    $search = $queryBuilderProperties["search"];
    $searchOptions = $queryBuilderProperties["searchOptions"];
    $groupBy = $queryBuilderProperties["groupBy"];
    $havingConditions = $queryBuilderProperties["havingConditions"];
    $fieldsToSelect = $queryBuilderProperties["fieldsToSelect"];
    $orderBy = $queryBuilderProperties["orderBy"];
    $skip = $queryBuilderProperties["skip"];
    $limit = $queryBuilderProperties["limit"];
    $fieldsToExclude = $queryBuilderProperties["fieldsToExclude"];

    unset($queryBuilderProperties);

    if ($handle = opendir($dataPath)) {

      while (false !== ($entry = readdir($handle))) {

        if ($entry === "." || $entry === "..") {
          continue;
        }

        $documentPath = $dataPath . $entry;

        try{
          $data = IoHelper::getFileContent($documentPath);
        } catch (Exception $exception){
          continue;
        }
        $data = @json_decode($data, true);
        if (!is_array($data)) {
          continue;
        }

        $storePassed = true;

        // Append only passed data from this store.

        // Process conditions
        if(!empty($conditions)) {
          // Iterate each conditions.
          $storePassed = ConditionsHandler::handleWhereConditions($conditions, $data);
        }

        // TODO remove nested where with version 3.0
        $storePassed = ConditionsHandler::handleNestedWhere($data, $storePassed, $nestedWhereConditions);

        if ($storePassed === true && count($distinctFields) > 0) {
          $storePassed = ConditionsHandler::handleDistinct($found, $data, $distinctFields);
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

    if($reduceAndJoinPossible === true){
      DocumentReducer::joinData($found, $listOfJoins);
    }

    if (count($found) > 0) {
      self::performSearch($found, $search, $searchOptions);
    }

    if ($reduceAndJoinPossible === true && !empty($groupBy) && count($found) > 0) {

      DocumentReducer::handleGroupBy(
        $found,
        $groupBy,
        $fieldsToSelect
      );
    }

    if($reduceAndJoinPossible === true && empty($groupBy) && count($found) > 0){
      // select specific fields
      DocumentReducer::selectFields($found, $primaryKey, $fieldsToSelect);
    }

    if(count($found) > 0){
      self::handleHaving($found, $havingConditions);
    }

    if($reduceAndJoinPossible === true && count($found) > 0){
      // exclude specific fields
      DocumentReducer::excludeFields($found, $fieldsToExclude);
    }

    if(count($found) > 0){
      // sort the data.
      self::sort($found, $orderBy);
    }


    if(count($found) > 0) {
      // Skip data
      self::skip($found, $skip);
    }

    if(count($found) > 0) {
      // Limit data.
      self::limit($found, $limit);
    }

    return $found;
  }

  /**
   * @return string
   */
  private function getDataPath(): string
  {
    return $this->storePath . Store::dataDirectory;
  }

  /**
   * @param array $found
   * @param array $orderBy
   * @throws InvalidArgumentException
   */
  private static function sort(array &$found, array $orderBy){
    if (!empty($orderBy)) {

      $resultSortArray = [];

      foreach ($orderBy as $orderByClause){
        // Start sorting on all data.
        $order = $orderByClause['order'];
        $fieldName = $orderByClause['fieldName'];

        $arrayColumn = [];
        // Get value of the target field.
        foreach ($found as $value) {
          $arrayColumn[] = NestedHelper::getNestedValue($fieldName, $value);
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
   * @param array $found
   * @param $skip
   */
  private static function skip(array &$found, $skip){
    if (empty($skip) || $skip <= 0) {
      return;
    }
    $found = array_slice($found, $skip);
  }

  /**
   * @param array $found
   * @param $limit
   */
  private static function limit(array &$found, $limit){
    if (empty($limit) || $limit <= 0) {
      return;
    }
    $found = array_slice($found, 0, $limit);
  }

  /**
   * Do a search in store objects. This is like a doing a full-text search.
   * @param array $found
   * @param array $search
   * @param array $searchOptions
   * @throws InvalidArgumentException
   */
  private static function performSearch(array &$found, array $search, array $searchOptions)
  {
    if(empty($search)){
      return;
    }
    $minLength = $searchOptions["minLength"];
    $searchScoreKey = $searchOptions["scoreKey"];
    $searchMode = $searchOptions["mode"];
    $searchAlgorithm = $searchOptions["algorithm"];

    $scoreMultiplier = 64;
    $encoding = "UTF-8";

    $fields = $search["fields"];
    $query = $search["query"];
    $lowerQuery = mb_strtolower($query, $encoding);
    $exactQuery  = preg_quote($query, "/");

    $fieldsLength = count($fields);

    $highestScore = $scoreMultiplier ** $fieldsLength;

    // split query
    $searchWords = preg_replace('/(\s)/u', ',', $query);
    $searchWords = explode(",", $searchWords);

    $prioritizeAlgorithm = (in_array($searchAlgorithm, [
      Query::SEARCH_ALGORITHM["prioritize"],
      Query::SEARCH_ALGORITHM["prioritize_position"]
    ], true));

    $positionAlgorithm = ($searchAlgorithm === Query::SEARCH_ALGORITHM["prioritize_position"]);

    // apply min word length
    $temp = [];
    foreach ($searchWords as $searchWord){
      if(strlen($searchWord) >= $minLength){
        $temp[] = $searchWord;
      }
    }
    $searchWords = $temp;
    unset($temp);
    $searchWords = array_map(static function($value){
      return preg_quote($value, "/");
    }, $searchWords);

    // apply mode
    if($searchMode === "and"){
      $preg = "";
      foreach ($searchWords as $searchWord){
        $preg .= "(?=.*".$searchWord.")";
      }
      $preg = '/^' . $preg . '.*/im';
      $pregOr = '!(' . implode('|', $searchWords) . ')!i';
    } else {
      $preg = '!(' . implode('|', $searchWords) . ')!i';
    }

    // search
    foreach ($found as $foundKey => &$document) {
      $searchHits = 0;
      $searchScore = 0;
      foreach ($fields as $key => $field) {
        if($prioritizeAlgorithm){
          $score = $highestScore / ($scoreMultiplier ** $key);
        } else {
          $score = $scoreMultiplier;
        }
        $value = NestedHelper::getNestedValue($field, $document);

        if (!is_string($value) || $value === "") {
          continue;
        }

        $lowerValue = mb_strtolower($value, $encoding);

        if ($lowerQuery === $lowerValue) {
          // exact match
          $searchHits++;
          $searchScore += 16 * $score;
        } elseif ($positionAlgorithm && mb_strpos($lowerValue, $lowerQuery, 0, $encoding) === 0) {
          // exact beginning match
          $searchHits++;
          $searchScore += 8 * $score;
        } elseif ($matches = preg_match_all('!' . $exactQuery . '!i', $value)) {
          // exact query match
          $searchHits += $matches;
//          $searchScore += 2 * $score;
          $searchScore += $matches * 2 * $score;
          if($searchAlgorithm === Query::SEARCH_ALGORITHM["hits_prioritize"]){
            $searchScore += $matches * ($fieldsLength - $key);
          }
        }

        $matchesArray = [];

        $matches = ($searchMode === "and") ? preg_match($preg, $value) : preg_match_all($preg, $value, $matchesArray, PREG_OFFSET_CAPTURE);

        if ($matches) {
          // any match
          $searchHits += $matches;
          $searchScore += $matches * $score;
          if($searchAlgorithm === Query::SEARCH_ALGORITHM["hits_prioritize"]) {
            $searchScore += $matches * ($fieldsLength - $key);
          }
          // because the "and" search algorithm at most finds one match we also use the amount of word occurrences
          if($searchMode === "and" && isset($pregOr) && ($matches = preg_match_all($pregOr, $value, $matchesArray, PREG_OFFSET_CAPTURE))){
            $searchHits += $matches;
            $searchScore += $matches * $score;
          }
        }

        // we apply a small very small number to the score to differentiate the distance from the beginning
        if($positionAlgorithm && $matches && !empty($matchesArray)){
          $hitPosition = $matchesArray[0][0][1];
          if(!is_int($hitPosition) || !($hitPosition > 0)){
            $hitPosition = 1;
          }
          $searchScore += ($score / $highestScore) * ($hitPosition / ($hitPosition * $hitPosition));
        }
      }

      if($searchHits > 0){
        if(!is_null($searchScoreKey)){
          $document[$searchScoreKey] = $searchScore;
        }
      } else {
        unset($found[$foundKey]);
      }
    }
  }

  /**
   * @param array $found
   * @param array $havingConditions
   * @throws InvalidArgumentException
   */
  private static function handleHaving(array &$found, array $havingConditions){
    if(empty($havingConditions)){
      return;
    }

    foreach ($found as $key => $document){
      if(false === ConditionsHandler::handleWhereConditions($havingConditions, $document)){
        unset($found[$key]);
      }
    }
  }

}