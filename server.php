<?php

require './vendor/autoload.php';


use Meet\MeetServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;


$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new MeetServer()
        )
    ),
    9990
);

$server->run();