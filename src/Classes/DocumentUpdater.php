<?php


namespace SleekDB\Classes;


use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;
use SleekDB\Query;
use SleekDB\Store;
use function basteyy\VariousPhpSnippets\varDebug;

/**
 * Class DocumentUpdater
 * Update and/or delete documents
 */
class DocumentUpdater
{

  protected $storePath;
  protected $primaryKey;
  protected $prettyPrint = false;

  public function __construct(string $storePath, string $primaryKey, bool $prettyPrint)
  {
    $this->storePath = $storePath;
    $this->primaryKey = $primaryKey;
    $this->prettyPrint = $prettyPrint;
  }

  /**
   * Update one or multiple documents, based on current query
   * @param array $results
   * @param array $updatable
   * @param bool $returnUpdatedDocuments
   * @return array|bool
   * @throws IOException
   */
  public function updateResults(array $results, array $updatable, bool $returnUpdatedDocuments)
  {
    if(count($results) === 0) {
      return false;
    }

    $primaryKey = $this->primaryKey;
    $dataPath = $this->getDataPath();
    // check if all documents exist beforehand
    foreach ($results as $key => $data) {
      $primaryKeyValue = IoHelper::secureStringForFileAccess($data[$primaryKey]);
      $data[$primaryKey] = (int) $primaryKeyValue;
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
      IoHelper::writeContentToFile($filePath, $this->encodeJson($data));
      $results[$key] = $data;
    }
    return ($returnUpdatedDocuments === true) ? $results : true;
  }

  /**
   * Deletes matched store objects.
   * @param array $results
   * @param int $returnOption
   * @return bool|array|int
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function deleteResults(array $results, int $returnOption)
  {
    $primaryKey = $this->primaryKey;
    $dataPath = $this->getDataPath();
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

  /**
   * @param array $results
   * @param array $fieldsToRemove
   * @return array|false
   * @throws IOException
   */
  public function removeFields(array &$results, array $fieldsToRemove)
  {
    $primaryKey = $this->primaryKey;
    $dataPath = $this->getDataPath();

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

    foreach ($results as &$document){
      foreach ($fieldsToRemove as $fieldToRemove){
        if($fieldToRemove !== $primaryKey){
          NestedHelper::removeNestedField($document, $fieldToRemove);
        }
      }
      $filePath = $dataPath . $document[$primaryKey] . '.json';
      IoHelper::writeContentToFile($filePath, $this->encodeJson($document));
    }
    return $results;
  }

  /**
   * @return string
   */
  private function getDataPath(): string
  {
    return $this->storePath . Store::dataDirectory;
  }

    /**
     * Encode the array to a json string
     * @param array $json_object
     * @return string
     */
    public function encodeJson(array $json_object): string
    {
        try {
            return $this->prettyPrint ? json_encode($json_object, JSON_PRETTY_PRINT) : json_encode($json_object);
        } catch (\Exception $exception) {
            varDebug($exception);
        }
    }


}