<?php
session_start();

/* -------------------------------------------------------
   1. Fluxo OAuth — MANTIDO EXATAMENTE COMO NO SEU ORIGINAL
-------------------------------------------------------- */

if (isset($_SESSION['access_token'])) {
    // Usuário já está logado
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

/* -------------------------------------------------------
   2. Função auxiliar da API Spotify
-------------------------------------------------------- */
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
        if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

/* -------------------------------------------------------
   3. Identificar playlist do usuário (se enviada)
-------------------------------------------------------- */

$playlistData = null;
$tracks = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["playlist_url"])) {

    $playlist_url = trim($_POST["playlist_url"]);

    if (preg_match("/playlist\/([a-zA-Z0-9]+)/", $playlist_url, $m)) {

        $playlist_id = $m[1];

        $playlistData = spotifyAPI("playlists/$playlist_id", $_SESSION['access_token']);
        $tracks = $playlistData["tracks"]["items"] ?? [];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Mixify</title>
<link rel="stylesheet" href="homeStyle.css">

<script>
function confirmPopup() {
    document.getElementById("popup").style.display = "flex";
}
function closePopup() {
    document.getElementById("popup").style.display = "none";
}
</script>

</head>
<body>

<!-- POPUP -->
<div id="popup" class="popup-overlay" onclick="closePopup()">
    <div class="popup-box" onclick="event.stopPropagation()">
        <h3>Criar playlist embaralhada?</h3>
        <p>Uma nova playlist será criada na sua conta.</p>

        <form method="POST" action="shuffle.php">
            <input type="hidden" name="playlist_id" value="<?= $playlistData['id'] ?? '' ?>">
            <button class="btn-confirm">Confirmar</button>
        </form>

        <button class="btn-cancel" onclick="closePopup()">Cancelar</button>
    </div>
</div>

<!-- NAVBAR -->
<div class="navbar">
    <div class="navbar-title">Mixify</div>
    <div class="navbar-user"><?= $user["display_name"] ?></div>
</div>

<!-- SE NÃO TEM PLAYLIST: INPUT -->
<?php if (!$playlistData): ?>

<div class="initial-box">
    <h2>Insira o link da playlist</h2>

    <form method="POST">
        <input type="text" name="playlist_url" placeholder="https://open.spotify.com/playlist/..." required>
        <button type="submit">Carregar</button>
    </form>
</div>

<?php else: ?>

<!-- LAYOUT PRINCIPAL -->
<div class="main-layout">

    <!-- LEFT: PLAYLIST -->
    <div class="playlist-area">

        <div class="playlist-header">
            <img src="<?= $playlistData['images'][0]['url'] ?>" class="playlist-cover">
            <div>
                <h1><?= $playlistData["name"] ?></h1>
                <p class="playlist-owner">Criada por <?= $playlistData["owner"]["display_name"] ?></p>
                <p><?= count($tracks) ?> músicas</p>
            </div>
        </div>

        <div class="tracks-header">
            <div class="col1">#</div>
            <div class="col2">Título</div>
            <div class="col3">Álbum</div>
            <div class="col4">Duração</div>
        </div>

        <div class="track-list">
            <?php foreach ($tracks as $i => $item): 
                $track = $item["track"];
            ?>
            <div class="track-item">
                <div class="track-number"><?= $i+1 ?></div>

                <div class="track-title">
                    <img src="<?= $track["album"]["images"][2]["url"] ?>" class="track-cover">
                    <div>
                        <div class="t-name"><?= $track["name"] ?></div>
                        <div class="t-artist"><?= $track["artists"][0]["name"] ?></div>
                    </div>
                </div>

                <div class="track-album"><?= $track["album"]["name"] ?></div>

                <div class="track-duration">
                    <?php
                        $ms = $track["duration_ms"];
                        echo floor($ms/60000) . ":" . str_pad(floor(($ms%60000)/1000), 2, "0");
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button class="shuffle-btn" onclick="confirmPopup()">Criar playlist embaralhada</button>

    </div>

    <!-- RIGHT: FUTURA ÁREA -->
    <div class="tools-area">
        <h2>Em breve...</h2>
        <p>Filtros, ordenação, análise musical, BPM, energia, etc.</p>
    </div>

</div>

<?php endif; ?>

</body>
</html>
