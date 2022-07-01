<?php



namespace Meet;

use voku\db\DB;


use Exception;
use SplObjectStorage;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

final class MeetServer implements MessageComponentInterface
{
    private $clients;
    private $usersClients;
    private $qtdSuporte;
    private $qtdCliente;
    private $db;

    public function __construct()
    {

        $this->db = DB::getInstance('localhost', 'root', '', 'sala_virtual');

        

        $this->clients = new SplObjectStorage();
        $this->usersClients = new SplObjectStorage();
        $this->qtdSuporte = 0;
        $this->qtdCliente = 0;

        for($i=0; $i < 4; $i++){
            $nome = "lobby_".$i;
            $sql = "UPDATE lobbys SET status='0' WHERE nome='$nome'";
            $result = $this->db->query($sql);

            $sql = "UPDATE lobbys SET sala='0' WHERE nome='$nome'";
            $result = $this->db->query($sql);
        }

        for($i=0; $i < 2; $i++){
            $nome = "lobby_".$i;
            $sql = "UPDATE users_suporte SET status='0' WHERE nome='$nome'";

            $result = $this->db->query($sql);
        }


        
        
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        echo "onOpen ";
        $conn->nome = "";
        $conn->userType = "";
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {

        $obj = json_decode($msg);
        if($obj->cmd == "ls"){
            echo $obj->nome;
            $nome = $obj->nome;

            $newConn = $from;
            $newConn->sala = $obj->nome;
            $this->clients->offsetSet($from, $newConn);

            $sql = "UPDATE lobbys SET sala='1' WHERE nome='$nome'";
            $result = $this->db->query($sql);
        } else {
            $this->usersClients->attach($obj);

            echo "User ". $obj->nome . " conectado ";

            $newConn = $from;
            $newConn->nome = $obj->nome;
            $newConn->userType = $obj->userType;
            $this->clients->offsetSet($from, $newConn);


            if($obj->userType == "1"){

                $this->qtdSuporte += 1;
                $nome = "lobby_".$this->qtdSuporte;
                $sql = "UPDATE lobbys SET status='1' WHERE nome='$nome'";

                $result = $this->db->query($sql);

                


            } else if($obj->userType == "0"){
                $this->qtdCliente += 1;
                $nome = $obj->nome;

                $sql = "UPDATE lobbys SET status='2' WHERE nome='$nome'";
                $result = $this->db->query($sql);
            }
            
            foreach ($this->clients as $client) {
                $client->send($msg);
            }
        }

        
    }

    public function onClose(ConnectionInterface $conn): void
    {

        if($conn->userType == "1"){
            $nome = "lobby_".$this->qtdSuporte;
            $sql = "UPDATE lobbys SET status='0' WHERE nome='$nome'";

            $result = $this->db->query($sql);

            $nome = $conn->sala;
            $sql = "UPDATE lobbys SET sala='0' WHERE nome='$nome'";

            $result = $this->db->query($sql);

            $this->qtdSuporte -= 1;
        } else if($conn->userType == "0"){
            $nome = $conn->nome;
            $sql = "SELECT * FROM `lobbys` WHERE nome='$nome'";

            $result = $this->db->query($sql);
            $lobby  = $result->fetchAll();

            echo "sala: ".$lobby[0]->sala;
            echo "status: ".$lobby[0]->status;

            if($lobby[0]->sala == "0" && $lobby[0]->status != "0"){
                $sql = "UPDATE lobbys SET status='1' WHERE nome='$nome'";

                $result = $this->db->query($sql);
            }


            $this->qtdCliente -= 1;

        }

        echo "User ". $conn->nome . " saiu ";

        
        echo "onClose ";
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $exception): void
    {
        echo "onError ";
        $conn->close();
    }
}

?>