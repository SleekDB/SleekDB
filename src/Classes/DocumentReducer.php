<?php


namespace SleekDB\Classes;


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
    foreach ($found as $key => &$document) {
      $newDocument = [];
      $newDocument[$primaryKey] = $document[$primaryKey];
      foreach ($fieldsToSelect as $alternativeFieldName => $fieldToSelect) {
        $fieldName = (!is_int($alternativeFieldName))? $alternativeFieldName : $fieldToSelect;
        if(!is_string($fieldToSelect) && !is_int($fieldToSelect)){
          $errorMsg = "If select is used an array containing strings with fieldNames has to be given";
          throw new InvalidArgumentException($errorMsg);
        }
        $fieldValue = NestedHelper::getNestedValue($fieldToSelect, $document);
        $createdArray = NestedHelper::createNestedArray((string) $fieldName, $fieldValue);
        if(!empty($createdArray)){
          $createdArrayKey = array_keys($createdArray)[0];
          $newDocument[$createdArrayKey] = $createdArray[$createdArrayKey];
        }
      }
      $document = $newDocument;
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
   * @param array $havingConditions
   * @throws InvalidArgumentException
   */
  public static function handleGroupBy(array &$found, array $groupBy, array $fieldsToSelect, array $havingConditions)
  {
    if(empty($groupBy)){
      return;
    }
    // TODO optimize algorithm
    $groupByFields = $groupBy["groupByFields"];
    $countKeyName = $groupBy["countKeyName"];
    $allowEmpty = $groupBy["allowEmpty"];

    $pattern = (!empty($fieldsToSelect))? $fieldsToSelect : $groupByFields;

    if(!empty($countKeyName) && empty($fieldsToSelect)){
      $pattern[] = $countKeyName;
    }

    // remove duplicates
    $patternWithOutDuplicates = [];
    foreach ($pattern as $key => $item){
      if(!array_key_exists($key, $patternWithOutDuplicates) || !in_array($item, $patternWithOutDuplicates, true)){
        $patternWithOutDuplicates[$key] = $item;
      }
    }
    $pattern = $patternWithOutDuplicates;
    unset($patternWithOutDuplicates);

    // validate pattern
    foreach ($pattern as $key => $value){
      if(!is_string($key) && !is_string($value)){
        throw new InvalidArgumentException("You need to format the select correctly when using Group By.");
      }
      if(!is_string($value)) {
        if (!is_array($value) || empty($value)) {
          throw new InvalidArgumentException("You need to format the select correctly when using Group By.");
        }

        list($function) = array_keys($value);
        $field = $value[$function];
        if(!is_string($function) || !in_array(strtolower($function), ["sum", "min", "max", "avg"])){
          throw new InvalidArgumentException("The given function \"$function\" is not supported in Group By.");
        }
        if(!is_string($field)){
          throw new InvalidArgumentException("You need to format the select correctly when using Group By.");
        }

      } else if($value !== $countKeyName && !in_array($value, $groupByFields, true)) {
        throw new InvalidArgumentException("You can not select a field that is not grouped by.");
      }
    }

    $groupedResult = [];
    foreach ($found as $foundKey => $document){
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

      // new entry
      if(!array_key_exists($valueHash, $groupedResult)){
        $resultDocument = [];
        foreach ($pattern as $key => $patternValue){
          $resultFieldName = (is_string($key)) ? $key : $patternValue;

          if($resultFieldName === $countKeyName){
            $resultDocument[$resultFieldName] = 1;
            continue;
          }

          if(!is_string($patternValue)){
            list($function) = array_keys($patternValue);
            $fieldNameToHandle = $patternValue[$function];
            $currentFieldValue = NestedHelper::getNestedValue($fieldNameToHandle, $document);
            if(!is_numeric($currentFieldValue)){
              $resultDocument[$resultFieldName] = [$function => [null]];
            } else {
              $resultDocument[$resultFieldName] = [$function => [$currentFieldValue]];
            }
            continue;
          }
          $resultDocument[$resultFieldName] = NestedHelper::getNestedValue($patternValue, $document);
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

        if(!is_string($patternValue)){
          list($function) = array_keys($patternValue);
          $fieldNameToHandle = $patternValue[$function];
          $currentFieldValue = NestedHelper::getNestedValue($fieldNameToHandle, $document);
          $currentFieldValue = is_numeric($currentFieldValue) ? $currentFieldValue : null;
          $currentResult[$resultFieldName][$function][] = $currentFieldValue;
        }
      }
      $groupedResult[$valueHash] = $currentResult;
    }

    // reduce and format result
    $resultArray = [];
    foreach ($groupedResult as $result){
      foreach ($pattern as $key => $patternValue){
        $resultFieldName = (is_string($key)) ? $key : $patternValue;
        if(is_array($patternValue)){
          list($function) = array_keys($patternValue);
          $resultValue = $result[$resultFieldName][$function];
          switch (strtolower($function)){
            case "sum":
              $currentResult = 0;
              $allEntriesNull = true;
              foreach ($resultValue as $currentValue){
                if(!is_null($currentValue)){
                  $currentResult += $currentValue;
                  $allEntriesNull = false;
                }
              }
              if($allEntriesNull === true){
                $currentResult = null;
              }
              break;
            case "min":
              $currentResult = PHP_INT_MAX;
              if(empty($resultValue)){
                $currentResult = null;
                break;
              }
              $allEntriesNull = true;
              foreach ($resultValue as $currentValue){
                if(!is_null($currentValue)){
                  if($currentValue < $currentResult){
                    $currentResult = $currentValue;
                  }
                  $allEntriesNull = false;
                }
              }
              if($allEntriesNull === true){
                $currentResult = null;
              }
              break;
            case "max":
              $currentResult = PHP_INT_MIN;
              if(empty($resultValue)){
                $currentResult = null;
                break;
              }
              $allEntriesNull = true;
              foreach ($resultValue as $currentValue){
                if($currentValue > $currentResult && !is_null($currentValue)) {
                  $currentResult = $currentValue;
                  $allEntriesNull = false;
                }
              }
              if($allEntriesNull === true){
                $currentResult = null;
              }
              break;
            case "avg":
              if(empty($resultValue)){
                $currentResult = null;
                break;
              }
              $currentResult = 0;
              $resultValueAmount = $resultValue;
              $allEntriesNull = true;
              foreach ($resultValue as $currentValue){
                if(!is_null($currentValue)){
                  $currentResult += $currentValue;
                  $allEntriesNull = false;
                }
              }
              if($allEntriesNull === true){
                $currentResult = null;
              } else {
                $currentResult /= $resultValueAmount;
              }
              break;
            default:
              throw new InvalidArgumentException("The given function \"$function\" is not supported in Group By.");
          }
          $result[$resultFieldName] = $currentResult;
        }
      }
      if(empty($havingConditions) || true === ConditionsHandler::handleWhereConditions($havingConditions, $result)){
        $resultArray[] = $result;
      }
    }

    $found = $resultArray;
  }


}