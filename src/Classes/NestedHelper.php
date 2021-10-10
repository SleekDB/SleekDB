<?php


namespace SleekDB\Classes;


use SleekDB\Exceptions\InvalidArgumentException;

/**
 * Class NestedHelper
 * Helper to handle arrays.
 */
class NestedHelper
{

  /**
   * Get nested properties of a store object.
   * @param string $fieldName
   * @param array $data
   * @return mixed
   * @throws InvalidArgumentException
   */
  public static function getNestedValue(string $fieldName, array $data)
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
   * Check if a nested Property exists
   * @param string $fieldName
   * @param array $data
   * @return mixed
   * @throws InvalidArgumentException
   */
  public static function nestedFieldExists(string $fieldName, array $data)
  {
    $fieldName = trim($fieldName);
    if (empty($fieldName)) {
      throw new InvalidArgumentException('fieldName is not allowed to be empty');
    }

    // Dive deep step by step.
    foreach (explode('.', $fieldName) as $i) {
      // check if field exists
      if (!is_array($data) || !array_key_exists($i, $data)) {
        return false;
      }
      // The index is valid, dive deeper.
      $data = $data[$i];
    }
    return true;
  }

  public static function updateNestedValue(string $fieldName, array &$data, $newValue){
    $fieldNameArray = explode(".", $fieldName);
    $value = $newValue;
    if(count($fieldNameArray) > 1){
      $data = self::_updateNestedValueHelper($fieldNameArray, $data, $newValue, count($fieldNameArray));
      return;
    }
    $data[$fieldNameArray[0]] = $value;
  }

  public static function createNestedArray(string $fieldName, $fieldValue): array
  {
    $temp = [];
    $fieldNameArray = explode('.', $fieldName);
    $fieldNameArrayReverse = array_reverse($fieldNameArray);
    foreach ($fieldNameArrayReverse as $index => $i) {
      if($index === 0){
        $temp = array($i => $fieldValue);
      } else {
        $temp = array($i => $temp);
      }
    }

    return $temp;
  }

  public static function removeNestedField(array &$document, string $fieldToRemove){
    if (array_key_exists($fieldToRemove, $document)) {
      unset($document[$fieldToRemove]);
      return;
    }
    // should be a nested array at this point
    $temp = &$document;
    $fieldNameArray = explode('.', $fieldToRemove);
    $fieldNameArrayCount = count($fieldNameArray);
    foreach ($fieldNameArray as $index => $i) {
      // last iteration
      if(($fieldNameArrayCount - 1) === $index){
        if(is_array($temp) && array_key_exists($i, $temp)) {
          unset($temp[$i]);
        }
        break;
      }
      if(!is_array($temp) || !array_key_exists($i, $temp)){
        break;
      }
      $temp = &$temp[$i];
    }
  }

  /**
   * @param array $keysArray
   * @param $data
   * @param $newValue
   * @param int $originalKeySize
   * @return mixed
   */
  private static function _updateNestedValueHelper(array $keysArray, $data, $newValue, int $originalKeySize)
  {
    if(empty($keysArray)){
      return $newValue;
    }
    $currentKey = $keysArray[0];
    $result = (is_array($data)) ? $data : [];
    if(!is_array($data) || !array_key_exists($currentKey, $data)){
      $result[$currentKey] = self::_updateNestedValueHelper(array_slice($keysArray, 1), $data, $newValue, $originalKeySize);
      if(count($keysArray) !== $originalKeySize){
        return $result;
      }
    }
    $result[$currentKey] = self::_updateNestedValueHelper(array_slice($keysArray, 1), $data[$currentKey], $newValue, $originalKeySize);
    return $result;
  }
}