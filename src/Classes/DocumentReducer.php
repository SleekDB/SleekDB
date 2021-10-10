<?php


namespace SleekDB\Classes;


use Closure;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;
use SleekDB\QueryBuilder;
use SleekDB\SleekDB;

/**
 * Class DocumentReducer
 * Alters one or multiple documents
 */
class DocumentReducer
{

  const SELECT_FUNCTIONS = [
    "AVG" => "avg",
    "MAX" => "max",
    "MIN" => "min",
    "SUM" => "sum",
    "ROUND" => "round",
    "ABS" => "abs",
    "POSITION" => "position",
    "UPPER" => "upper",
    "LOWER" => "lower",
    "LENGTH" => "length",
    "CONCAT" => "concat",
    "CUSTOM" => "custom",
  ];

  const SELECT_FUNCTIONS_THAT_REDUCE_RESULT = [
    "AVG" => "avg",
    "MAX" => "max",
    "MIN" => "min",
    "SUM" => "sum"
  ];

  /**
   * @param array $found
   * @param array $fieldsToExclude
   */
  public static  function excludeFields(array &$found, array $fieldsToExclude){
    if (empty($fieldsToExclude)) {
      return;
    }
    foreach ($found as $key => &$document) {
      if(!is_array($document)){
        continue;
      }
      foreach ($fieldsToExclude as $fieldToExclude) {
        NestedHelper::removeNestedField($document, $fieldToExclude);
      }
    }
  }

