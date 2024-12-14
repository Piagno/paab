<?php
require 'db.php';
require 'inner_api.php';
require 'secret.php';
if(isset($argv)){
	if(isset($argv[1])){
		sleep($argv[1]);
	}
}
$trains_mse_data = file_get_contents('https://api.sncf.com/v1/coverage/sncf/stop_areas/stop_area:SNCF:85000109/arrivals?count=15&forbidden_uris[]=commercial_mode:additional_service',false,stream_context_create( [
	'http' => [ 'header' => 'Authorization: '.$secret]
] ) );
$trains_mse = json_decode($trains_mse_data);
$arrivals = $trains_mse->arrivals;

$req = $db->prepare('SELECT * FROM paab_trains WHERE train_id LIKE "F%"');
$req->execute();
foreach($req->fetchAll() as $train_paab){
	$passed = true;
	foreach($arrivals as $arrival){
		if($train_paab['train_number'] == $arrival->display_informations->headsign){
			$passed = false;
		}
	}
	if($passed){
		// Handle outage train
		if($train_paab['drives'] == 'outage' || $train_paab['drives'] || 'outage_stl'){
			$delete_train_time = strtotime($train_paab['departure_time']) + ($train_paab['normal_run_time'] * 60) + 300;
			if($delete_train_time < strtotime('now')){remove_train($train_paab);}
		}
		// Set effective departure time or set outage
		if($train_paab['effective_departure_time'] == null && $train_paab['drives'] == 1){
			$departure_time = new DateTime($train_paab['departure_time']);
			$diff = $departure_time->diff(new DateTime('now'));
			if($diff->i > 8){
				add_update_train(array(
					'train_id' => $train_paab['train_id'],
					'train_number' => $train_paab['train_number'],
					'departure_time' => $train_paab['departure_time'],
					'estimated_retard' => $train_paab['estimated_retard'],
					'destination' => $train_paab['destination'],
					'drives' => 'outage',
					'effective_departure_time' => $train_paab['effective_departure_time'],
					'train_type' => $train_paab['train_type'],
					'departure_station' => $train_paab['departure_station'],
					'normal_run_time' => $train_paab['normal_run_time'],
					'additional_info' => $train_paab['additional_info']
				));
			}else{
				run_train($train_paab,date("Y-m-d H:i:s"));
			}
		}
		// Remove if necessary
		if($train_paab['drives'] == 'driven'){
			$delete_train_time = strtotime($train_paab['effective_departure_time']) + ($train_paab['normal_run_time'] * 60) + 300;
			if($delete_train_time < strtotime('now')){remove_train($train_paab);}
		}
	}
}

foreach($arrivals as $arrival){
	if($arrival->display_informations->physical_mode != 'Autocar' && $arrival->display_informations->direction != 'Paris - Gare de Lyon - Hall 1 & 2 (Paris)'){
		$rt = $arrival->stop_date_time->arrival_date_time;
		$estimated_arrival_time = substr($rt,0,4).'-'.substr($rt,4,2).'-'.substr($rt,6,2).' '.substr($rt,9,2).':'.substr($rt,11,2).':'.substr($rt,-2);
		$arrival_time = $estimated_arrival_time;
		if(isset($arrival->stop_date_time->base_arrival_date_time)){
			$rt = $arrival->stop_date_time->base_arrival_date_time;
			$arrival_time = substr($rt,0,4).'-'.substr($rt,4,2).'-'.substr($rt,6,2).' '.substr($rt,9,2).':'.substr($rt,11,2).':'.substr($rt,-2);
		}
	$obj_arrival_time = new DateTime($arrival_time);
	$obj_estimated_arrival_time = new DateTime($estimated_arrival_time);
	$obj_estimated_retard = $obj_arrival_time->diff($obj_estimated_arrival_time);
	$intervalInSeconds = (new DateTime())->setTimeStamp(0)->add($obj_estimated_retard)->getTimeStamp();
	$intervalInMinutes = $intervalInSeconds/60;
	$estimated_retard = $intervalInMinutes;
	$drives = 1;
	add_update_train(array(
		'train_id' => ('F'.$arrival->display_informations->headsign),
		'train_number' => $arrival->display_informations->headsign,
		'departure_time' => $arrival_time,
		'estimated_retard' => $estimated_retard,
		'destination' => $arrival->display_informations->direction,
		'drives' => $drives,
		'effective_departure_time' => null,
		'train_type' => $arrival->display_informations->commercial_mode,
		'departure_station' => '',
		'normal_run_time' => 0,
		'additional_info' => $arrival->display_informations->description
	));
	}
}
