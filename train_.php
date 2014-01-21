<?php
include 'settings_server.php';

    error_reporting(E_ALL^ E_WARNING); 
    if (!function_exists('http_response_code')) {
        function http_response_code($code = NULL) {

            if ($code !== NULL) {

                switch ($code) {
                    case 100: $text = 'Continue'; break;
                    case 101: $text = 'Switching Protocols'; break;
                    case 200: $text = 'OK'; break;
                    case 201: $text = 'Created'; break;
                    case 202: $text = 'Accepted'; break;
                    case 203: $text = 'Non-Authoritative Information'; break;
                    case 204: $text = 'No Content'; break;
                    case 205: $text = 'Reset Content'; break;
                    case 206: $text = 'Partial Content'; break;
                    case 300: $text = 'Multiple Choices'; break;
                    case 301: $text = 'Moved Permanently'; break;
                    case 302: $text = 'Moved Temporarily'; break;
                    case 303: $text = 'See Other'; break;
                    case 304: $text = 'Not Modified'; break;
                    case 305: $text = 'Use Proxy'; break;
                    case 400: $text = 'Bad Request'; break;
                    case 401: $text = 'Unauthorized'; break;
                    case 402: $text = 'Payment Required'; break;
                    case 403: $text = 'Forbidden'; break;
                    case 404: $text = 'Not Found'; break;
                    case 405: $text = 'Method Not Allowed'; break;
                    case 406: $text = 'Not Acceptable'; break;
                    case 407: $text = 'Proxy Authentication Required'; break;
                    case 408: $text = 'Request Time-out'; break;
                    case 409: $text = 'Conflict'; break;
                    case 410: $text = 'Gone'; break;
                    case 411: $text = 'Length Required'; break;
                    case 412: $text = 'Precondition Failed'; break;
                    case 413: $text = 'Request Entity Too Large'; break;
                    case 414: $text = 'Request-URI Too Large'; break;
                    case 415: $text = 'Unsupported Media Type'; break;
                    case 500: $text = 'Internal Server Error'; break;
                    case 501: $text = 'Not Implemented'; break;
                    case 502: $text = 'Bad Gateway'; break;
                    case 503: $text = 'Service Unavailable'; break;
                    case 504: $text = 'Gateway Time-out'; break;
                    case 505: $text = 'HTTP Version not supported'; break;
                    default:
                        exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
                }

                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

                header($protocol . ' ' . $code . ' ' . $text);

                $GLOBALS['http_response_code'] = $code;

            } else {

                $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);

            }

            return $code;

        }
    }











   $log = "";
   $logstart = getcwd();
   function logit( $it )
   {
      global $log;
      global $logstart;
      $fp2 = fopen( $logstart. "/logs.txt", "a" );
      $d = date( "Y-m-d H:i:s");
      fwrite( $fp2, $d . ">" . $it . "\n" );
      fclose( $fp2 );
   }
   
   // returns true if $str begins with $sub
   function beginsWith( $str, $sub )
   {
       return ( substr( $str, 0, strlen( $sub ) ) == $sub );
   }
   
   // trims off x chars from the front of a string
   // or the matching string in $off is trimmed off
   function trimOffFront( $off, $str )
   {
       if( is_numeric( $off ) )
           return substr( $str, $off );
       else
           return substr( $str, strlen( $off ) );
   }
   function BuildQuery( $view, $params, $db )
   {
   	logit( "BuildQuery " . $view ."!!");
   	$pubFields = "*";
   	$statement = $db->prepare( "SELECT pubFields FROM publish WHERE tablename = :tab;" );
   	$statement->bindValue( ':tab', $view );
   	$res_pub_fields = $statement->execute();
   	if( ($res_pub_fields instanceof Sqlite3Result) )
   	{
   		logit( "BuildQuery instance of true " );
   		if( $row = $res_pub_fields->fetchArray(SQLITE3_ASSOC) )
   		{
   			$pubFields = $row[ 'pubFields'];
   			logit( "BuildQuery got row" . $pubFields );
   		}
   	}
   	$res = "SELECT " . $pubFields . " FROM " . $view;
   	logit( "BuildQuery" . $res );
   	$doneWhere = false;
   	foreach( $params as $key => $value )
   	{
   		if( $doneWhere == false ){
   			$res = $res . " WHERE ";
   			$doneWhere = true;
   		}
   		else
   		{
   			$res = $res . " AND ";
   		}
   		$res = $res . $key . ' = ?'; # weakness is if key has values.
   			
   	}
   	logit( "BuildQuery" . $res );
   	$res = $res . ";";
   	$stmt = $db->prepare( $res );
   	$i = 1;
   	foreach( $params as $key => $value )
   	{
   		$stmt->bindValue( $i, $value );
   		$i = $i + 1;
   	}
   
   	return $stmt;
   }
    
   function BuildDelete( $view, $params, $db )
   {
   	logit( "BuildDelete " . $view ."!!");

   	$res = "DELETE FROM " . $view;
   	logit( "BuildDelete" . $res );
   	$doneWhere = false;
   	foreach( $params as $key => $value )
   	{
   		if( $doneWhere == false ){
   			$res = $res . " WHERE ";
   			$doneWhere = true;
   		}
   		else
   		{
   			$res = $res . " AND ";
   		}
   		$res = $res . $key . ' = ?'; # weakness is if key has values.
   			
   	}
   	logit( "BuildDelete" . $res );
   	$res = $res . ";";
   	$stmt = $db->prepare( $res );
   	$i = 1;
   	foreach( $params as $key => $value )
   	{
   		$stmt->bindValue( $i, $value );
   		$i = $i + 1;
   	}
   
   	return $stmt;
   }
    
   
   
   
   function ProcessPut( )
   {
      logit( "ProcessPut()" . " " . $_SERVER[ "REQUEST_METHOD" ] . " " . $_SERVER[ "REQUEST_URI" ] );
       
      $mydata = file_get_contents( 'php://input' );
       
      logit( "mydata = " . $mydata );
      $params = array();
      $view = GetViewName( $_SERVER[ "PATH_INFO"], $params  ); # e.g. users (also add id =  num to $params where /users/num

      $statement = $db->prepare( "SELECT writeTemplate  FROM writeTemplate WHERE tablename = :tab;" );
      $statement->bindValue( ':tab', $view );	       
      $res_template = $statement->execute();
      $query = "";
      if( ($res_pub_fields instanceof Sqlite3Result) )
      {
          logit( "BuildQuery instance of true " );
   	  if( $row = $res_pub_fields->fetchArray(SQLITE3_ASSOC) )
   	  {
 	      $query = $row[ 'writeTemplate'];
 	      logit( "post got row" . $writeTempate );
   	  }
 	  if( $query != "" ) {
	      $statement = $db->prepare( "SELECT fieldText  FROM writeFields WHERE tablename = :tab;" );
              $statement->bindValue( ':tab', $view );
	      $res_fields = $statement->execute();
	      $statement_put  =$db->prepare( $writeTemplate );
	      if( $res_fields instanceof Sqlite3Result ){
	      	  while( $row = $res_fields->fetchArray( SQLITE3_ASSOC ) ){
	          	 $statement_put->bindValue( ':' + $row[ 'fieldText' ], $mydata[ $row['fieldText'] ] );
	          }
	      	  $statement_put->execute();
              }
	  }	       
       }
   }
   function GetViewName( $path, &$params )
   {
   		$normalized = trim( $path, "/"); # remove leading and trailing '/'
   		# now split at the '/'
   		$normalized = explode( "/", $normalized );
   		$ct = count($normalized );
   		logit( "ct = " . $ct );

   		if( $ct > 1 )
   		{
   			$id = $normalized[ 1];
   			$params[ "ID" ] = $id;
   			unset( $normalized[ 1] );
   		}
   		$res =  $normalized[0];
   		return $res;
   
   }
   function GetTail( $path )
   {
   		$normalized = trim( $path, "/"); # remove leading and trailing '/'
   		# now split at the '/'
   		$normalized = explode( "/", $normalized );
   		$ct = count($normalized );
   		logit( "ct = " . $ct );

   		if( $ct > 1 )
   		{
   			unset( $normalized[ 1] );
   		}
		unset( $normalized[0] );
   		return $normalized;
   
   }
   function ProcessGet( $db )
   {
   	# PATH_INFO is name of table (view)
   	# QUERY_INFO is fields restriction.
   		$params = array();
       logit( "ProcessGet()" . " " . $_SERVER[ "PATH_INFO" ] . " " . $_SERVER[ "REQUEST_URI" ] );

       parse_str( $_SERVER["QUERY_STRING"], $params ); # convert a=xxx&b=y... into key value array $params
       $view = GetViewName( $_SERVER[ "PATH_INFO"], $params  ); # e.g. users (also add id =  num to $params where /users/num
       $tail = GetTail( $_SERVER[ "PATH_INFO" ] );
   	   $q = BuildQuery( $view, $params, $db );

   	   $result = $q->execute();
   	   
   	   $rows = array();
   	   
   	   while( $row = $q->fetch(PDO::FETCH_ASSOC) ){
   	   	$rows[] = $row;
   	   }
   	   	
   	   print json_encode( $rows );
#   	   logit( json_encode( $rows ) );
   	   	
   }
   function WriteRow( $db, $res_fields, $query, $mydata, &$ids )
   {

        $statement_put  =$db->prepare( $query );
	$res_fields->reset();
	while( $row = $res_fields->fetchArray( SQLITE3_ASSOC ) ){
           logit( "Binding field " . $row[ 'fieldText'] . " with value " . $mydata->{ $row['fieldText'] } );
           $statement_put->bindValue( ':' . $row[ 'fieldText' ], $mydata->{ $row['fieldText'] } );
        }
 	$statement_put->execute();
 	$id = $db->lastInsertRowID();
	logit( "id = " . $id );
	$ids[] = $id;

   }
   
   function ProcessPost( $db )
   {

      logit( "ProcessPost()" . " " . $_SERVER[ "PATH_INFO" ] . " " . $_SERVER[ "REQUEST_URI" ] );


      $params = array();
      $view = GetViewName( $_SERVER[ "PATH_INFO"], $params  ); # e.g. users (also add id =  num to $params where /users/num
      logit( "ProcessPost()" . " " . $_SERVER[ "REQUEST_METHOD" ] . " " . $_SERVER[ "REQUEST_URI" ] . " " . $view );
       
      $mydata = json_decode(file_get_contents( 'php://input' ) );
       
      logit( "mydata = "  );

      $statement = $db->prepare( "SELECT writeTemplate  FROM writeTemplate WHERE tablename = :tab;" );
      $statement->bindValue( ':tab', $view );	       
      $res_template = $statement->execute();
      $query = "";
      if( ($res_template instanceof Sqlite3Result) )
      {
          logit( "BuildQuery instance of true " );
   	  if( $row = $res_template->fetchArray(SQLITE3_ASSOC) )
   	  {
 	      $query = $row[ 'writeTemplate'];
 	      logit( "post got row " . $query );
   	  }
 	  if( $query != "" ) {
	      $statement = $db->prepare( "SELECT fieldText  FROM writeFields WHERE tablename = :tab;" );
              $statement->bindValue( ':tab', $view );
	      $res_fields = $statement->execute();

	      if( $res_fields instanceof Sqlite3Result ){
	         $ids = array();

                 if( is_array( $mydata ) ){
		     foreach( $mydata as $data ){
	      	     	 WriteRow( $db, $res_fields, $query, $data, $ids );
	             }
	      	 } else {
	      	     WriteRow( $db, $res_fields, $query, $mydata, $ids );

		 }
		  header( 'Location: ' . $_SERVER[ 'SCRIPT_NAME']. '/' . $view . '/' . $id );
		  logit( json_encode( $ids ) );
		  print( json_encode( $ids ) );
              }
	  }	       
       }




       $view = GetViewName( $_SERVER[ "PATH_INFO"], $params  ); # e.g. users (also add id =  num to $params where /users/num
       $tail = GetTail( $_SERVER[ "PATH_INFO" ] );
       logit( $view );
       
   }

   function ProcessDelete( $db )
   {
   	# PATH_INFO is name of table (view)
   	# QUERY_INFO is fields restriction.
       $params = array();
       logit( "ProcessDelete()" . " " . $_SERVER[ "PATH_INFO" ] . " " . $_SERVER[ "REQUEST_URI" ] );
       parse_str( $_SERVER["QUERY_STRING"], $params ); # convert a=xxx&b=y... into key value array $params
       $view = GetViewName( $_SERVER[ "PATH_INFO"], $params  ); # e.g. users (also add id =  num to $params where /users/num
       $q = BuildDelete( $view, $params, $db );

#       $result = $q->execute();
   	   
       
   }


