<?php
require_once('vendor/autoload.php');
require_once('SleekDB.php');

class SleekCli extends League\CLImate\CLImate {
    public $sdb;
    public $data_dir;
    public $args;

    function __construct($ini_array = false ) {
        parent::__construct();
        if(!$ini_array) $ini_array = $this->create_config();
        $this->data_dir = $ini_array['data_directory'] ?? 'sleek_db';
        $this->sdb = new \SleekDB\SleekDB($this->data_dir, $ini_array);
        
        // define args 
        $this->args = [
            'create-store' => [
                'prefix'       => 'c',
                'longPrefix'   => 'create-store',
                'description'  => 'Create a new store',
            ],
            'delete-store' => [
                'prefix'      => 'd',
                'longPrefix'  => 'delete-store',
                'description' => 'Delete a store',
            ],
            'delete-object' => [
                'prefix'      => 'do',
                'longPrefix'  => 'delete-object',
                'description' => 'Delete a object into a store',
            ],
            'insert' => [
                'prefix'      => 'i',
                'longPrefix'  => 'insert',
                'description' => 'Insert a object into a store'
            ],
            'fetch' => [
                'prefix'      => 'f',
                'longPrefix'  => 'fetch',
                'description' => 'Fetch all objects from a store'
            ],
            'insert-many' => [
                'prefix'      => 'im',
                'longPrefix'  => 'insert',
                'description' => 'Insert an object into a store'
            ],
            'data' => [
                'longPrefix'  => 'data',
                'description' => 'JSON object to insert a record'
            ],
            'list-stores' => [
                'prefix'      => 'l',
                'longPrefix'  => 'list-stores',
                'description' => 'List all stores in the data directory.',
                'noValue' => true
            ],
            'help' => [
                'prefix'      => 'h',
                'longPrefix'  => 'help',
                'description' => 'Prints a usage statement',
                'noValue' => true

            ]
        ];
        
        $this->arguments->add($this->args);
    }

    // create config.ini file
    public function create_config() {
        $input = $this->confirm('No config file found. Create one now?');
        if(!$input->confirmed()) die("Please create a config.ini file.\n");

        $ini_array = [];
        $input = $this->input('Enter a data directory:');
        $ini_array['data_directory'] = $input->prompt(); 

        $input = $this->confirm('Enable auto-caching?');
        $ini_array['auto_cache'] = $input->confirmed(); 

        $input = $this->input('Enter Timeout length (int):');
        $ini_array['timeout'] = $input->prompt(); 

        foreach($ini_array as $config => $value ){
            $line = "{$config} = {$value}\n";
            file_put_contents('config.ini', $line, FILE_APPEND | LOCK_EX);
        }

        return $ini_array;
    }


    public function check_args() {
        $this->arguments->parse();
        foreach($this->args as $arg => $values) {
            if($this->arguments->get($arg)) {
                echo "$arg : {$this->arguments->get($arg)} \n";
                switch($arg){
                    case 'delete-store':
                        $this->sdb->store($this->arguments->get($arg), $this->data_dir)->deleteStore();
                        break;
                    case 'create-store':
                        $this->sdb->store($this->arguments->get($arg), $this->data_dir);
                        break;
                    case 'fetch':
                        $store = $this->sdb->store($this->arguments->get($arg), $this->data_dir)->fetch();
                        print_r('Store: ' . $store);
                        break;  
                    case 'insert':
                        $store = $this->sdb->store($this->arguments->get($arg), $this->data_dir);
                        $data = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $this->arguments->get('data')), true );
                        $res = $store->insert($data);
                        break;
                    case 'delete-record':
                        $store = $this->sdb->store($this->arguments->get($arg), $this->data_dir);
                        $data = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $this->arguments->get('data')), true );
                        $res = $store->where($data['fieldName'], $data['condition'], $data['value'])->delete();
                        break;    
                    case 'help':
                        $this->usage();
                        break;
                }
            }
        }
    }
}
?>