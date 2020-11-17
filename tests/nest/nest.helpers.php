<?php

  function equalItems ($arrayWithoutId, $arrayWithId, $removeId = true) {
      $dataMatched = false;
      foreach ($arrayWithId as $key => $value) {
        if ($removeId) {
          unset($value['_id']);
        }
        foreach ($arrayWithoutId as $key => $singleArrayWithoutId) {
          if ($value === $singleArrayWithoutId) {
            $dataMatched = true;
            break;
          }
        }
      }
      if (!$dataMatched) {
        return [
          'result' => false,
          'message' => 'Data mismatched'
        ];
      }

      return true;
  }

  function idExistsInEveryItem ($items) {
    $test = [
      'result' => true,
      'message' => ''
    ];
    foreach ($items as $key => $value) {
      if (!isset($value['_id']) || gettype($value['_id']) !== 'integer') {
        $test['result'] = false;
        $test['message'] = "Can not find _id property in an item fetched after multiple insert";
        break;
      }
    }
    return $test;
  }