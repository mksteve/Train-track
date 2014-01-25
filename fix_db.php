<?php
include 'settings_server.php';

$db = NULL;
try {
      $db = new PDO( $sql_dsn, $sql_user, $sql_pass );
} catch ( PDOException $e ) {
  echo 'connection Failed: ' . $e->GetMessage();
  exit(1);
}
function _chkExecute( $stmt ){
   if( $stmt->execute() == FALSE ){
       print_r( $stmt->errorInfo() );
       exit( 1 );
   }
   
}
######################################################
## Stations 2 is the set of stations which we have 
## stanox codes for.
## (Stations is the set of tiplocs which we have northing/eastings for.)
#########################################################################
$db->exec( 'TRUNCATE TABLE Stations2;' );
$db->exec( 'TRUNCATE TABLE Segments2;' );
$db->exec( 'TRUNCATE TABLE helper;' );
print_r( $db->errorInfo() );
$db->exec( 'INSERT INTO Stations2 (stanox, TiplocCode, CrsCode, StationName, Easting, NORTHING, Lat, Long_ ) ' . 
	   'SELECT ' . 
	   ' stn.STANOX ' .
	   ' ,sta.TiplocCode ' .
	   ' ,sta.CrsCode ' .
	   ' ,sta.StationName ' .
	   ' ,sta.Easting ' .
	   ' ,sta.NORTHING ' .
	   ' ,sta.Lat ' .
	   ' ,sta.Long_ ' .
	   ' FROM Stations sta ' .
	   ' JOIN Stanox stn ON stn.Alpha3 = sta.crsCode AND stn.TIPLOC = sta.TiplocCode' );

print_r( $db->errorInfo() );
$db->exec( 'INSERT INTO Segments2 (sched_seq, sch_id, type, tiploc, tme, stanox ) ' .
	   ' SELECT sg.sched_seq, sg.sch_id, sg.type, sg.tiploc, sg.tme, sta.stanox FROM ' .
	   '  Segments AS sg ' .
	   '  JOIN Stations2 as sta ON sta.TiplocCode = sg.tiploc ' .
	   ' WHERE sg.tme IS NOT NULL;' );
print_r( $db->errorInfo() );
$db->exec( 'INSERT INTO helper ( sch_id, seq_id, stanox ) SELECT sch_id, sched_seq, stanox FROM Segments2;' );
print_r( $db->errorInfo() );
?>
