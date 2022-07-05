<?php



namespace Meet;

use voku\db\DB;


use Exception;
use SplObjectStorage;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Serializer\CompactSerializer;

final class MeetServer implements MessageComponentInterface
{
    private $clients;
    private $usersClients;
    private $qtdSuporte;
    private $qtdCliente;
    private $db;
    private $qtdUserSuporte;
    private $qtdUserCliente;
    private $qtdLobbys;
    private $Lobbys;
    private $Suporte;
    private $Cliente;
    private $LobbysEspera;
    private $LobbysEspera2;
    
    private $appID;
    private $kid;
    private $jwt;

    public function __construct()
    {

        $this->clients = new SplObjectStorage();
        $this->usersClients = new SplObjectStorage();
        $this->qtdSuporte = 0;
        $this->qtdCliente = 0;
        $this->qtdUserSuporte = 2;
        $this->qtdUserCliente = 4;
        $this->qtdLobbys = 4;
        $this->LobbysEspera = [];
        $this->LobbysEspera2 = new SplObjectStorage();

        $this->jwt = new JWT();
        
        $this->appID = 'vpaas-magic-cookie-e3d18e07c6b84703a43feca37bc14da3';
        $this->kid = 'vpaas-magic-cookie-e3d18e07c6b84703a43feca37bc14da3/04809e';
        
        $this->db = DB::getInstance('localhost', 'root', '', 'sala_virtual');

        $this->Lobbys = new Lobbys();
        $this->Suporte = new Suporte();
        $this->Cliente = new Cliente();

        $this->Lobbys->IndisponibilizarLobbys();
        $this->Suporte->DeslogarUsersSuporte();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        echo " ;onOpen; ";
        $conn->nome = "";
        $conn->userType = "";
        $conn->lobby = "";
        $conn->isSala = "0";
        $this->clients->attach($conn);

        echo " ;qtd: ".sizeof($this->clients)."; ";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {

       
            //$this->usersClients->attach($obj);

            //echo "User ". $obj->nome . " conectado ";
            $obj = json_decode($msg);

            if($obj->cmd == "sair-sala-suporte"){
                $newConn = $from;
                $newConn->isSala = "0";
                $lobby = $newConn->lobby;
                $newConn->lobby = "";

                foreach ($this->clients as $client) {
                    if($client->lobby == $lobby){
                        $client->send(json_encode(["lobby-sair"]));
                    }
                }


                $this->clients->offsetSet($from, $newConn);
                $this->Lobbys->DisponibilizarLobby($lobby);
            }elseif($obj->cmd == "meet-suporte"){
                if($from->isSala == "0"){
                    $newConn = $from;
                    $lobby = $obj->lobby;
                    $c = null;

                    foreach ($this->clients as $client) {
                        if($client->lobby == $lobby){
                            $c = $client;
                        }
                    }
                    
                    if($c->isSala == "1"){
                        echo "Esta em sala";
                    }elseif($c->isSala == "0"){
                        $newConn->lobby = $obj->lobby;
                        $newConn->isSala = "1";


                        $this->clients->offsetSet($from, $newConn);


                        $roomName = $this->appID."/".$newConn->lobby;

                        $tokenSuporte = $this->jwt->GerarToken("", $newConn->nome, $newConn->lobby, $this->appID, $this->kid, true);

                        $credenciaisSuporte = ["credenciais-suporte", $roomName, $tokenSuporte];

                        $from->isSala = "1";

                        $from->send(json_encode($credenciaisSuporte));


                        foreach ($this->clients as $client) {
                            if($client->lobby == $newConn->lobby && $client->userType == "cliente"){
                                $tokenCliente = $this->jwt->GerarToken("", $client->nome, $client->lobby, $this->appID, $this->kid, false);
                                $credenciaisCliente = ["credenciais-cliente", $roomName, $tokenCliente];
                                $client->isSala = "1";
                                $client->send(json_encode($credenciaisCliente));
                                $this->LobbysEspera2->detach($client);
                            }
                        }

                        $LobbysEspera = $this->ListaClienteEspera();

                        foreach ($this->clients as $client) {
                            if($client->userType == "suporte"){
                                $client->send(json_encode($LobbysEspera));
                            }
                        }
                    }


                    
                } else{
                    echo "Está em atendimento";
                }
                

            } else if ($obj->cmd == "0"){
                $newConn = $from;
                $newConn->nome = $obj->nome;
                $newConn->userType = $obj->userType;
                


                if($obj->userType == "suporte"){

                    $this->qtdSuporte += 1;
                    
                    $this->Lobbys->AddLobbyDisponivel($this->qtdSuporte);
                    
                    $this->clients->offsetSet($from, $newConn);

                    $this->Suporte->LogarUserSuporte($newConn->nome);

                    $LobbysEspera = $this->ListaClienteEspera();
                                    

                    foreach ($this->clients as $client) {
                        if($client->userType == "suporte"){
                            $client->send(json_encode($LobbysEspera));
                        }
                    }

                    echo " ;Suporte ".$from->nome." Entrou; ";

                } else if($obj->userType == "cliente"){
                    $this->qtdCliente += 1;

                    if($this->qtdCliente > $this->qtdSuporte){
                        echo " ;Mais clientes conectados do que suporte; ";
                        $from->send(json_encode(["lobby-sair"]));
                        $from->close();
                    }elseif($this->qtdSuporte == 0){
                        echo " ;Sem Suporte Disponivel; ";
                        $from->send(json_encode(["lobby-sair"]));
                        $from->close();

                    }else{
                        $c = null;
                        foreach ($this->clients as $client) {
                            if($client->lobby == $obj->lobby){
                                $c = $client;
                            }
                        }

                        
                            if($c != null && $c->lobby == $obj->lobby){
                                echo " ;Já existe cliente nesse lobby; ";

                                $from->send(json_encode(["lobby-sair"]));
                                $from->close();
                            } else{

                                echo " ;Lobby disponivel; ";

                                $newConn->lobby = $obj->lobby;

                                if($this->Lobbys->VerificarDisponibilidadeLobby($newConn->lobby) == 1){
                                    $this->clients->offsetSet($from, $newConn);

                                    echo " ;Cliente ".$from->nome." Entrou; ";

                                    $this->usersClients->attach($newConn);
                                
                                    $nomeLobby = $newConn->lobby;

                                    $this->Cliente->AddClienteLobby($nomeLobby);


                                    $this->LobbysEspera2->attach($newConn);
                                    $LobbysEspera = $this->ListaClienteEspera();
                                    

                                    foreach ($this->clients as $client) {
                                        if($client->userType == "suporte"){
                                            $client->send(json_encode($LobbysEspera));
                                        }
                                    }
                                } else {
                                    echo " ;Lobby Indisponivel ou ocupado; ";
                                    $from->send(json_encode(["loading-sair"]));
                                    $this->clients->detach($from);
                                    $from->close();
                                }
                            }
                    
                        

                }

                }
                
                

                if($this->qtdSuporte == 0){
                    foreach ($this->clients as $client) {
                        $client->close();
                    }
                }
            }

            
        }


    public function onClose(ConnectionInterface $conn): void
    {

        if($conn->userType == "suporte"){
            $this->qtdSuporte -= 1;
            if($conn->isSala == "1"){
                echo " ;Suporte Saindo da sala e retirando integrantes da sala; ";
                foreach ($this->clients as $client) {
                    if($client->lobby == $conn->lobby && $client->isSala == "1" && $client->userType == "cliente"){
                        $client->send(json_encode(["lobby-sair"]));
                        $this->LobbysEspera2->detach($client);
                        $client->close();
                    }
                }

                $this->Lobbys->IndisponibilizarLobbyEspecifico($conn->lobby);
            }elseif($conn->isSala == "0"){
                $ultimoCliente = $this->UltimoCliente();

                if($ultimoCliente != null){
                    echo "; onClose-Suporte: ". $ultimoCliente->lobby. "; ";
                    echo " ;qtdSuporte: ".$this->qtdSuporte."; ";
                    echo " ;qtdCliente: ".$this->qtdCliente."; ";



                    if($this->qtdSuporte > 0 && $this->qtdCliente <= $this->qtdSuporte){
                        echo " ;Existe Suporte Logados; ";

                        
                        $this->Lobbys->IndisponibilizarLobby();
                        
                        
                    }elseif($this->qtdCliente > $this->qtdSuporte){
                        echo " ;Mais Clientes Logados do que Suporte; ";
                        while($this->qtdCliente > $this->qtdSuporte){
                            $this->qtdCliente -= 1;

                            $this->LobbysEspera2->detach($ultimoCliente);
                            $this->clients->detach($ultimoCliente);
                            
                            $ultimoCliente->close();

                            

                            $LobbysEspera = $this->ListaClienteEspera();
                                    
                            
                            foreach ($this->clients as $client) {
                                if($client->userType == "suporte"){
                                    $client->send(json_encode($LobbysEspera));
                                }
                            }

                            $this->Lobbys->IndisponibilizarLobbys();
                        }
                    }
                    
                } else{
                    if($this->qtdSuporte == 0){
                        echo " ;Ultimo Suporte Desconectado; ";
                        //$this->Lobbys->IndisponibilizarLobby();
                        $this->Lobbys->IndisponibilizarLobbys();
                    } else {
                        echo " ;Liberar sala Normalmente; ";
                        $this->Lobbys->IndisponibilizarLobby();
                    }
                }
        }
            
            echo " ;Deslogar Suporte; ";
            
            $this->Suporte->DeslogarUserSuporte($conn->nome);

            

        } else if($conn->userType == "cliente"){
            $this->qtdCliente -= 1;
            //if($conn->isSala == "0"){
                $this->LobbysEspera2->detach($conn);
                $LobbysEspera = $this->ListaClienteEspera();
                    

                foreach ($this->clients as $client) {
                    if($client->userType == "suporte"){
                        $client->send(json_encode($LobbysEspera));
                    }
                }
            

                $this->Cliente->DeslogarUserCliente($conn);
            
            //}
        }

        echo "User ". $conn->nome . " saiu ";

        
        echo " ;onClose; ";
        $this->LobbysEspera2->detach($conn);
        $this->clients->detach($conn);

        if($this->qtdSuporte == 0){
            foreach ($this->clients as $client) {
                $client->close();
            }
        }

        if($this->ExisteClientes()){
            echo " ;Existe Clientes Conectados; ";
        } else {
            echo " ;Sem Clientes Conectados; ";
        }
    }

    public function onError(ConnectionInterface $conn, Exception $exception): void
    {
        echo " ;onError; ";
        echo " ;".$exception."; ";
        $conn->close();

        if($this->qtdSuporte == 0){
            foreach ($this->clients as $client) {
                $client->close();
            }
        }

        if($this->ExisteClientes()){
            echo " ;Existe Clientes Conectados; ";
        } else {
            echo " ;Sem Clientes Conectados; ";
        }
    }

    public function ListaClienteEspera(){
        $LobbysEspera = [];
        $this->LobbysEspera2->rewind();
        while($this->LobbysEspera2->valid()){
            $index = $this->LobbysEspera2->key();
                        

            $object = $this->LobbysEspera2->current();
                

            var_dump($object->lobby);
                
                
            $LobbysEspera[$index] = $object->lobby;

            $this->LobbysEspera2->next();
        }

        return $LobbysEspera;
    }

    public function UltimoCliente(){
        $LobbysEspera = null;

        $this->LobbysEspera2->rewind();
        while($this->LobbysEspera2->valid()){
                        

            $object = $this->LobbysEspera2->current();
                

                
                
            $LobbysEspera = $object;

            $this->LobbysEspera2->next();
        }

        return $LobbysEspera;
    }

    public function ExisteClientes(){
        if(sizeof($this->clients) > 0){
            return 1;
        }

        return 0;
    }

}


class Lobbys {

