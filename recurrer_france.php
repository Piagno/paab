<?php
require 'db.php';
require 'inner_api.php';
if(isset($argv)){
	if(isset($argv[1])){
		sleep($argv[1]);
	}
}
$trains_mse = json_decode(file_get_contents('https://sncf-appligares.azurewebsites.net/API/PIV/Departures/0087182063'));
$req = $db->prepare('SELECT * FROM paab_trains WHERE train_id LIKE "F%"');
$req->execute();
foreach($req->fetchAll() as $train_paab){
	$passed = true;
	foreach($trains_mse as $train_mse){
		if($train_paab['train_number'] == $train_mse->trainNumber){
			$passed = false;
		}
	}
	if($passed){
		// Handle outage train
		if($train_paab['drives'] == 'outage' || $train_paab['drives'] || 'outage_stl'){
			$delete_train_time = strtotime($train_paab['departure_time']) + ($train_paab['normal_run_time'] * 60) + 300;
			if($delete_train_time < strtotime('now')){remove_train($train_paab);}
		}
		// Set effective departure time
		if($train_paab['effective_departure_time'] == null && $train_paab['drives'] == 1){
			run_train($train_paab,date("Y-m-d H:i:s"));
		}
		// Remove if necessary
		if($train_paab['drives'] == 'driven'){
			$delete_train_time = strtotime($train_paab['effective_departure_time']) + ($train_paab['normal_run_time'] * 60) + 300;
			if($delete_train_time < strtotime('now')){remove_train($train_paab);}
		}
	}
}
foreach($trains_mse as $train){
	if($train->trainMode == "TRAIN"){
		if($train->traffic->destination == "Basel SBB" || $train->traffic->destination == "Zuerich HB" || $train->traffic->oldDestination == "Basel SBB" || $train->traffic->oldDestination == "Zuerich HB" || $train->traffic->destination == 'Bâle - SBB' || $train->traffic->oldDestination == 'Bâle - SBB' || $train->traffic->destination == 'Zürich - Hauptbahnhof' || $train->traffic->oldDestination == 'Zürich - Hauptbahnhof' || $train->traffic->destination == 'Saint-Louis' || $train->traffic->oldDestination == 'Saint-Louis'){
			if($train->trainType == "Lyria" || $train->trainType == "Train TER"){
				$drives = 1;
				$additional_info = '';
				switch($train->informationStatus->trainStatus){
					case "NORMAL":
					case "Ontime":
					case "RETARD":
						if($train->traffic->destination == "Saint-Louis"){
							$drives = 'outage_stl';
						}
						break;
					case "SUPPRESSION_TOTALE":
					case "SUPPRESSION_PARTIELLE":
						$drives = 'outage';
						break;
					default:
						$additional_info = $train->informationStatus->trainStatus;
				}
				$travel_time = 31;
				if($train->trainType == 'Train TER' && substr($train->trainNumber,0,3) == '962'){
					$travel_time = 22;
				}elseif($train->trainType == 'Lyria'){
					$travel_time = 20;
				}
				$departure_time = explode("T",$train->scheduledTime)[0]." ".substr(explode("T",$train->scheduledTime)[1],0,8);
				$estimated_retard = $train->informationStatus->delay;
				if($estimated_retard == null){$estimated_retard = 0;}
				add_update_train(array(
					'train_id' => ('F'.$train->trainNumber),
					'train_number' => $train->trainNumber,
					'departure_time' => $departure_time,
					'estimated_retard' => $estimated_retard,
					'destination' => $train->traffic->destination,
					'drives' => $drives,
					'effective_departure_time' => null,
					'train_type' => $train->trainType,
					'departure_station' => 'Mulhouse',
					'normal_run_time' => $travel_time,
					'additional_info' => $additional_info
				));
			}
		}
	}
}
