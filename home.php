<?php
session_start();

// ---------------------------------------------------------------------
// 1. Verificar autenticação
// ---------------------------------------------------------------------
if (!isset($_SESSION['access_token'])) {
    header("Location: index.php");
    exit;
}

$access_token = $_SESSION['access_token'];

// ---------------------------------------------------------------------
// 2. Função auxiliar para chamadas à Spotify API
// ---------------------------------------------------------------------
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

// ---------------------------------------------------------------------
// 3. Receber INPUT do usuário (link da playlist)
// ---------------------------------------------------------------------
$playlistData = null;
$tracks = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["playlist_url"])) {

    $playlist_url = trim($_POST["playlist_url"]);

    // Extrair ID da playlist
    if (preg_match("/playlist\/([a-zA-Z0-9]+)/", $playlist_url, $matches)) {
        $playlist_id = $matches[1];

        // Buscar playlist
        $playlistData = spotifyAPI("playlists/$playlist_id", $access_token);

        // Tracks
        $tracks = $playlistData["tracks"]["items"] ?? [];
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Mixify - Editor de Playlists</title>
<link rel="stylesheet" href="homeStyle.css">

<style>
/* TEMPORÁRIO: pop-up estilizado aqui, depois moveremos pro CSS */
.popup-overlay {
    position: fixed;
    top:0; left:0;
    width:100%; height:100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    display:none;
    align-items:center;
    justify-content:center;
    z-index: 10;
}

.popup-box {
    background: #fff;
    padding: 20px;
    width: 350px;
    border-radius: 10px;
    text-align: center;
}
.popup-box button {
    padding: 10px 20px;
    margin: 10px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.btn-confirm { background:#1DB954; color:white; }
.btn-cancel { background:#ccc; }
</style>

<script>
// Mostrar pop-up
function confirmPopup() {
    document.getElementById("popup").style.display = "flex";
}

// Fechar pop-up
function closePopup() {
    document.getElementById("popup").style.display = "none";
}
</script>

</head>
<body>

<div id="popup" class="popup-overlay" onclick="closePopup()">
    <div class="popup-box" onclick="event.stopPropagation()">
        <h3>Confirmar?</h3>
        <p>Deseja criar uma nova playlist com as músicas embaralhadas?</p>

        <form method="POST" action="shuffle.php">
            <input type="hidden" name="playlist_id" value="<?php echo $playlistData['id'] ?? ''; ?>">
            <button class="btn-confirm">Sim, criar</button>
        </form>

        <button class="btn-cancel" onclick="closePopup()">Cancelar</button>
    </div>
</div>

<h1 style="margin-left: 20px;">Mixify</h1>

<?php if (!$playlistData): ?>
    <!-- ------------------------------------------------------- -->
    <!-- 1. CAIXA INICIAL PARA ESCOLHER PLAYLIST -->
    <!-- ------------------------------------------------------- -->

    <div class="input-box">
        <h2>Insira o link da playlist:</h2>

        <form method="POST">
            <input type="text" name="playlist_url" placeholder="https://open.spotify.com/playlist/..." required>
            <button type="submit">Carregar</button>
        </form>
    </div>

<?php else: ?>

<!-- ------------------------------------------------------- -->
<!-- 2. INTERFACE PRINCIPAL (playlist + colunas laterais) -->
<!-- ------------------------------------------------------- -->

<div class="layout">

    <!-- DIV DA PLAYLIST (ESQUERDA) -->
    <div class="playlist-box">

        <div class="playlist-header">
            <img src="<?php echo $playlistData['images'][0]['url']; ?>" width="180">
            <div>
                <h2><?php echo $playlistData['name']; ?></h2>
                <p><?php echo $playlistData['description']; ?></p>
                <p><strong><?php echo count($tracks); ?> músicas</strong></p>
            </div>
        </div>

        <hr>

        <div class="track-list">
            <?php foreach ($tracks as $item): 
                $track = $item['track'];
            ?>
                <div class="track-item">
                    <img src="<?php echo $track['album']['images'][2]['url']; ?>" width="50">
                    <div class="track-info">
                        <strong><?php echo $track['name']; ?></strong><br>
                        <?php echo $track['artists'][0]['name']; ?>
                    </div>
                    <div class="track-album"><?php echo $track['album']['name']; ?></div>
                    <div class="track-duration">
                        <?php echo floor($track['duration_ms']/60000) . ":" . str_pad(floor(($track['duration_ms']%60000)/1000), 2, "0", STR_PAD_LEFT); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button class="shuffle-btn" onclick="confirmPopup()">Criar playlist embaralhada</button>
    </div>

    <!-- DIV DIREITA (FUTURAS FUNÇÕES) -->
    <div class="side-box">
        <h2>Ferramentas futuras</h2>
        <p>Aqui teremos filtros, análises e funcionalidades avançadas.</p>
    </div>

</div>

<?php endif; ?>

</body>
</html>