    private $db;
    private $qtdUserSuporte;
    private $qtdUserCliente;
    private $qtdLobbys;

    public function __construct(){
        $this->qtdUserSuporte = 2;
        $this->qtdUserCliente = 4;
        $this->qtdLobbys = 4;

        $this->db = DB::getInstance('localhost', 'root', '', 'sala_virtual');
    }

    public function IndisponibilizarLobbys(){
            $sql = "UPDATE lobbys SET status='0'";
            $result = $this->db->query($sql);

            $sql = "UPDATE lobbys SET sala='0'";
            $result = $this->db->query($sql);
    }

    public function AddLobbyDisponivel($qtdSuporte){
        $nomeLobby = $this->PrimeiroLobbyIndisponivel();

        if($nomeLobby == -1){
            echo " ;Sem Lobbys Indisponiveis; ";
            return -1;
        }

        echo " ;AddLobbyDisponivel; ";
        echo " ;".$nomeLobby."; ";
        $sql = "UPDATE lobbys SET status='1' WHERE nome='$nomeLobby'";

        $result = $this->db->query($sql);
    }

    public function IndisponibilizarLobby(){
        $numLobby = $this->UltimoLobbyDisponivel();

        echo " ;IndisponibilizarLobby: ".$numLobby."; ";

        if($numLobby == -1){
            return -1;
        }

        echo " ;; ";
        
        $numLobby += 1;

        $nome = "lobby_".$numLobby;
        
        $sql = "UPDATE lobbys SET status='0' WHERE nome='$nome'";

        $result = $this->db->query($sql);

        $sql = "UPDATE lobbys SET sala='0' WHERE nome='$nome'";

        $result = $this->db->query($sql);

        return $numLobby;

    }

