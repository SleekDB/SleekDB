#!/usr/bin/env php
<?php
function write_ini($data, $location='config.ini'){
    foreach($data as $d){
        $string = "{$d['setting']} = {$d['value']}";
        file_put_contents($location, $string, FILE_APPEND | LOCK_EX);
    }
}

function print_usage(){
    echo 'hi';
}

if ('cli' !== php_sapi_name()) {
    return;
} else {
    require_once('SleekDB.php');
    ///use SleekDB;
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
    $sdb = new \SleekDB\SleekDB($data_dir);
}

// var_dump($sdb);
// exit;

// configure args
$shortopts  = "";
$shortopts .= "c:";  // Required value
$shortopts .= "d:";  // Required value

$shortopts .= "f:";  // Required value
$shortopts .= "i:";  // Required value
$shortopts .= "h:";  // Required value

$opts = [
        'c' => "create:", 
        'd' => "delete:",
        'dr' => "delete-record:",
        'da' => "data:",
        'f' => "fetch:",    
        'i' => "insert:",
        'l' => 'list:' 
];

$options = getopt(implode(':', array_keys($opts)), array_values($opts));

print_r($options);
switch($options) {
    case $options['help'] || $options['h']: 
        print_usage();
    break;

    // list
    case $options['list'] || $options['l']:
        // find all stores in data dir

        // loop thru and print name
        foreach ($stores as $s)
        $store = $sdb->store((!is_null($options['f']) ? $options['f'] : $options['fetch']), $data_dir);
        $res = $fetch = $store->fetch();
        print_r($fetch);
        break;

    // create
    case $options['create-store'] || $options['c']: 
        $res = $sdb->store((!is_null($options['c']) ? $options['c'] : $options['create']), $data_dir);
        break;

    // delete-store
    case $options['d'] || $options['delete-store']: 
        $store = $sdb->store((!is_null($options['d']) ? $options['d'] : $options['delete']), $data_dir);
        $res = $store->deleteStore();
        break;    

    // insert
    case ($options['i'] || $options['insert']) && !is_null($options['data']):
        $store = $sdb->store((!is_null($options['i']) ? $options['i'] : $options['insert']), $data_dir);
        $data = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $options['data']), true );
        $res = $store->insert($data);
        break;


    // delete-record
    case $options['delete-record'] && !is_null($options['data']): 
        $store = $sdb->store($options['delete-record'], $data_dir);
        $data = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $options['data']), true );
        $res = $store->where($data['fieldName'], $data['condition'], $data['value'])->delete();
        break;

    // fetch
    case $options['f'] || $options['fetch']: 
        $store = $sdb->store((!is_null($options['f']) ? $options['f'] : $options['fetch']), $data_dir);
        $res = $fetch = $store->fetch();
        print_r('DD: ' . $fetch);
        break;
        
}

print_r($res);