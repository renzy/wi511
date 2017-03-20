<?php

require_once 'wi511.class.php';
$api = new wi511('YOUR_API_KEY');

//fetch single endpoint
//$api->get('cameras');

//fetch all endpoints
$api->all();
?>