    public function UltimoLobbyDisponivel(){
        $sql = "SELECT * FROM lobbys";
        $result = $this->db->query($sql);

        $lobby  = $result->fetchAll();

        $k = 0;

        for($i = $this->qtdLobbys-1; $i >=0; $i--){
            if($lobby[$i]->status == "1"){
                $k = $i;
                break;
            }
        }

        if($k != -1){
            return $k;
        }

        for($i = $this->qtdLobbys-1; $i >=0; $i--){
            if($lobby[$i]->status == "2" && $lobby[$i]->sala == "0"){
                $k = $i;
                break;
            }
        }

        return $k;
        
    }

    public function PrimeiroLobbyIndisponivel(){


        echo " ;PrimeiroLobbyIndisponivel; ";

        $sql = "SELECT * FROM lobbys";
        $result = $this->db->query($sql);

        $lobby  = $result->fetchAll();

        $nomeLobby = null;

        for($i = 0; $i < $this->qtdLobbys-1; $i++){
            if($lobby[$i]->status == "0"){
                $nomeLobby = $lobby[$i]->nome;
                break;
            }
        }

        if($nomeLobby == null){
            return -1;
        }
        return $nomeLobby;
        
    }

    public function OcuparLobby($nome){
        $sql = "UPDATE lobbys SET status='2' WHERE nome='$nome'";
        $result = $this->db->query($sql);
    }

