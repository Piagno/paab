<?php
header('Content-Type: application/xml; charset=utf-8');
echo(file_get_contents('https://iris.noncd.db.de/iris-tts/timetable/plan/8000026/'.$_GET['date'].'/'.$_GET['hour']));
?>