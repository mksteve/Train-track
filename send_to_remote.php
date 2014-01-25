<?php
// Network Rail Stomp Handler example by ian13
$server = "tcp://datafeeds.networkrail.co.uk:61618";
include 'settings.php';

$channel = "TRAIN_MVT_ALL_TOC";
$timeout =0;
date_default_timezone_set('UTC');
if( count($argv) > 1 )
{
	$timeout = time() + intval( $argv[1] );
	print( "Stopping in $argv[1] seconds \n" );
}

function read_callback($ch, $fd, $length)
{
    $string = fread( $fd, $length );
    return $string; 
}

function boolString($bValue = false) {                      // returns string
   return ($bValue ? 'true' : 'false');
 }


$curl_option_defaults = array(
    CURLOPT_HEADER => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20
  ); 

// Generic Curl REST function.
// Connection are created demand and closed by PHP on exit.
function curl_rest($method,$uri,$query=NULL,$json=NULL,$options=NULL){
  global $curl_url,$curl_handle,$curl_option_defaults;

  // Connect 
  if(!isset($curl_handle)) $curl_handle = curl_init();

#  echo "DB operation: $method $uri $query $json\n";

   $fd = fopen( "php://memory" , "w+" );
   fwrite( $fd, $json, strlen( $json ));
   fseek( $fd , 0 );
  // Compose query
  $options = array(
    CURLOPT_URL => $curl_url.$uri."?".$query,
    CURLOPT_CUSTOMREQUEST => $method, // GET POST PUT PATCH DELETE HEAD OPTIONS 
#    CURLOPT_POSTFIELDS => array( "data" => $json ),
    CURLOPT_POST => TRUE,
    CURLOPT_HTTPHEADER => array (
       'Content-Type: application/json',
       'Content-Length: ' . strlen( $json ) ),
    CURLOPT_INFILE => $fd,
    CURLOPT_INFILESIZE => strlen( $json ),
    CURLOPT_RETURNTRANSFER => true,
  ); 
  curl_setopt_array($curl_handle,($options + $curl_option_defaults)); 

  // send request and wait for response
  $response =  curl_exec($curl_handle);
  fclose( $fd );
  echo "Response from DB: ". gettype( $response ). "\n";
  if( gettype( $response ) == "string" ){
      print( $response );
  }
  if( gettype( $response ) == "boolean" ){
      echo curl_error( $curl_handle );
  }
  print_r($response);
  if( gettype( $response ) == 'string' ){
      if( strpos( $response, 'Deadlock' ) != FALSE ){
      	  return false;
      }
  }  
  return($response);
}

 
$con = 0;
try {
    $con = new Stomp($server, $netrail_user, $netrail_password, array('client-id' => $netrail_user ) );
}
catch ( StompException $e ){
      var_dump( $e);
}
if (!$con) {
   die('Connection failed: ' . stomp_connect_error());
}
 
$con->subscribe("/topic/" . $channel,array('activemq.subscriptionName' => 'send_to_remote') );
# $db->busyTimeout( 5000 );
while($con && ($timeout == 0 || time() < $timeout ) ){
   if ($con->hasFrame()){
       $msg = $con->readFrame();
       if( $msg != false ){
       	   print ("start .." );
	   if( curl_rest( "POST", $website_post ,NULL, $msg->body ) == false ){
	       print( "retrying\n" );
	       curl_rest( "POST", $website_post ,NULL, $msg->body );
	   }
	   foreach (json_decode($msg->body) as $event) {
	       print( date("F j, Y, g:i a" ) . " type " . $event->header->msg_type . " " . $event->body->train_id  );
	       if( $event->header->msg_type == "0003" ){
	       	   print( " " .$event->body->event_type . " ". $event->body->planned_event_type ." " );
		   print( $event->body->loc_stanox . " " . $event->body->next_report_stanox . " " );
		   print( $event->body->next_report_run_time . " " );
		   print( date( "F j, Y, g:i a", $event->body->actual_timestamp / 1000 ) . " "  );
		   print( $event->body->train_terminated . " " . $event->body->reporting_stanox . " "  );
		   print( $event->body->auto_expected . " " . $event->header->original_data_source . " " );
		   print( $event->header->source_dev_id );
	       }
	       if( $event->header->msg_type == "0001" ){
	       	   print(  " ". $event->body->train_uid ." " . $event->body->schedule_start_date . " " . $event->body->schedule_type );
	       }
	       print( "\n" );
           }
	   print( "done\n" );
       }
       $con->ack( $msg );
   }
}
 
die('Connection lost: ' . time());
?>

