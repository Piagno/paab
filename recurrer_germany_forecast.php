<?php
require 'db.php';
require 'inner_api.php';
if(isset($argv)){
	if(isset($argv[1])){
		sleep($argv[1]);
	}
}
$forecast = simplexml_load_string(file_get_contents('https://tool.piagno.ch/paab/fetch_bad_forecast.php'));
$req = $db->prepare('SELECT * FROM paab_trains WHERE train_id LIKE "G%"');
$req->execute();
$now = strtotime('now');
foreach($req->fetchAll() as $stored_train){
	$found = false;
	foreach($forecast as $forecast_train){
		if(substr($stored_train['train_id'],1) == $forecast_train['id']){
			$found = true;
			//echo('found the train: '.$stored_train['train_number'].' ');
			if($forecast_train->dp['cs'] == 'c'){
				$stored_train['drives'] = 'outage';
				if(strtotime($stored_train['departure_time'].' + '.($stored_train['normal_run_time'] + 5).' minutes') < $now){
					//echo('train is outage and should already have arrived - deleting it');
					remove_train($stored_train);
				}else{
					//echo('train is outage - updating it');
					add_update_train($stored_train);
				}
			}else{
				$planned_departure_time = new DateTime($stored_train['departure_time']);
				$dp_raw = $forecast_train->dp['ct'];
				$forecast_departure_time = new DateTime('20'.substr($dp_raw,0,2).'-'.substr($dp_raw,2,2).'-'.substr($dp_raw,4,2).' '.substr($dp_raw,6,2).':'.substr($dp_raw,8,2).':00');
				$retard = $planned_departure_time->diff($forecast_departure_time);
				$stored_train['estimated_retard'] = $retard->format('%i');
				if(strtotime($stored_train['departure_time'].' + '.($stored_train['estimated_retard'] + $stored_train['normal_run_time'] + 5).' minutes') < $now){
					//echo('train should have arrived - removing it');
					remove_train($stored_train);
				}elseif(strtotime($stored_train['departure_time'].' + '.($stored_train['estimated_retard'] + 1).' minutes') < $now){
					$stored_train['drives'] = 'driven';
					//echo('train should have driven - updating it');
					add_update_train($stored_train);
				}else{
					//echo("train shouldn't have driven - updating it");
					add_update_train($stored_train);
				}
			}
			//echo('<br />');
		}
	}
	if($found == false){
		//echo("couldn't find train ".$stored_train['train_number'].' - deleting it');
		remove_train($stored_train);
	}
}
