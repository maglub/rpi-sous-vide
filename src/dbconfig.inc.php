<?php

// static variables defining the database location
static $db_target = '/var/lib/rpi-sous-vide/db/app.sqlite3';
static $db_dir = '/var/lib/rpi-sous-vide/db';

// PDO database object connecting to sqlite3 db
$db = new PDO('sqlite:'.$db_target);
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$db->setAttribute( PDO::ATTR_EMULATE_PREPARES, false);
$db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

?>

