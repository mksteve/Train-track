
<?php

  $db = new SQLite3('train_progress.db' );

   	   
  $rows = array();
  do {
     $result = $db->query( "select id as train_id, toc_id, strftime('%s', evtTime ) as whenTime, SUBSTR(tme,1,4) as station1_tme, sta1.Lat as Lat, sta1.Long as Long, SUBSTR(next_tme,1,4) as station2_tme, sta2.Lat AS next_Lat, sta2.Long as next_long FROM WayPoints as wp JOIN Stations2 as sta1 ON wp.stanox = sta1.stanox join Stations2 as sta2 ON wp.next_stanox = sta2.stanox;" );

     if( $db->lastErrorCode() != 0 ) {
     	 sleep( 1 );
     }
  } while( $db->lastErrorCode() != 0 );
	   
  while( $row = $result->fetchArray(SQLITE3_ASSOC) ){
     $rows[] = $row;
  }
  print json_encode( $rows );
?>