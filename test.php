<?php
// Network Rail Stomp Handler example by ian13
$server = "tcp://datafeeds.networkrail.co.uk:61618";
include 'settings.php';

$channel = "TRAIN_MVT_ALL_TOC";
$timeout =0;
if( count($argv) > 1 )
{
	$timeout = time() + intval( $argv[1] );
	print( "Stopping in $argv[1] seconds \n" );
}


 function boolString($bValue = false) {                      // returns string
   return ($bValue ? 'true' : 'false');
 }


$db = new SQLite3('train_progress.db' );
$db->exec('CREATE TABLE IF NOT EXISTS Trains (train_id TEXT, stanox TEXT, next_stanox, whatTime INTEGER, trainServiceCode TEXT, eventType TEXT, toc_id TEXT );' );
$db->exec('CREATE INDEX IF NOT EXISTS idx_Trains_train_id ON Trains (train_id );');
$db->exec('CREATE TABLE IF NOT EXISTS TrainSchedule (train_uid TEXT, train_id TEXT, schedule_start_date TEXT,train_service_code TEXT, when_created DEFAULT current_timestamp  );' );
$db->exec('CREATE INDEX IF NOT EXISTS idx_ts_train_id ON TrainSchedule (train_id );' );
$db->exec('CREATE TABLE IF NOT EXISTS WayPoints (id TEXT, stanox TEXT, tme TEXT, next_stanox TEXT, next_tme TEXT, evtTime DATETIME default current_timestamp, toc_id TEXT, train_uid TEXT, seq_id INTEGER );');
$db->exec( 'CREATE INDEX IF NOT EXISTS idx_wayPoints_id ON WayPoints (id );' );
 
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
 