  /**
   * @param array $results
   * @param array $listOfJoins
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public static function joinData(array &$results, array $listOfJoins){
    if(empty($listOfJoins)){
      return;
    }
    // Join data.
    foreach ($results as $key => $doc) {
      foreach ($listOfJoins as $join) {
        // Execute the child query.
        $joinQuery = ($join['joinFunction'])($doc); // QueryBuilder or result of fetch
        $propertyName = $join['propertyName'];

        // TODO remove SleekDB check in version 3.0
        if($joinQuery instanceof QueryBuilder || $joinQuery instanceof SleekDB){
          $joinResult = $joinQuery->getQuery()->fetch();
        } else if(is_array($joinQuery)){
          // user already fetched the query in the join query function
          $joinResult = $joinQuery;
        } else {
          throw new InvalidArgumentException("Invalid join query.");
        }

        // Add child documents with the current document.
        $results[$key][$propertyName] = $joinResult;
      }
    }
  }

  /**
   * @param array $found
   * @param array $groupBy
   * @param array $fieldsToSelect
   * @throws InvalidArgumentException
   */
  public static function handleGroupBy(array &$found, array $groupBy, array $fieldsToSelect)
  {
    if(empty($groupBy)){
      return;
    }
    // TODO optimize algorithm if possible
    $groupByFields = $groupBy["groupByFields"];
    $countKeyName = $groupBy["countKeyName"];
    $allowEmpty = $groupBy["allowEmpty"];

    $hasSelectFunctionThatNotReduceResult = false;
    $hasSelectFunctionThatReduceResult = false;

    $pattern = (!empty($fieldsToSelect))? $fieldsToSelect : $groupByFields;

    if(!empty($countKeyName) && empty($fieldsToSelect)){
      $pattern[] = $countKeyName;
    }

    // remove duplicates
    $patternWithOutDuplicates = [];
    foreach ($pattern as $key => $value){
      if(array_key_exists($key, $patternWithOutDuplicates) && in_array($value, $patternWithOutDuplicates, true)){
        continue;
      }
      $patternWithOutDuplicates[$key] = $value;

      // validate pattern
      if(!is_string($key) && !is_string($value)){
        throw new InvalidArgumentException("You need to format the select correctly when using Group By.");
      }
      if(!is_string($value)) {

        if($value instanceof Closure){
          if($hasSelectFunctionThatNotReduceResult === false){
            $hasSelectFunctionThatNotReduceResult = true;
          }
          if(!in_array($key, $groupByFields, true)){ // key is fieldAlias
            throw new InvalidArgumentException("You can not select a field \"$key\" that is not grouped by.");
          }
          continue;
        }

        if (!is_array($value) || empty($value)) {
          throw new InvalidArgumentException("You need to format the select correctly when using Group By.");
        }

        list($function) = array_keys($value);
        $functionParameters = $value[$function];
        self::getFieldNamesOfSelectFunction($function, $functionParameters);

        if(is_string($function) ){
          if(!in_array(strtolower($function), self::SELECT_FUNCTIONS_THAT_REDUCE_RESULT)){
            if($hasSelectFunctionThatNotReduceResult === false){
              $hasSelectFunctionThatNotReduceResult = true;
            }
            if(!in_array($key, $groupByFields, true)){ // key is fieldAlias
              throw new InvalidArgumentException("You can not select a field \"$key\" that is not grouped by.");
            }
          } else if($hasSelectFunctionThatReduceResult === false){
            $hasSelectFunctionThatReduceResult = true;
          }
        }
      } else if($value !== $countKeyName && !in_array($value, $groupByFields, true)) {
        throw new InvalidArgumentException("You can not select a field that is not grouped by.");
      }
    }
    $pattern = $patternWithOutDuplicates;
    unset($patternWithOutDuplicates);

    // Apply select functions that do not reduce result before grouping
    if($hasSelectFunctionThatNotReduceResult){
      foreach ($found as &$document){
        foreach ($pattern as $key => $value){
          if(is_array($value)){
            list($function) = array_keys($value);
            $functionParameters = $value[$function];
            if(in_array(strtolower($function), self::SELECT_FUNCTIONS_THAT_REDUCE_RESULT)){
              continue;
            }

            $document[$key] = self::handleSelectFunction($function, $document, $functionParameters);
          } else if($value instanceof Closure){
            $function = self::SELECT_FUNCTIONS['CUSTOM'];
            $functionParameters = $value;
            $document[$key] = self::handleSelectFunction($function, $document, $functionParameters);
          }
        }
      }
      unset($document);
    }

    // GROUP
    $groupedResult = [];
    foreach ($found as $foundKey => $document){

      // Prepare hash for group by
      $values = [];
      $isEmptyAndEmptyNotAllowed = false;
      foreach ($groupByFields as $groupByField){
        $value = NestedHelper::getNestedValue($groupByField, $document);
        if($allowEmpty === false && is_null($value)){
          $isEmptyAndEmptyNotAllowed = true;
          break;
        }
        $values[$groupByField] = $value;
      }
      if($isEmptyAndEmptyNotAllowed === true){
        continue;
      }
      $valueHash = md5(json_encode($values));

      // is new entry
      if(!array_key_exists($valueHash, $groupedResult)){
        $resultDocument = [];
        foreach ($pattern as $key => $patternValue){
          $resultFieldName = (is_string($key)) ? $key : $patternValue;

          if($resultFieldName === $countKeyName){
            // is a counter
            $attributeValue = 1;
          } else if(!is_string($patternValue)){
            // is a function
            list($function) = array_keys($patternValue);
            if(in_array(strtolower($function), self::SELECT_FUNCTIONS_THAT_REDUCE_RESULT)){
              // is a select function that reduce result.
              $fieldNameToHandle = $patternValue[$function];
              $currentFieldValue = NestedHelper::getNestedValue($fieldNameToHandle, $document);
              if(!is_numeric($currentFieldValue)){
                $attributeValue = [$function => [null]];
              } else {
                $attributeValue = [$function => [$currentFieldValue]];
              }
            } else {
              // is a select function that does not reduce result.
              $attributeValue = $document[$resultFieldName];
            }
          } else {
            // is a normal select
            $attributeValue = NestedHelper::getNestedValue($patternValue, $document);
          }
          $resultDocument[$resultFieldName] = $attributeValue;
        }
        $groupedResult[$valueHash] = $resultDocument;
        continue;
      }

      // entry exists
      $currentResult = $groupedResult[$valueHash];
      foreach ($pattern as $key => $patternValue){
        $resultFieldName = (is_string($key)) ? $key : $patternValue;

        if($resultFieldName === $countKeyName){
          $currentResult[$resultFieldName] += 1;
          continue;
        }

        if(is_array($patternValue)){
          list($function) = array_keys($patternValue);
          if(in_array(strtolower($function), self::SELECT_FUNCTIONS_THAT_REDUCE_RESULT)){
            $fieldNameToHandle = $patternValue[$function];
            $currentFieldValue = NestedHelper::getNestedValue($fieldNameToHandle, $document);
            $currentFieldValue = is_numeric($currentFieldValue) ? $currentFieldValue : null;
            $currentResult[$resultFieldName][$function][] = $currentFieldValue;
          }
        }
      }
      $groupedResult[$valueHash] = $currentResult;
    }

    // Apply select functions that reduce result
    if($hasSelectFunctionThatReduceResult){
      foreach ($groupedResult as &$document){
        foreach ($pattern as $key => $value){
          if(!is_array($value)){
            continue;
          }
          list($function) = array_keys($value);
          if(!in_array(strtolower($function), self::SELECT_FUNCTIONS_THAT_REDUCE_RESULT)){
            continue;
          }
          // "price" => ["sum" => [...]]
          $functionParameters = $key.".".$function;
          $document[$key] = self::handleSelectFunction($function, $document, $functionParameters);
        }
      }
      unset($document);
    }

    $found = array_values($groupedResult);
  }

