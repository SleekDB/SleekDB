<?php

  $title = 'Trying to connect to the store "mysite"';

  function connect_to_store( $database ) {
    $database->store( "mysite" );
  }