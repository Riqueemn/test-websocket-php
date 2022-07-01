<?php

include("../api_2/conexao.php");
include("../api_2/lobby.php");

$json = file_get_contents("php://input");


$lobbys = new Lobby();    

$obj = $lobbys->LobbyStatus($mysqli);

echo $obj;


?>