<?php

require './vendor/autoload.php';


use Meet\MeetServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;


/*
$app = new Ratchet\App('localhost', 9990);
$app->route('/meet', new MeetServer, ['echo-protocol']);
//$app->route('/echo', new Ratchet\Server\EchoServer, array('*'));
$app->run();
*/


$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new MeetServer()
        )
    ),
    9990
);

$server->run();