$con->subscribe("/topic/" . $channel,array('activemq.subscriptionName' => 'test') );
# $db->busyTimeout( 5000 );
while($con && ($timeout == 0 || time() < $timeout ) ){
   if ($con->hasFrame()){
       $msg = $con->readFrame();
       if( $msg != false ){
       	   print ("start .." );
	   do {
       	      $db->exec( 'BEGIN;' );
	      if( $db->lastErrorCode() != 0 ){
	         sleep( 1 );
		 print( "_" );
	      }
	   }while( $db->lastErrorCode() != 0 );
       	   foreach (json_decode($msg->body) as $event) {
	       if( $event->header->msg_type == "0003" ) {
       	          $stmt = $db->prepare( 'DELETE FROM Trains WHERE train_id = ?;' );
	          $stmt->bindValue( 1, $event->body->train_id );
	          $stmt->execute();
	          $stmt = $db->prepare( 'INSERT INTO Trains (train_id, stanox, next_stanox, whatTime, trainServiceCode, eventType, toc_id )' .
	       	       		    ' VALUES (?,?,?,?,?,?,? );' );
                  $stmt->bindValue( 1, $event->body->train_id );
	          $stmt->bindValue( 2, $event->body->loc_stanox );
	          $stmt->bindValue( 3, $event->body->next_report_stanox );
	          $stmt->bindValue( 4, $event->body->actual_timestamp / 1000 ); # convert milliseconds to seconds.
	          $stmt->bindValue( 5, $event->body->train_service_code );
	          $stmt->bindValue( 6, $event->body->planned_event_type );
	          $stmt->bindValue( 7, $event->body->toc_id);
		  print( "stanox " . $event->body->loc_stanox . " run time ". $event->body->next_report_run_time . " next stanox  " . $event->body->next_report_stanox  .  "\n" );
		  $stmt->execute();
# try to find if this is a stanox we know about, and we know schedule.
      	      	  $stmt = $db->prepare( 'SELECT sched_seq, stanox, tme, ts.train_uid as t_uid ' .
		  	  	' FROM TrainSchedule as ts ' . 
				' JOIN Schedule sch ON ts.train_uid = sch.train_uid AND ts.schedule_start_date = sch.schedule_start_date '.
				' JOIN Segments2 AS sg ON sg.sch_id = sch.sch_id ' . 
				' WHERE train_id = ? AND tme IS NOT NULL ORDER BY sched_seq ASC ;' );
		  $stmt->bindValue( 1, $event->body->train_id );
		  $results = $stmt->execute();
		  $found = false;
		  $loc_time = 0;
		  $next_stanox =0;
		  $next_time = 0;
		  $rowid = 0;
		  $t_uid = 0;
		  while( $row = $results->fetchArray( SQLITE3_ASSOC ) ){
#		     var_dump( $row );
		     if( $found == false){
		         if( $row["stanox"] == $event->body->loc_stanox ){
			    $found = true;
			    $loc_time = $row["tme"];
			    $rowid = $row["sched_seq"];
			    $t_uid = $row["t_uid" ];
			 }
	             } else {
#		     print( $rowid . " " . $row["sched_seq" ] . "\n" );
		         if( $next_stanox == 0 && $rowid < $row["sched_seq"] ){
		             $next_stanox = $row["stanox"];
			     $next_time = $row["tme"];
	                 }
		     }
		  }
		  print( boolString( $found ) ." ". $event->body->train_id . " ". 
		  	 $event->body->loc_stanox . " " .
			  $next_stanox . " " .$loc_time . " " . $next_time . "\n" );
		  if( $found == true && $next_time != 0 ){
		      $stmt = $db->prepare( "DELETE FROM WayPoints WHERE id = ?;" );
		      $stmt->bindValue( 1, $event->body->train_id );
		      $stmt->execute();
		      $stmt = $db->prepare( "INSERT INTO WayPoints (id, stanox, tme, next_stanox, next_tme, toc_id, train_uid, seq_id ) VALUES (?,  ?,?,  ?,?,?, ?, ? );" );
		      $stmt->bindValue( 1, $event->body->train_id );

		      $stmt->bindValue( 2, $event->body->loc_stanox );
		      $stmt->bindValue( 3, $loc_time );
		      
		      $stmt->bindValue( 4, $next_stanox );
		      $stmt->bindValue( 5, $next_time );
		      $stmt->bindValue( 6, $event->body->toc_id );
		      $stmt->bindValue( 7, $t_uid );
		      $stmt->bindValue( 8, $rowid );
		      $stmt->execute();
		  } else if ($found == false ){
		  # create a false waypoint if we know geo location of both stanoxes
		  }
#		  var_dump( $event );
	       } else if ( $event->header->msg_type == "0001" ){
	          //var_dump( $event );
	          $stmt = $db->prepare( 'INSERT INTO TrainSchedule (train_uid, train_id, schedule_start_date, train_service_code ) VALUES (?,?,?, ? );' );
                  $stmt->bindValue( 1, $event->body->train_uid );
                  $stmt->bindValue( 2, $event->body->train_id );
		  $stmt->bindValue( 3, $event->body->schedule_start_date );
		  $stmt->bindValue( 4, $event->body->train_service_code );
		  $stmt->execute();
               }

       	   }
       	   $con->ack($msg);
	   $db->exec( "DELETE FROM TrainSchedule WHERE when_created < DateTime('now', '-24 hours' );" );
       	   $db->exec("DELETE FROM TrainSchedule WHERE train_id IN (SELECT train_id FROM Trains  where eventtype='DESTINATION' and datetime(whattime, 'unixepoch','5 minutes') < datetime('now') );");

       	   $db->exec("delete from trains where eventtype='DESTINATION' and datetime(whattime, 'unixepoch','5 minutes') < datetime('now');");
       	   $db->exec("delete from trains where datetime(whattime, 'unixepoch','12 hours') < datetime('now');");
       	   $db->exec("delete from WayPoints where id NOT IN (SElECT train_id FROM Trains );");
       	   $db->exec("delete from WayPoints where evtTime <datetime('now', '-12 hours' ) ;");
	   do {
   	      $db->exec( 'COMMIT;' );	
	      if( $db->lastErrorCode() != 0 ){
	         sleep( 1 );
		 print( "_" );
	      }
	   }while( $db->lastErrorCode() != 0 );

	   print( "done\n" );
       }
   }
}
 
die('Connection lost: ' . time());
?>

