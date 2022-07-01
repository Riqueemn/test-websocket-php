<?php

include("../api_2/conexao.php");
include("../api_2/lobby.php");

$lobbys = new Lobby();    

$obj = $lobbys->LobbyStatus($mysqli);

echo $obj;


?>