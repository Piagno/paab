<?php
require 'db.php';
require 'inner_api.php';
if(isset($argv)){
	if(isset($argv[1])){
		sleep($argv[1]);
	}
}
$forecast = simplexml_load_string(file_get_contents('https://iris.noncd.db.de/iris-tts/timetable/fchg/8000026'));
$req = $db->prepare('SELECT * FROM paab_trains WHERE train_id LIKE "G%"');
$req->execute();
$now = strtotime('now');
$db_trains = $req->fetchAll();
foreach($db_trains as $stored_train){
	$found = false;
	foreach($forecast as $forecast_train){
		if(substr($stored_train['train_id'],1) == $forecast_train['id']){
			$found = true;
			if($forecast_train->dp['cs'] == 'c'){
				$stored_train['drives'] = 'outage';
				if(strtotime($stored_train['departure_time'].' + '.($stored_train['normal_run_time'] + 5).' minutes') < $now){
					remove_train($stored_train);
				}else{
					add_update_train($stored_train);
				}
			}else{
				if($forecast_train->dp == ''){
					$stored_train['estimated_retard'] = 0;
				}else{
					$planned_departure_time = new DateTime($stored_train['departure_time']);
					$dp_raw = $forecast_train->dp['ct'];
					if($dp_raw == null){
						$stored_train['estimated_retard'] = 0;
					}else{
						$forecast_departure_time = new DateTime('20'.substr($dp_raw,0,2).'-'.substr($dp_raw,2,2).'-'.substr($dp_raw,4,2).' '.substr($dp_raw,6,2).':'.substr($dp_raw,8,2).':00');
						$retard = $planned_departure_time->diff($forecast_departure_time);
						$stored_train['estimated_retard'] = $retard->format('%i');
					}
				}
				if(strtotime($stored_train['departure_time'].' + '.($stored_train['estimated_retard'] + $stored_train['normal_run_time'] + 5).' minutes') < $now){
					remove_train($stored_train);
				}elseif(strtotime($stored_train['departure_time'].' + '.($stored_train['estimated_retard'] + 1).' minutes') < $now){
					$stored_train['drives'] = 'driven';
					add_update_train($stored_train);
				}else{
					add_update_train($stored_train);
				}
			}
		}
	}
	if($found == false){
		remove_train($stored_train);
	}
}
foreach($forecast as $forecast_train){
	if(str_contains($forecast_train->dp['ppth'],'Basel SBB')){
		$add = true;
		foreach($db_trains as $stored_train){
			if($forecast_train['id'] == substr($stored_train['train_id'],1)){$add = false;}
		}
		if($add){
			$dp_raw = $forecast_train->dp['pt'];
			$departure_time = '20'.substr($dp_raw,0,2).'-'.substr($dp_raw,2,2).'-'.substr($dp_raw,4,2).' '.substr($dp_raw,6,2).':'.substr($dp_raw,8,2).':00';
			$additional_info = null;
			if($forecast_train->ref){
				$additional_info = 'Ersatzzug für '.$forecast_train->ref->tl['c'].' '.$forecast_train->ref->tl['n'];
			}
			add_update_train(array(
				'train_id' => ('G'.$forecast_train['id']),
				'train_number' => $forecast_train->tl['n'],
				'departure_time' => $departure_time,
				'estimated_retard' => 0,
				'destination' => $forecast_train->dp['ppth'],
				'drives' => 1,
				'effective_departure_time' => null,
				'train_type' => $forecast_train->tl['c'],
				'departure_station' => 'Basel Bad Bf',
				'normal_run_time' => 8,
				'additional_info' => $additional_info
			));
		}
	}
}
