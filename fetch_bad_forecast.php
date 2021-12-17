<?php
header('Content-Type: application/xml; charset=utf-8');
echo(file_get_contents('https://iris.noncd.db.de/iris-tts/timetable/fchg/8000026'));
?>