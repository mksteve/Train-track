<?php
include 'settings_server.php';

 function boolString($bValue = false) {                      // returns string
   return ($bValue ? 'true' : 'false');
 }
error_reporting(-1);
$db = NULL;
try {
      $db = new PDO( $sql_dsn, $sql_user, $sql_pass );
} catch ( PDOException $e ) {
  echo 'connection Failed: ' . $e->GetMessage();
}

#$db = new SQLite3( $_SERVER['DOCUMENT_ROOT' ] . '/../db/train_progress.db' );
#$db->exec('CREATE TABLE IF NOT EXISTS Trains (train_id TEXT, stanox TEXT, next_stanox, whatTime INTEGER, trainServiceCode TEXT, eventType TEXT, toc_id TEXT );' );
#$db->exec('CREATE INDEX IF NOT EXISTS idx_Trains_train_id ON Trains (train_id );');
#$db->exec('CREATE TABLE IF NOT EXISTS TrainSchedule (train_uid TEXT, train_id TEXT, schedule_start_date TEXT,train_service_code TEXT, when_created DEFAULT current_timestamp  );' );
#$db->exec('CREATE INDEX IF NOT EXISTS idx_ts_train_id ON TrainSchedule (train_id );' );
#$db->exec('CREATE TABLE IF NOT EXISTS WayPoints (id TEXT, stanox TEXT, tme TEXT, next_stanox TEXT, next_tme TEXT, evtTime DATETIME default current_timestamp, toc_id TEXT, train_uid TEXT, seq_id INTEGER );');
#$db->exec( 'CREATE INDEX IF NOT EXISTS idx_wayPoints_id ON WayPoints (id );' );
 

#do {
#   $db->exec( 'BEGIN;' );
#   if( $db->lastErrorCode() != 0 ){
#      sleep( 1 );
#      print( "_" );
#   }
#}while( $db->lastErrorCode() != 0 );
function _chkExecute( $stmt ){
   if( $stmt->execute() == FALSE ){
       print_r( $stmt->errorInfo() );
       exit( 1 );
   }
   
}


$db->beginTransaction();

