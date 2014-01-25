<?php
include 'settings_server.php';

require_once("phpcoord-2.3.php"); # convert easting, northing to lat long
$db = NULL;
try {
      $db = new PDO(  $sql_dsn, $sql_user, $sql_pass );
} catch ( PDOException $e ) {
  echo 'connection Failed: ' . $e->GetMessage();
}

$row = 1;
# from  http://data.gov.uk/dataset/naptan

if (($handle = fopen("RailReferences.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
	$os1 = new OSRef( $data[6], $data[7] );
	$ll1 = $os1->toLatLng();
        echo "$num fields in line $row: ". $os1->toString(). " ". $ll1->toString() ." \n";
        $row++;
#        for ($c=0; $c < $num; $c++) {
#            echo $data[$c] . "<br />\n";
#        }
	$stmt = $db->prepare( 'INSERT INTO Stations (TiplocCode, CrsCode, StationName, Easting, Northing, Lat, Long_ ) VALUES ( ?,?,?,  ?,?,  ?,? );' );
	$stmt->bindValue( 1, $data[1] );
	$stmt->bindValue( 2, $data[2] );
	$stmt->bindValue( 3, $data[3] );

	$stmt->bindValue( 4, $data[6] );
	$stmt->bindValue( 5, $data[7] );

	$stmt->bindValue( 6, $ll1->lat );
	$stmt->bindValue( 7, $ll1->lng );
	$stmt->execute();
    }
    fclose($handle);
}
?>