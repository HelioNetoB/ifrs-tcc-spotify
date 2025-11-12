<?php
//  aqui acontece a consulta ao banco:
// $usuario = $_POST['usuario'];
// $senha = $_POST['senha'];
// if(verificarUsuario($usuario, $senha)) { ... }

// Simulação:
$usuario = $_POST['usuario'];
$senha = $_POST['senha'];

if($usuario === "admin" && $senha === "1234") {
    // Iniciaria sessão aqui
    header("Location: home.php");
    exit();
} else {
    // Simulação de erro → por enquanto redireciona mesmo assim
    header("Location: home.php");
    exit();
}