class MyDB extends SQLite3
{
    function __construct()
    {
        $this->open($_SERVER['DOCUMENT_ROOT' ] . '/../db/train_progress.db' );
    }
}
	


#var_dump( explode( "/", "/users/"));
#var_dump( explode( "/", "/users/13"));
#var_dump( explode( "/", "/users?hello=a&world=b"));

$db = NULL;
try {
      $db = new PDO( $sql_dsn, $sql_user, $sql_pass );
} catch ( PDOException $e ) {
  echo 'connection Failed: ' . $e->GetMessage();
}

#$db = new MyDB();
#$db->exec( "CREATE VIEW IF NOT EXISTS vwTrainLocs AS " .
#	     "select id as id, ".
#	     "    toc_id, ".
#	     "    strftime('%s', evtTime ) as whenTime, ".
# 	     "    SUBSTR(tme,1,4) as station1_tme, ".
#	     "     sta1.Lat as Lat, ".
#	     "     sta1.Long as Long, ".
#	     "     SUBSTR(next_tme,1,4) as station2_tme, ".
#	     "     sta2.Lat AS next_Lat, ".
#	     "     sta2.Long as next_long, ".
#	     "     wp.seq_id as seq_id ".
#	     "  FROM WayPoints as wp ".
#	     "  JOIN Stations2 as sta1 ON wp.stanox = sta1.stanox ". 
#	     "  join Stations2 as sta2 ON wp.next_stanox = sta2.stanox;" );
#$db->exec( "CREATE VIEW IF NOT EXISTS vwTrainTimeTableAll AS " .
#	   " SELECT sg2.sched_seq as seq, ".
#	   "   sg2.sch_id as sch_id, ".
#	   "   sg2.type as type, ".
#	   "   sg2.tiploc as tiploc, ".
#	   "   sg2.tme as tme, ".
#	   "   sg2.stanox as stanox," .
#	   " sta.StationName as Name, " .
#	   " ts.train_id as id, " .
#	   " sta.Lat AS lat, ".
#	   " sta.Long AS long ".
#	   " FROM Segments2 AS sg2 ".
#	   " JOIN Stations2 AS sta ON sg2.stanox = sta.stanox ".
#	   " JOIN Schedule AS sch ON sg2.sch_id = sch.sch_id " .
#	   " JOIN TrainSchedule AS ts ON ts.train_uid = sch.train_uid AND ts.schedule_start_date = sch.schedule_start_date;" );
#$db->exec( "CREATE VIEW IF NOT EXISTS vwTrainTimeTable AS " .
#	   " SELECT sg2.sched_seq as seq, sg2.sch_id as sch_id, sg2.type as type, sg2.tiploc as tiploc, sg2.tme as tme, sg2.stanox as stanox," .
#	   " sta.StationName as Name, ts.train_id as id " .
#	   " FROM Segments2 AS sg2 ".
#	   " JOIN Stations2 AS sta ON sg2.stanox = sta.stanox ".
#	   " JOIN Schedule AS sch ON sg2.sch_id = sch.sch_id " .
#	   " JOIN TrainSchedule AS ts ON ts.train_uid = sch.train_uid AND ts.schedule_start_date = sch.schedule_start_date WHERE tme IS NOT NULL;" );
#$db->exec( "CREATE TABLE IF NOT EXISTS stanoxToStanox (id INTEGER PRIMARY KEY AUTOINCREMENT, stanox1 TEXT, stanox2 TEXT );" );
#$db->exec( "CREATE INDEX IF NOT EXISTS idx_stanox2stanox_key ON stanoxTostanox (stanox1, stanox2 );" );
#$db->exec( "CREATE TABLE IF NOT EXISTS publish (tableName TEXT, pubFields TEXT );" );
#$db->exec( "CREATE TABLE IF NOT EXISTS writeTemplate( tableName TEXT PRIMARY KEY, writeTemplate TEXT );" );
#$db->exec( "CREATE TABLE IF NOT EXISTS writeFields( tableName TEXT, fieldText TEXT );" );
#$db->exec( "DROP VIEW IF EXISTS vwTrainLocsO;" );
#$db->exec( "CREATE VIEW IF NOT EXISTS vwTrainLocsO AS " . 
#	   	   "SELECT t.train_id as id, Lat, long, toc_id, st.STANOX as stanox, sta.tiploccode as tipcode, ".
#		      "DateTime( whatTime, 'unixepoch') as WhatTime ".
#		      "FROM trains t ".
#		      "JOIN Stanox st ON t.stanox = st.STANOX ".
#		      "JOIN Stations sta ON sta.tiploccode=st.tiploc ".
#		      "ORDER BY whatTime DESC;" );
# INSERT INTO writeTemplate (tableName, writeTemplate ) VALUES ( 'stanoxTostanox', "INSERT INTO stanoxTostanox ( stanox1, stanox2 ) VALUES ( :stanox1, :stanox2 );" );
#INSERT INTO writeFields (tableName, fieldText ) VALUES ( 'stanoxTostanox', 'stanox1' );
#INSERT INTO writeFields (tableName, fieldText ) VALUES ( 'stanoxTostanox', 'stanox2' );


$params = array();
#GetViewName( "/users/3123", $params );
#GetViewName( "/users?username=1231", $params);


logit( "Headers :-" );
foreach($_SERVER as $key => $value) {
   logit( $key . "=>". $value );
}
logit( "Headers done" );


    session_start();
    header( "Content-Type: application/json"  );
    
    $m = $_SERVER["REQUEST_METHOD" ];
    logit( $_SERVER[ "REQUEST_URI" ] );
#    $session = $_REQUEST['sessionid'];
#    $data = $_REQUEST['datapacket' ];
#    logit( $m . " " . $data . " " . $session );
    switch( $m ){
        case "POST" :
            ProcessPost( $db );
	    http_response_code( 201 );
            break;
            
        case "PUT" :
            ProcessPut();
	    http_response_code( 201 );
            break;
            
        case "GET" :
            ProcessGet( $db );
            break;
            
        case "DELETE" :
            ProcessDelete( $db );
            break;
    }
?>
