<?php

$fecha = new \DateTime();
//$fecha->modify('+1 hours');

$timestamp = $fecha->format('YmdHis');

echo $timestamp;