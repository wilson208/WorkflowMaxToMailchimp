<?php

include_once 'config.php';

header('Content-Type: application/json');

if(!array_key_exists('email', $_POST) || !array_key_exists('name', $_POST) || !array_key_exists('list_id', $_POST) ){
    throw new Exception('Invalid Parameters', '400');
}

$name = $_POST['name'];
$email = $_POST['email'];
$list_id = $_POST['list_id'];

$name = explode(' ', $name, 2);


$mailchimp = new Drewm\MailChimp(MAILCHIMP_API_KEY);
$result = $mailchimp->call('lists/subscribe', array(
    'id'                => $list_id,
    'email'             => array('email'=> $email),
    'merge_vars'        => array('FNAME'=>$name[0], 'LNAME'=>(count($name) > 1 ? $name[1] : '')),
    'double_optin'      => false,
    'update_existing'   => true,
    'replace_interests' => false,
    'send_welcome'      => false,
));

if($result != false){
    if(array_key_exists('status', $result) && $result['status'] == 'error'){
        echo json_encode(array('success' => 0, 'error' => $result['error']));
    }else{
        echo json_encode(array('success' => 1));
    }
    die();
}

throw new Exception('Unknown Error', '400');