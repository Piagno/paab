<?php
require 'db.php';
$req = $db->prepare('SELECT * FROM paab_trains ORDER BY departure_time ASC');
$req->execute();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($req->fetchAll());
?>
