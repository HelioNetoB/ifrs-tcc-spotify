<?php
session_start();

// Verificar login
if (!isset($_SESSION['access_token'])) {
    header("Location: index.php");
    exit;
}

$access_token = $_SESSION['access_token'];

// Função auxiliar para chamar API
function spotifyAPI($endpoint, $access_token, $method = "GET", $body = null) {
    $url = "https://api.spotify.com/v1/" . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Receber playlist original
$playlist_id = $_POST["playlist_id"] ?? "";

if (!$playlist_id) {
    echo "ERRO: Nenhuma playlist recebida.";
    exit;
}

// -------------------------------------------------------------------
// 1. Obter dados do usuário (para criar playlist nova na conta dele)
// -------------------------------------------------------------------
$user = spotifyAPI("me", $access_token);

$user_id = $user["id"];

// -------------------------------------------------------------------
// 2. Buscar faixas da playlist original
// -------------------------------------------------------------------
$playlist = spotifyAPI("playlists/$playlist_id", $access_token);
$tracks = $playlist["tracks"]["items"];

// Criar lista só com URIs das músicas
$uris = [];
foreach ($tracks as $t) {
    $uris[] = $t["track"]["uri"];
}

// -------------------------------------------------------------------
// 3. Embaralhar músicas
// -------------------------------------------------------------------
shuffle($uris);

// -------------------------------------------------------------------
// 4. Criar nova playlist
// -------------------------------------------------------------------
$newPlaylistName = $playlist["name"] . " (Embaralhada)";

$new_playlist = spotifyAPI("users/$user_id/playlists", $access_token, "POST", [
    "name" => $newPlaylistName,
    "description" => "Playlist criada automaticamente com músicas embaralhadas.",
    "public" => false
]);

$new_playlist_id = $new_playlist["id"];

// -------------------------------------------------------------------
// 5. Adicionar faixas embaralhadas na nova playlist
// -------------------------------------------------------------------
spotifyAPI("playlists/$new_playlist_id/tracks", $access_token, "POST", [
    "uris" => $uris
]);

// -------------------------------------------------------------------
// 6. Redirecionar de volta ao home
// -------------------------------------------------------------------
header("Location: home.php?created=$new_playlist_id");
exit;
?>
