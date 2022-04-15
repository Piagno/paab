<?php
require 'db.php';
require 'inner_api.php';
function create_request_data($time_input){
	$time = strtotime($time_input);
	return array(
		'hour' => date('ymd',$time),
		'minute' => date('H',$time)
	);
}
$requests = array(
	create_request_data('now'),
	create_request_data('+1 hour'),
	create_request_data('+2 hours'),
	create_request_data('+3 hours'),
	create_request_data('+4 hours'),
	create_request_data('+5 hours'),
	create_request_data('+6 hours'),
	create_request_data('+7 hours')
);
foreach($requests as $request){
	$response = file_get_contents('https://iris.noncd.db.de/iris-tts/timetable/plan/8000026/'.$request['hour'].'/'.$request['minute']);
	$trains = simplexml_load_string($response);
	foreach($trains as $train){
		if(str_contains($train->dp['ppth'],'Basel SBB')){
			$train_type = trim($train->tl['c'].' '.$train->dp['l']);
			if($train->tl['c'] == 'SBB'){
				$train_type = $train->dp['l'];
			}
			$travel_time = 8;
			if($train_type == 'S6'){
				$travel_time = 6;
			}
			$dp_raw = $train->dp['pt'];
			$departure_time = '20'.substr($dp_raw,0,2).'-'.substr($dp_raw,2,2).'-'.substr($dp_raw,4,2).' '.substr($dp_raw,6,2).':'.substr($dp_raw,8,2).':00';
			add_update_train(array(
				'train_id' => ('G'.$train['id']),
				'train_number' => $train->tl['n'],
				'departure_time' => $departure_time,
				'destination' => $train->dp['ppth'],
				'drives' => 1,
				'train_type' => $train_type,
				'departure_station' => 'Basel Bad Bf',
				'normal_run_time' => $travel_time
			),true);
		}
	}
}
?>