    public function DisponibilizarLobby($nomeLobby){

        $lobby = $this->LobbyEspecifico($nomeLobby);

        if($lobby->status == "0"){
            return -1;
        } elseif($lobby->status == "1" || $lobby->status == "2") {
            $sql = "UPDATE lobbys SET status='1' WHERE nome='$nomeLobby'";
            $result = $this->db->query($sql);
            $sql = "UPDATE lobbys SET sala='0' WHERE nome='$nomeLobby'";
            $result = $this->db->query($sql);
        }
    }

    public function LobbyEspecifico($nomeLobby){
        $lobby[0] = null;
        $sql = "SELECT * FROM `lobbys` WHERE nome='$nomeLobby'";
        
        $result = $this->db->query($sql);
        $lobby  = $result->fetchAll();

        if($lobby[0] != null){
            return $lobby[0];
        }

        return -1;
        
    }

    public function VerificarDisponibilidadeLobby($nomeLobby){
        $lobby = $this->LobbyEspecifico($nomeLobby);
        //echo " ;VerificarDisponibilidadeLobby: ".$lobby->status."; ";
        if($lobby->status == "0" || $lobby->status == "2"){
            return 0;
        }

        return 1;
    }

    public function IndisponibilizarLobbyEspecifico($nome){
        $sql = "UPDATE lobbys SET status='0' WHERE nome='$nome'";

        $result = $this->db->query($sql);

        $sql = "UPDATE lobbys SET sala='0' WHERE nome='$nome'";

        $result = $this->db->query($sql);
    }

}

class Cliente {
    private $db;
    private $qtdUserSuporte;
    private $qtdUserCliente;
    private $qtdLobbys;
    private $Lobbys;

    public function __construct(){
        $this->qtdUserSuporte = 2;
        $this->qtdUserCliente = 4;
        $this->qtdLobbys = 4;
        $this->Lobbys = new Lobbys();

        $this->db = DB::getInstance('localhost', 'root', '', 'sala_virtual');
    }

