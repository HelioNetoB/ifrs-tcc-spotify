<?php
// Normalmente inseriríamos no banco:
// $usuario = $_POST['usuario'];
// $email = $_POST['email'];
// $senha = $_POST['senha'];
// INSERT INTO usuarios (usuario, email, senha) VALUES (...)

$usuario = $_POST['usuario'];
$email = $_POST['email'];
$senha = $_POST['senha'];

// Simulação:
header("Location: home.php");
exit();
