<?php



class Sessao {
    
    public static function Logar($db, $nome, $lobby){
        $sql = "UPDATE users_suporte SET status='1' WHERE nome='$nome'";


        mysqli_query($db, $sql);

        echo "Logado";

        $lobby->LiberarLobby2($db);
    }

    public static function Deslogar($db, $nome, $lobby){
        $sql = "SELECT * FROM `users_suporte` WHERE nome='$nome'";
        $sql = "UPDATE users_suporte SET status='0' WHERE nome='$nome'";


        mysqli_query($db, $sql);

        echo "Deslogado";

        $lobby->FecharLobby2($db);
    }

    public static function SuporteLogados($db){
        $sql = "SELECT * FROM `users_suporte`";

        $users = [];

        $select = mysqli_query($db, $sql);

        $cont = 0;

        for($linha = 0; $resultado = mysqli_fetch_assoc($select); $linha++){
            
            if($resultado['status'] == "1"){
                $cont++;
            }
           
        }

        return $cont;
    }
}


?>