    public function AddClienteLobby($nomeLobby){
        $this->Lobbys->OcuparLobby($nomeLobby);
    }

    public function DeslogarUserCliente($conn){
        if($conn->isSala == "1"){
             
            return -1;
        } else if ($conn->isSala == "0"){
            $this->Lobbys->DisponibilizarLobby($conn->lobby);
        }
    }
}

class Suporte {

    private $db;
    private $qtdUserSuporte;
    private $qtdUserCliente;
    private $qtdLobbys;

    public function __construct(){
        $this->qtdUserSuporte = 2;
        $this->qtdUserCliente = 4;
        $this->qtdLobbys = 4;

        $this->db = DB::getInstance('localhost', 'root', '', 'sala_virtual');
    }

    public function LogarUserSuporte($nomeUserSuporte){
        
        $sql = "UPDATE users_suporte SET status='1' WHERE nome='$nomeUserSuporte'";

        $result = $this->db->query($sql);
        
    }

    public function DeslogarUserSuporte($nomeUserSuporte){
        
        $sql = "UPDATE users_suporte SET status='0' WHERE nome='$nomeUserSuporte'";

        $result = $this->db->query($sql);
        
    }

    public function DeslogarUsersSuporte(){
        
            $sql = "UPDATE users_suporte SET status='0'";

            $result = $this->db->query($sql);
        
    }
    
}


class JWT {

    private $API_KEY="my api key";
    private $APP_ID="my AppID"; // Your AppID (previously tenant)
    private $USER_EMAIL="myemail@email.com";
    private $USER_NAME="my user name";
    private $USER_IS_MODERATOR=false;
    private $USER_AVATAR_URL="";
    private $USER_ID="";
    private $LIVESTREAMING_IS_ENABLED=false;
    private $RECORDING_IS_ENABLED=true;
    private $OUTBOUND_IS_ENABLED=false;
    private $TRANSCRIPTION_IS_ENABLED=false;
    private $EXP_DELAY_SEC=720000;
    private $NBF_DELAY_SEC=10;
    private $ROOM='';

    private $jwk;
    private $algorithm;
    private $jwsBuilder;
    private $payload;
    private $jws;
    private $serializer;
    private $token;

    public function __construct(){
        $this->jwk = JWKFactory::createFromKeyFile("./rsa-private.key");

        $this->algorithm = new AlgorithmManager([
            new RS256()
        ]);

        $this->jwsBuilder = new JWSBuilder($this->algorithm);

    }

    public function GerarToken($exp, $nomeUser, $roomName, $appID, $kid, $isModerator){
        $this->USER_NAME = $nomeUser;
        $this->ROOM = $roomName;
        $this->APP_ID = $appID;
        $this->API_KEY = $kid;
        $this->USER_IS_MODERATOR = $isModerator;
        $this->USER_ID = $nomeUser;

        $this->payload = json_encode([
            'iss' => 'chat',
            'aud' => 'jitsi',
            'exp' => time() + $this->EXP_DELAY_SEC,
            'nbf' => time() - $this->NBF_DELAY_SEC,
            'room'=> $this->ROOM,
            'sub' => $this->APP_ID,
            'context' => [
                'user' => [
                    'moderator' => $this->USER_IS_MODERATOR ? "true" : "false",
                    'email' => $this->USER_EMAIL,
                    'name' => $this->USER_NAME,
                    'avatar' => $this->USER_AVATAR_URL,
                    'id' => $this->USER_ID
                ],
                'features' => [
                    'recording' => $this->RECORDING_IS_ENABLED ? "true" : "false",
                    'livestreaming' => $this->LIVESTREAMING_IS_ENABLED ? "true" : "false",
                    'transcription' => $this->TRANSCRIPTION_IS_ENABLED ? "true" : "false",
                    'outbound-call' => $this->OUTBOUND_IS_ENABLED ? "true" : "false"
                ]
            ]
        ]);

        $this->jws = $this->jwsBuilder
        ->create()
        ->withPayload($this->payload)
        ->addSignature($this->jwk, [
            'alg' => 'RS256',
            'kid' => $this->API_KEY,
            'typ' => 'JWT'
        ])
        ->build();

        $this->serializer = new CompactSerializer();
        $this->token = $this->serializer->serialize($this->jws, 0);

        return $this->token;

    }

}




?>