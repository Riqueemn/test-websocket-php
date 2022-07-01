<?php

require './vendor/autoload.php';


use Meet\MeetServer;

$app = new Ratchet\App('localhost', 9990);
$app->route('/meet', new MeetServer, ['*']);
$app->run();