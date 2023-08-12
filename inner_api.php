<?php
function run_train($train,$effective_run_time){
	global $db;
	$req = $db->prepare('UPDATE paab_trains SET effective_departure_time = :effective_departure_time, drives = "driven" WHERE train_id = :train_id');
	$req->bindParam(':train_id',$train['train_id']);
	$req->bindParam(':effective_departure_time',$effective_run_time);
	$req->execute();
}
function remove_train($train){
	global $db;
	$req = $db->prepare('SELECT latest_major_change FROM paab_trains WHERE train_id = :train_id');
	$req->bindParam(':train_id',$train['train_id']);
	$req->execute();
	$latest_major_change = ($req->fetchAll())[0]['latest_major_change'];
	if($latest_major_change){
		$obj_latest_major_change = new DateTime($latest_major_change);
		$now = new DateTime("now");
		$diff = $obj_latest_major_change->diff($now);
		var_dump($diff);
		if($diff->i > 5 && $now > $obj_latest_major_change){
			$req = $db->prepare('DELETE FROM paab_trains WHERE train_id = :train_id');
			$req->bindParam(':train_id',$train['train_id']);
			$req->execute();
		}
	}else{
		$req = $db->prepare('DELETE FROM paab_trains WHERE train_id = :train_id');
		$req->bindParam(':train_id',$train['train_id']);
		$req->execute();
	}
}
function add_update_train($train,$no_forecast = false){
	global $db;
	$req = $db->prepare('SELECT train_id, drives, latest_major_change FROM paab_trains WHERE train_id = :train_id');
	$req->bindParam(':train_id',$train['train_id']);
	$req->execute();
	if($req->rowCount() == 1){
		$old_train = ($req->fetchAll())[0];
		$latest_major_change = null;
		if($train['drives'] != $old_train['drives']){
			switch($train['drives']){
				case "1":
				case 1:
				case "driven":
					$latest_major_change = $old_train['latest_major_change'];
					break;
				default:
					$latest_major_change = date_format(date_create("now"),"Y-m-d H:i:s");
			}
		}else{
			if($old_train['latest_major_change'] != null){
				$latest_major_change = $old_train['latest_major_change'];
			}
		}
		$req;
		if($no_forecast){
			$req = $db->prepare('UPDATE paab_trains SET train_number = :train_number, departure_time = :departure_time, destination = :destination, drives = :drives, train_type = :train_type, departure_station = :departure_station, normal_run_time = :normal_run_time, latest_major_change = :latest_major_change WHERE train_id = :train_id');
		}else{
			$req = $db->prepare('UPDATE paab_trains SET train_number = :train_number, departure_time = :departure_time, estimated_retard = :estimated_retard, destination = :destination, drives = :drives, effective_departure_time = :effective_departure_time, train_type = :train_type, departure_station = :departure_station, normal_run_time = :normal_run_time, additional_info = :additional_info, latest_major_change = :latest_major_change WHERE train_id = :train_id');
			$req->bindParam(':estimated_retard',$train['estimated_retard']);
			$req->bindParam(':effective_departure_time',$train['effective_departure_time']);
			$req->bindParam(':additional_info',$train['additional_info']);
		}
		$req->bindParam(':train_id',$train['train_id']);
		$req->bindParam(':train_number',$train['train_number']);
		$req->bindParam(':departure_time',$train['departure_time']);
		$req->bindParam(':destination',$train['destination']);
		$req->bindParam(':drives',$train['drives']);
		$req->bindParam(':train_type',$train['train_type']);
		$req->bindParam(':departure_station',$train['departure_station']);
		$req->bindParam(':normal_run_time',$train['normal_run_time']);
		$req->bindParam(':latest_major_change', $latest_major_change);
		$req->execute();
		if($req->rowCount() == 1){
			return true;
		}
	}else{
		$not_driven = false;
		$definitive_arrival;
		if($no_forecast){
			$definitive_arrival = date($train['departure_time'],($train['normal_run_time'] + 5));
		}else{
			if($train['effective_departure_time'] != null){
				$definitive_arrival = date($train['effective_departure_time'],($train['normal_run_time'] + 5));
				
			}else{
				$definitive_arrival = date($train['departure_time'], ($train['estimated_retard'] + $train['normal_run_time'] + 5));
			}
		}
		if($definitive_arrival > date('Y-m-d H:i:s')){
			$req;
			if($no_forecast){
				$req = $db->prepare('INSERT INTO paab_trains (train_id, train_number, departure_time, estimated_retard, destination, drives, train_type, departure_station, normal_run_time) VALUES (:train_id, :train_number, :departure_time, 0, :destination, :drives, :train_type, :departure_station, :normal_run_time)');
			}else{
				$req = $db->prepare('INSERT INTO paab_trains (train_id, train_number, departure_time, estimated_retard, destination, drives, effective_departure_time, train_type, departure_station, normal_run_time, additional_info) VALUES (:train_id, :train_number, :departure_time, :estimated_retard, :destination, :drives, :effective_departure_time, :train_type, :departure_station, :normal_run_time, :additional_info)');
				$req->bindParam(':estimated_retard',$train['estimated_retard']);
				$req->bindParam(':effective_departure_time',$train['effective_departure_time']);
				$req->bindParam(':additional_info',$train['additional_info']);
			}
			$req->bindParam(':train_id',$train['train_id']);
			$req->bindParam(':train_number',$train['train_number']);
			$req->bindParam(':departure_time',$train['departure_time']);
			$req->bindParam(':destination',$train['destination']);
			$req->bindParam(':drives',$train['drives']);
			$req->bindParam(':train_type',$train['train_type']);
			$req->bindParam(':departure_station',$train['departure_station']);
			$req->bindParam(':normal_run_time',$train['normal_run_time']);
			$req->execute();
			if($req->rowCount() == 1){
				return true;
			}
		}
	}
	return false;
}
?>