$msgbody = file_get_contents( 'php://input' );
foreach (json_decode($msgbody) as $event) {
   if( $event->header->msg_type == "0003" ) {
      $stmt = $db->prepare( 'SELECT whatTime FROM Trains WHERE train_id = ?;' );
      $stmt->bindValue( 1, $event->body->train_id );
      _chkExecute($stmt );
      $doReplace = true;
      if( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ){
      	  $oldRec = (integer)$row['whatTime'];
	  if( $oldRec > $event->body->actual_timestamp / 1000 ){
	      print( "skipping old record ". $event->body->train_id . "stanox ". $event->body->loc_stanox . "\n" );
	      $doReplace = false;
	  }
      }
      { // store all stops for a train...
      	$stmt = $db->prepare( 'DELETE FROM  TrainStops WHERE train_id = ? AND stanox = ?;' );
 	$stmt->bindValue( 1, $event->body->train_id );
	$stmt->bindValue( 2, $event->body->loc_stanox );
	_chkExecute( $stmt );
      	$stmt = $db->prepare( 'INSERT INTO TrainStops (train_id, stanox, whatTime, eventType ) VALUES (?,?,?,?);' );
 	$stmt->bindValue( 1, $event->body->train_id );
	$stmt->bindValue( 2, $event->body->loc_stanox );
	$stmt->bindValue( 3, $event->body->actual_timestamp / 1000 );
	$stmt->bindValue( 4, $event->body->planned_event_type );
	_chkExecute( $stmt );
      }
      if( $doReplace ){
          $stmt = $db->prepare( 'DELETE FROM Trains WHERE train_id = ?;' );
          $stmt->bindValue( 1, $event->body->train_id );
          _chkExecute($stmt );
      	  $stmt = $db->prepare( 'INSERT INTO Trains (train_id, stanox, next_stanox, whatTime, trainServiceCode, eventType, toc_id )' .
	       	       		    ' VALUES (?,?,?,?,?,?,? );' );
          $stmt->bindValue( 1, $event->body->train_id );
      	  $stmt->bindValue( 2, $event->body->loc_stanox );
      	  $stmt->bindValue( 3, $event->body->next_report_stanox );
      	  $stmt->bindValue( 4, $event->body->actual_timestamp / 1000 ); # convert milliseconds to seconds.
      	  $stmt->bindValue( 5, $event->body->train_service_code );
      	  $stmt->bindValue( 6, $event->body->planned_event_type );
      	  $stmt->bindValue( 7, $event->body->toc_id);
#          print( "stanox " . $event->body->loc_stanox . " run time ". $event->body->next_report_run_time . " next stanox  " . $event->body->next_report_stanox  .  "\n" );
          _chkExecute( $stmt );
# try to find if this is a stanox we know about, and we know schedule.
          $stmt = $db->prepare( 'SELECT sched_seq, stanox, tme, ts.train_uid as t_uid ' .
      	      		    ' FROM TrainSchedule as ts ' . 
			    ' JOIN Schedule sch ON ts.train_uid = sch.train_uid AND ts.schedule_start_date = sch.schedule_start_date '.
 			    ' JOIN Segments2 AS sg ON sg.sch_id = sch.sch_id ' . 
 			    ' WHERE train_id = ? AND tme IS NOT NULL ORDER BY sched_seq ASC ;' );
          $stmt->bindValue( 1, $event->body->train_id );
      	  _chkExecute( $stmt );
      	  $found = false;
      	  $loc_time = 0;
      	  $next_stanox =0;
      	  $next_time = 0;
      	  $rowid = 0;
      	  $t_uid = 0;
      	  while( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ){
#		     var_dump( $row );
              if( $found == false){
      	      	  if( $row["stanox"] == $event->body->loc_stanox ){
 	      	      $found = true;
 	     	      $loc_time = $row["tme"];
 	     	      $rowid = $row["sched_seq"];
 	     	      $t_uid = $row["t_uid" ];
 	      	  }
      	      } else {
# print( $rowid . " " . $row["sched_seq" ] . "\n" );
      	      	if( $next_stanox == 0 && $rowid < $row["sched_seq"] ){
      	           $next_stanox = $row["stanox"];
 	      	   $next_time = $row["tme"];
                }
              }
          }
# print( boolString( $found ) ." ". $event->body->train_id . " ". 
#      $event->body->loc_stanox . " " .
#      $next_stanox . " " .$loc_time . " " . $next_time . "\n" );
          if( $found == true && $next_time != 0 ){
      	      $stmt = $db->prepare( "DELETE FROM WayPoints WHERE id = ?;" );
 	      $stmt->bindValue( 1, $event->body->train_id );
 	      _chkExecute( $stmt );
 	      $stmt = $db->prepare( "INSERT INTO WayPoints (id, stanox, tme, next_stanox, next_tme, toc_id, train_uid, seq_id ) VALUES (?,  ?,?,  ?,?,?, ?, ? );" );
 	      $stmt->bindValue( 1, $event->body->train_id );

 	      $stmt->bindValue( 2, $event->body->loc_stanox );
 	      $stmt->bindValue( 3, $loc_time );
 	      $stmt->bindValue( 4, $next_stanox );
 	      $stmt->bindValue( 5, $next_time );
 	      $stmt->bindValue( 6, $event->body->toc_id );
 	      $stmt->bindValue( 7, $t_uid );
 	      $stmt->bindValue( 8, $rowid );
 	      _chkExecute( $stmt );
          } else if ($found == false ){
 # create a false waypoint if we know geo location of both stanoxes
          }
      }
#		  var_dump( $event );
  } else if ( $event->header->msg_type == "0001" ){
      //var_dump( $event );
      $stmt = $db->prepare( 'INSERT INTO TrainSchedule (train_uid, train_id, schedule_start_date, train_service_code ) VALUES (?,?,?, ? );' );
      $stmt->bindValue( 1, $event->body->train_uid );
      $stmt->bindValue( 2, $event->body->train_id );
      $stmt->bindValue( 3, $event->body->schedule_start_date );
      $stmt->bindValue( 4, $event->body->train_service_code );
      _chkExecute( $stmt );
 }
}

 $db->exec( "DELETE FROM TrainSchedule WHERE when_created < SUBDATE( CURRENT_TIMESTAMP(), INTERVAL 24 hour );" );
 $db->exec("DELETE FROM TrainSchedule WHERE train_id IN (SELECT train_id FROM Trains where eventtype='DESTINATION' and ADDDATE( FROM_UNIXTIME( whattime) , INTERVAL 4 hour ) < CURRENT_TIMESTAMP() );"); // need to cope with late messages

 $db->exec("delete from Trains WHERE eventtype='DESTINATION' and ADDDATE( FROM_UNIXTIME( whattime) , INTERVAL 4 hour ) < CURRENT_TIMESTAMP();");
 $db->exec("delete from Trains where ADDDATE(FROM_UNIXTIME(whattime), INTERVAL 12 hour) < CURRENT_TIMESTAMP();");
 $db->exec("delete from WayPoints where id NOT IN (SElECT train_id FROM Trains );");
 $db->exec("delete from WayPoints where ADDDATE( evtTime, INTERVAL 12 hour ) < CURRENT_TIMESTAMP();");
 $db->exec("DELETE FROM Trains WHERE train_id NOT IN (SELECT train_id FROM Trains );" );
 $db->commit();


#	   print( "done\n" );

?>

