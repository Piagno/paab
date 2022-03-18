<?php
require 'db.php';
require 'inner_api.php';
if(isset($argv)){
	if(isset($argv[1])){
		sleep($argv[1]);
	}
}
$trains_mse = json_decode(file_get_contents('https://www.garesetconnexions.sncf/fr/train-times/MSE/departure'));
//$trains_stl = json_decode(file_get_contents('https://www.garesetconnexions.sncf/fr/train-times/STL/departure'));
$req = $db->prepare('SELECT * FROM paab_config WHERE parameter = "Fupdated"');
$req->execute();
$db_updated = ($req->fetchAll())[0]['value'];
if(!is_numeric ($db_updated)){$db_updated = 9999;} 
$fetch_updated = substr($trains_mse->updated,0,2).substr($trains_mse->updated,3,2);
if(($fetch_updated >= $db_updated && $fetch_updated - $db_updated < 1000) || ($db_updated > 1600 && $fetch_updated < 1000) || $db_updated == 9999){
	$today = date('Y-m-d ');
	$tomorrow = date('Y-m-d ',strtotime("+1 days"));
	$yesterday = date('Y-m-d ',strtotime("-1 days"));
	$depature_time_prepared = $today.($trains_mse->updated).':00';
	$req = $db->prepare('UPDATE paab_config SET value = :updated WHERE parameter = "Fupdated"');
	$req->bindParam(':updated',$fetch_updated);
	$req->execute();
	$req = $db->prepare('SELECT * FROM paab_trains WHERE train_id LIKE "F%"');
	$req->execute();
	// REMOVE PASSED TRAINS
	foreach($req->fetchAll() as $stored_train){
		$passed = true;
		foreach($trains_mse->trains as $fetched_train){
			if($stored_train['train_number'] == $fetched_train->num){
				$passed = false;
			}
		}
		if($passed){
			// Handle outage train
			if($stored_train['drives'] == 'outage'){
				$delete_train_time = strtotime($stored_train['departure_time']) + ($stored_train['normal_run_time'] * 60) + 300;
				if($delete_train_time < strtotime('now')){remove_train($stored_train);}
			}
			// Set effective departure time
			if($stored_train['effective_departure_time'] == null && $stored_train['drives'] == 1){
				run_train($stored_train,$depature_time_prepared);
			}
			// Remove if necessary
			if($stored_train['drives'] == 'driven'){
				$delete_train_time = strtotime($stored_train['effective_departure_time']) + ($stored_train['normal_run_time'] * 60) + 300;
				if($delete_train_time < strtotime('now')){remove_train($stored_train);}
			}
		}
	}
	// ADD/UPDATE TRAINS
	$relevant_trains = array('BALE','BALE/BASEL','BALE PAR BUS','ZURICH');
	$evening = false;
	if(date('H') > 12){$evening = true;}
	foreach($trains_mse->trains as $train){
		if(in_array($train->origdest,$relevant_trains)){
			$travel_time = 31;
			if($train->type == 'TER' && substr($train->num,0,3) == '962'){
				$travel_time = 22;
			}elseif($train->type == 'TGV' || $train->type == 'TGV Lyria'){
				$travel_time = 20;
			}
			$departure_time;
			if($evening){
				if(substr($train->heure,0,1) < 1){
					$departure_time = $tomorrow.$train->heure.':00';
				}else{
					$departure_time = $today.$train->heure.':00';
				}
			}else{
				if(substr($train->heure,0,1) == 2){
					$departure_time = $yesterday.$train->heure.':00';
				}else{
					$departure_time = $today.$train->heure.':00';
				}
			}
			$estimated_retard = 0;
			$drives = true;
			$additional_info = null;
			switch(true){
				case ($train->infos == 'sup'):
					$drives = 'outage';
					break;
				case (substr($train->infos,-3) == 'min'):
					$estimated_retard = (int)substr($train->infos,0,2);
					break;
				case (substr($train->infos,1,1) == 'h'):
					$estimated_retard = (((int)substr($train->infos,0,1) * 60) + (int)substr($train->infos,2,2));
					break;
				default:
					$additional_info = $train->infos;
			}
			add_update_train(array(
				'train_id' => ('F'.$train->num),
				'train_number' => $train->num,
				'departure_time' => $departure_time,
				'estimated_retard' => $estimated_retard,
				'destination' => $train->origdest,
				'drives' => $drives,
				'effective_departure_time' => null,
				'train_type' => $train->type,
				'departure_station' => 'Mulhouse',
				'normal_run_time' => $travel_time,
				'additional_info' => $additional_info
			));
		}
	}
}
?>
