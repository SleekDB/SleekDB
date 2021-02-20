<?php


namespace SleekDB\Classes;


use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;
use SleekDB\Query;

/**
 * Class DocumentUpdater
 * Update and/or delete documents
 */
class DocumentUpdater
{

  /**
   * Update one or multiple documents, based on current query
   * @param array $results
   * @param array $updatable
   * @param bool $returnUpdatedDocuments
   * @param string $primaryKey
   * @param string $dataPath
   * @return array|bool
   * @throws IOException
   */
  public static function updateResults(array $results, array $updatable, bool $returnUpdatedDocuments, string $primaryKey, string $dataPath)
  {
    // If no documents found return false.
    if (empty($results)) {
      return false;
    }
    // check if all documents exist beforehand
    foreach ($results as $key => $data) {
      $primaryKeyValue = IoHelper::secureStringForFileAccess($data[$primaryKey]);
      $data[$primaryKey] = $primaryKeyValue;
      $results[$key] = $data;

      $filePath = $dataPath . $primaryKeyValue . '.json';
      if(!file_exists($filePath)){
        return false;
      }
    }

    foreach ($results as $key => $data){
      $filePath = $dataPath . $data[$primaryKey] . '.json';
      foreach ($updatable as $fieldName => $value) {
        // Do not update the primary key reserved index of a store.
        if ($fieldName !== $primaryKey) {
          NestedHelper::updateNestedValue($fieldName, $data, $value);
        }
      }
      IoHelper::writeContentToFile($filePath, json_encode($data));
      $results[$key] = $data;
    }
    return ($returnUpdatedDocuments === true) ? $results : true;
  }

  /**
   * Deletes matched store objects.
   * @param array $results
   * @param int $returnOption
   * @param string $primaryKey
   * @param string $dataPath
   * @return bool|array|int
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public static function deleteResults(array $results, int $returnOption, string $primaryKey, string $dataPath)
  {

    switch ($returnOption){
      case Query::DELETE_RETURN_BOOL:
        $returnValue = !empty($results);
        break;
      case Query::DELETE_RETURN_COUNT:
        $returnValue = count($results);
        break;
      case Query::DELETE_RETURN_RESULTS:
        $returnValue = $results;
        break;
      default:
        throw new InvalidArgumentException("Return option \"$returnOption\" is not supported");
    }

    if (empty($results)) {
      return $returnValue;
    }

    // TODO implement beforehand check

    foreach ($results as $key => $data) {
      $primaryKeyValue = IoHelper::secureStringForFileAccess($data[$primaryKey]);
      $filePath = $dataPath . $primaryKeyValue . '.json';
      if(false === IoHelper::deleteFile($filePath)){
        throw new IOException(
          'Unable to delete document! 
            Already deleted documents: '.$key.'. 
            Location: "' . $filePath .'"'
        );
      }
    }
    return $returnValue;
  }

}