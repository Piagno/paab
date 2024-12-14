<?php
require 'db.php';
$req = $db->prepare('SELECT * FROM paab_trains ORDER BY departure_time ASC');
$req->execute();
header('Content-Type: application/json; charset=utf-8');
$response = array();
foreach($req->fetchAll() as $train){
	$response[] = array(
		'train_id' => $train['train_id'],
		'train_number' => $train['train_number'],
		'departure_time' => $train['departure_time'],
		'estimated_retard' => strval($train['estimated_retard']),
		'destination' => $train['destination'],
		'drives' => $train['drives'],
		'effective_departure_time' => $train['effective_departure_time'],
		'train_type' => $train['train_type'],
		'departure_station' => $train['departure_station'],
		'normal_run_time' => strval($train['normal_run_time']),
		'additional_info' => $train['additional_info']
	);
}
echo json_encode($response);
?>