  /**
   * @param array $found
   * @param string $primaryKey
   * @param array $fieldsToSelect
   * @throws InvalidArgumentException
   */
  public static function selectFields(array &$found, string $primaryKey, array $fieldsToSelect)
  {
    if (empty($fieldsToSelect)) {
      return;
    }

    $functionsThatReduceResultToSingleResult = self::SELECT_FUNCTIONS_THAT_REDUCE_RESULT;
    $reducedResult = []; // "fieldName" => ["values",...]
    $reduceResultToSingleResult = false;

    // check if result should be reduced to single result
    foreach ($fieldsToSelect as $fieldToSelect){
      if(!is_array($fieldToSelect)){
        continue;
      }

      list($function) = array_keys($fieldToSelect);

      if(in_array(strtolower($function), $functionsThatReduceResultToSingleResult, true)){
        $reduceResultToSingleResult = true;
      }

      if($reduceResultToSingleResult === true){
        break;
      }
    }

    // Is not result of group by and contains function that reduces result to single result
    if($reduceResultToSingleResult === true){
      foreach ($found as $key => $document) {
        foreach ($fieldsToSelect as $fieldAlias => $fieldToSelect) {
          $fieldName = (!is_int($fieldAlias))? $fieldAlias : $fieldToSelect;
          if(!is_array($fieldToSelect)){
            continue;
          }

          // no alias specified and select function (array) used as element
          if(!is_string($fieldName)){
            $errorMsg = "You need to specify an alias for the field when using select functions.";
            throw new InvalidArgumentException($errorMsg);
          }

          list($function) = array_keys($fieldToSelect);
          $functionParameters = $fieldToSelect[$function];

          if(in_array(strtolower($function), $functionsThatReduceResultToSingleResult, true)){
            if(!is_string($functionParameters)){
              $errorMsg = "When using the function \"$function\" the parameter has to be a string (fieldName).";
              throw new InvalidArgumentException($errorMsg);
            }

            $value = NestedHelper::getNestedValue($functionParameters, $document);
            if(!array_key_exists($fieldName, $reducedResult)){
              $reducedResult[$fieldName] = [];
            }
            $reducedResult[$fieldName][] = $value;
          }
        }
      }

      $newDocument = [];
      foreach ($fieldsToSelect as $fieldAlias => $fieldToSelect){
        $fieldName = (!is_int($fieldAlias))? $fieldAlias : $fieldToSelect;
        if(!is_array($fieldToSelect)){
          continue;
        }

        list($function) = array_keys($fieldToSelect);
        if(in_array(strtolower($function), $functionsThatReduceResultToSingleResult, true)){
          $newDocument[$fieldName] = self::handleSelectFunction($function, $reducedResult, $fieldName);
        }
      }
      $found = [$newDocument];
      return;
    }

    // result should not be reduced to single result

    foreach ($found as $key => &$document) {
      $newDocument = [];

      $newDocument[$primaryKey] = $document[$primaryKey];
      foreach ($fieldsToSelect as $fieldAlias => $fieldToSelect) {

        $fieldName = (!is_int($fieldAlias))? $fieldAlias : $fieldToSelect;

        if(!is_string($fieldToSelect) && !is_int($fieldToSelect) && !is_array($fieldToSelect)
          && !($fieldToSelect instanceof Closure))
        {
          $errorMsg = "When using select an array containing fieldNames as strings or select functions has to be given";
          throw new InvalidArgumentException($errorMsg);
        }

        // no alias specified and select function (array) used as element
        if(!is_string($fieldName)){
          $errorMsg = "You need to specify an alias for the field when using select functions.";
          throw new InvalidArgumentException($errorMsg);
        }

        // if the fieldToSelect is an array, the user wants to use a select function
        if(is_array($fieldToSelect)){
          // "fieldAlias" => ["function" => "field"]
          list($function) = array_keys($fieldToSelect);
          $functionParameters = $fieldToSelect[$function];
          $newDocument[$fieldName] = self::handleSelectFunction($function, $document, $functionParameters);
        } else if($fieldToSelect instanceof Closure){
          $function = self::SELECT_FUNCTIONS['CUSTOM'];
          $functionParameters = $fieldToSelect;
          $newDocument[$fieldName] = self::handleSelectFunction($function, $document, $functionParameters);
        } else {
          // No select function is used (fieldToSelect is string or int)
          $fieldValue = NestedHelper::getNestedValue((string) $fieldToSelect, $document);
          $createdArray = NestedHelper::createNestedArray($fieldName, $fieldValue);
          if(!empty($createdArray)){
            $createdArrayKey = array_keys($createdArray)[0];
            $newDocument[$createdArrayKey] = $createdArray[$createdArrayKey];
          }
        }
      }
      $document = $newDocument;
    }
  }

