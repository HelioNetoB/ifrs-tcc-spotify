<?php
session_start();

if (isset($_SESSION['access_token'])) {
    // Usuário já está logado, seguir para o conteúdo
}


$client_id = "8c475506c0bd401e866407378998ee29";
$client_secret = "9e04d796a112493bae2c8e25b003e982";
$redirect_uri = "https://mixtify-mixer-e-editor-de-playlists-e.onrender.com/home.php";

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    $token_url = "https://accounts.spotify.com/api/token";

    $data = [
        "grant_type" => "authorization_code",
        "code" => $code,
        "redirect_uri" => $redirect_uri,
        "client_id" => $client_id,
        "client_secret" => $client_secret
    ];

    $options = [
        CURLOPT_URL => $token_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokens = json_decode($response, true);

    if (isset($tokens['error'])) {
    echo "<h2>Erro ao obter token:</h2>";
    echo "<pre>" . print_r($tokens, true) . "</pre>";
    exit;
}

    // Salvar access_token e refresh_token na sessão
    $_SESSION['access_token'] = $tokens['access_token'];
    $_SESSION['refresh_token'] = $tokens['refresh_token'];

    header("Location: home.php");
    exit;
}

if (isset($_SESSION['access_token']) && !isset($_GET['code'])) {

    $api_url = "https://api.spotify.com/v1/me";

    $headers = [
        "Authorization: Bearer " . $_SESSION['access_token']
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $user_response = curl_exec($ch);
    curl_close($ch);

    $user = json_decode($user_response, true);

    if (isset($user["error"])) {
        echo "<h2>Erro na API:</h2>";
        echo "<pre>" . print_r($user, true) . "</pre>";
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Olá, <?php echo $user['display_name']; ?>!</h1>
    <p>Seu ID Spotify é: <?php echo $user['id']; ?></p>
    <p>E-mail: <?php echo $user['email']; ?></p>

    <a href="logout.php">Sair</a>
</body>
</html>