#!/usr/bin/env php
<?php
function write_ini($data, $location='config.ini'){
    foreach($data as $d){
        $string = "{$d['setting']} = {$d['value']}";
        file_put_contents($location, $string, FILE_APPEND | LOCK_EX);
    }
}

if ('cli' !== php_sapi_name()) {
    return;
} else {
    require_once('SleekDB.php');
    $ini_array = parse_ini_file("config.ini");
    if($ini_array === false) {
      echo "No config.ini found, create one now? (y/n): ";
      $line = trim(fgets(STDIN)); 
        if($line == 'y') {
            $data = [];
            echo "Enter a data dir: ";
            $data_dir = trim(fgets(STDIN)); 
            array_push($data, ['setting'=> 'data_dir', 'value' => $data_dir]); 
            
            // write ini
            write_ini($data, 'config.ini');
        } 
    } else {
        $data_dir = $ini_array['data_dir'];
        unset($ini_array['data_dir']);
    }
    
}

// configure args
$shortopts  = "";
$shortopts .= "c:";  // Required value
$shortopts .= "f:";  // Required value
$shortopts .= "i:";  // Required value
$shortopts .= "v::"; // Optional value

$longopts  = array(
    "required:",     // Required value
    "create:", 
    "delete:",
    "delete-record:",
    "data-dir:",   
    "data:",
    "fetch:",    
    "insert:",   
    
        
);

$options = getopt($shortopts, $longopts);
print_r($options);
switch($options) {
    // create
    case $options['create-store'] || $options['c']: 
        $res = \SleekDB\SleekDB::store((!is_null($options['c']) ? $options['c'] : $options['create']), $data_dir);
        break;

    // delete-store
    case $options['d'] || $options['delete-store']: 
        $store = \SleekDB\SleekDB::store((!is_null($options['d']) ? $options['d'] : $options['delete']), $data_dir);
        $res = $store->deleteStore();
        break;    

    // insert
    case ($options['i'] || $options['insert']) && !is_null($options['data']):
        $store = \SleekDB\SleekDB::store((!is_null($options['i']) ? $options['i'] : $options['insert']), $data_dir);
        $data = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $options['data']), true );
        $res = $store->insert($data);
        break;


    // delete-record
    case $options['delete-record'] && !is_null($options['data']): 
        $store = \SleekDB\SleekDB::store($options['delete-record'], $data_dir);
        $data = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $options['data']), true );
        $res = $store->where($data['fieldName'], $data['condition'], $data['value'])->delete();
        break;

    // fetch
    case $options['f'] || $options['fetch']: 
        $store = \SleekDB\SleekDB::store((!is_null($options['f']) ? $options['f'] : $options['fetch']), $data_dir);
        $res = $fetch = $store->fetch();
        print_r($fetch);
        break;
        
}

print_r($res);