  /**
   * @param string $function
   * @param array $document
   * @param string|array|int|Closure $functionParameters
   * @return mixed
   * @throws InvalidArgumentException
   */
  private static function handleSelectFunction(string $function, array $document, $functionParameters){

    if(is_int($functionParameters)){
      $functionParameters = (string) $functionParameters;
    }

    switch (strtolower($function)){
      case self::SELECT_FUNCTIONS["ROUND"]:
        list($field, $precision) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        if(!is_string($field) || !is_int($precision)){
          $errorMsg = "When using the select function \"$function\" the field parameter has to be a string "
            ."and the precision parameter has to be an integer";
          throw new InvalidArgumentException($errorMsg);
        }

        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_numeric($data)){
          return null;
        }
        return round((float) $data, $precision);
      case self::SELECT_FUNCTIONS["ABS"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_numeric($data)){
          return null;
        }
        return abs($data);
      case self::SELECT_FUNCTIONS["POSITION"]:
        list($field, $subString) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        if(!is_string($subString) || !is_string($field)){
          $errorMsg = "When using the select function \"$function\" the subString and field parameters has to be strings";
          throw new InvalidArgumentException($errorMsg);
        }

        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_string($data)){
          return null;
        }
        $result = strpos($data, $subString);
        return ($result !== false)? $result + 1 : null;
      case self::SELECT_FUNCTIONS["UPPER"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_string($data)){
          return null;
        }
        return strtoupper($data);
      case self::SELECT_FUNCTIONS["LOWER"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_string($data)){
          return null;
        }
        return strtolower($data);
      case self::SELECT_FUNCTIONS["LENGTH"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(is_string($data)){
          return strlen($data);
        }
        if(is_array($data)){
          return count($data);
        }
        return null;
      case self::SELECT_FUNCTIONS["CONCAT"]:
        list($fields, $glue) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $result = "";
        foreach ($fields as $field){
          $data = NestedHelper::getNestedValue($field, $document);
          // convertible to string
          if(
            ( !is_array( $data ) )
            && ($data !== "" && $data !== null)
            && (
              ( !is_object( $data ) && settype( $data, 'string' ) !== false )
              || ( is_object( $data ) && method_exists( $data, '__toString' ) )
            )
          )
          {
            if($result !== ""){
              $result .= $glue;
            }
            $result .= $data;
          }
        }
        return ($result !== "") ? $result : null;
      case self::SELECT_FUNCTIONS["SUM"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_array($data)){
          return null;
        }

        $result = 0;
        $allEntriesNull = true;
        foreach ($data as $value){
          if(!is_null($value)){
            $result += $value;
            $allEntriesNull = false;
          }
        }
        if($allEntriesNull === true){
          return null;
        }
        return $result;
      case self::SELECT_FUNCTIONS["MIN"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_array($data)){
          return null;
        }

        $result = INF;
        $allEntriesNull = true;
        foreach ($data as $value){
          if(!is_null($value)){
            if($value < $result){
              $result = $value;
            }
            $allEntriesNull = false;
          }
        }
        if($allEntriesNull === true){
          return null;
        }
        return $result;
      case self::SELECT_FUNCTIONS["MAX"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_array($data)){
          return null;
        }

        $result = -INF;
        $allEntriesNull = true;
        foreach ($data as $value){
          if($value > $result && !is_null($value)) {
            $result = $value;
            $allEntriesNull = false;
          }
        }
        if($allEntriesNull === true){
          return null;
        }
        return $result;
      case self::SELECT_FUNCTIONS["AVG"]:
        list($field) = self::getFieldNamesOfSelectFunction($function, $functionParameters);
        $data = NestedHelper::getNestedValue($field, $document);
        if(!is_array($data)){
          return null;
        }

        $result = 0;
        $resultValueAmount = (count($data) + 1);
        $allEntriesNull = true;
        foreach ($data as $value){
          if(!is_null($value)){
            $result += $value;
            $allEntriesNull = false;
          }
        }
        if($allEntriesNull === true){
          return null;
        }
        return ($result / $resultValueAmount);
      case self::SELECT_FUNCTIONS['CUSTOM']:
        if(!($functionParameters instanceof Closure)){
          throw new InvalidArgumentException("When using a custom select function you need to provide a closure.");
        }
        return $functionParameters($document);
      default:
        throw new InvalidArgumentException("The given select function \"$function\" is not supported.");
    }
  }

  /**
   * @param string $function
   * @param string|array|int $functionParameters
   * @return array [array|string $fieldNames, $addition]
   * @throws InvalidArgumentException
   */
  private static function getFieldNamesOfSelectFunction(string $function, $functionParameters): array
  {
    if(is_int($functionParameters)){
      $functionParameters = (string) $functionParameters;
    }
    $function = strtolower($function);
    switch ($function){
      case self::SELECT_FUNCTIONS["ROUND"]:
      case self::SELECT_FUNCTIONS["POSITION"]:
        if(!is_array($functionParameters) || count($functionParameters) !== 2){
          $type = gettype($functionParameters);
          $length = (is_array($functionParameters)) ? count($functionParameters) : 0;
          $errorMsg = "When using the select function \"$function\" the parameter "
            ."has to be an array with length = 2, got $type with length $length";
          throw new InvalidArgumentException($errorMsg);
        }
        list($firstParameter, $secondParameter) = $functionParameters;
        if($function === self::SELECT_FUNCTIONS["ROUND"]){
          $field = $firstParameter;
          $addition = $secondParameter;
        } else {
          $field = $secondParameter;
          $addition = $firstParameter;
        }
        return [$field, $addition];
      case self::SELECT_FUNCTIONS["ABS"]:
      case self::SELECT_FUNCTIONS["UPPER"]:
      case self::SELECT_FUNCTIONS["LOWER"]:
      case self::SELECT_FUNCTIONS["LENGTH"]:
      case self::SELECT_FUNCTIONS["SUM"]:
      case self::SELECT_FUNCTIONS["MIN"]:
      case self::SELECT_FUNCTIONS["MAX"]:
      case self::SELECT_FUNCTIONS["AVG"]:
        if(!is_string($functionParameters)){
          $type = gettype($functionParameters);
          $errorMsg = "When using the select function \"$function\" the parameter "
            ."has to be a string, got $type.";
          throw new InvalidArgumentException($errorMsg);
        }
        return [$functionParameters, null];
      case self::SELECT_FUNCTIONS["CONCAT"]:
        if(!is_array($functionParameters) || count($functionParameters) < 3){
          $type = gettype($functionParameters);
          $length = (is_array($functionParameters)) ? count($functionParameters) : 0;
          $errorMsg = "When using the select function \"$function\" the parameter "
            ."has to be an array with length > 3, got $type with length $length";
          throw new InvalidArgumentException($errorMsg);
        }
        list($glue) = $functionParameters;
        unset($functionParameters[array_keys($functionParameters)[0]]);

        return [$functionParameters, $glue];
      default:
        throw new InvalidArgumentException("The given select function \"$function\" is not supported.");
    }
  }

}