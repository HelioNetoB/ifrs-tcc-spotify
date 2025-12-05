<?php
session_start();

// --- MANTER FLUXO OAUTH (igual ao seu original) ---
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

/* --- helper API --- */
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

/* --- quando o usuário enviar a playlist_url via POST --- */
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

// Prepara array JS com as faixas (uri, name, artists, album, index)
$tracks_for_js = [];
foreach ($tracks as $idx => $item) {
    $t = $item['track'];
    $tracks_for_js[] = [
        'uri' => $t['uri'],
        'name' => $t['name'],
        'artists' => array_map(function($a){return $a['name'];}, $t['artists']),
        'album' => $t['album']['name'],
        'index' => $idx + 1
    ];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Mixify</title>
<link rel="stylesheet" href="homeStyle.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<!-- POPUP (oculto até chamado) -->
<div id="popup" class="popup-overlay" onclick="closePopup()">
    <div class="popup-box" onclick="event.stopPropagation()">
        <h3>Criar playlist embaralhada?</h3>
        <p>Informe o nome da nova playlist (será criada em sua conta):</p>

        <form method="POST" action="shuffle.php" id="createForm">
            <input type="hidden" name="playlist_id" value="<?= htmlspecialchars($playlistData['id'] ?? '') ?>">
            <input type="hidden" name="exceptions" id="exceptionsInput" value="">
            <input type="text" name="new_playlist_name" id="newPlaylistName" style="width:90%; padding:8px; margin-top:10px;"
                   value="<?= isset($playlistData['name']) ? htmlspecialchars($playlistData['name'] . ' (embaralhado)') : 'Nova Playlist (embaralhada)' ?>">
            <div style="margin-top:15px;">
                <button class="btn-confirm" type="submit">Confirmar</button>
                <button class="btn-cancel" type="button" onclick="closePopup()">Cancelar</button>
            </div>
        </form>

    </div>
</div>

<!-- NAVBAR -->
<div class="navbar">
    <div class="navbar-left">
        <div class="navbar-title">Mixify</div>
    </div>
    <div class="navbar-right">
        <div class="navbar-user"><?= htmlspecialchars($user["display_name"] ?? 'Usuário') ?></div>
    </div>
</div>

<?php if (!$playlistData): ?>

<!-- CAIXA INICIAL -->
<div class="initial-box">
    <h2>Insira o link da playlist</h2>

    <form method="POST">
        <input type="text" name="playlist_url" placeholder="https://open.spotify.com/playlist/..." required>
        <button type="submit">Carregar</button>
    </form>
</div>

<?php else: ?>

<div class="main-layout">

    <!-- LEFT: PLAYLIST -->
    <div class="playlist-area">
        <div class="playlist-header">
            <img src="<?= htmlspecialchars($playlistData['images'][0]['url'] ?? '') ?>" class="playlist-cover" alt="cover">
            <div class="playlist-meta">
                <div class="playlist-type">PLAYLIST</div>
                <h1 class="playlist-title"><?= htmlspecialchars($playlistData["name"]) ?></h1>
                <div class="playlist-owner">Criada por <?= htmlspecialchars($playlistData["owner"]["display_name"] ?? '—') ?></div>
                <div class="playlist-count"><?= count($tracks) ?> músicas</div>
            </div>
        </div>

        <div class="tracks-header">
            <div class="col1">#</div>
            <div class="col2">Título</div>
            <div class="col3">Álbum</div>
            <div class="col4">Duração</div>
        </div>

        <div id="trackList" class="track-list">
        <?php foreach ($tracks as $i => $item): 
            $track = $item["track"];
            $artists = array_map(fn($a) => $a["name"], $track["artists"]);
        ?>
        <div class="track-item" data-uri="<?= htmlspecialchars($track["uri"]) ?>">
            <div class="track-number"><?= $i + 1 ?></div>

            <div class="track-title">
                <img src="<?= htmlspecialchars($track["album"]["images"][2]["url"] ?? '') ?>" class="track-cover">
                <div>
                    <div class="t-name"><?= htmlspecialchars($track["name"]) ?></div>
                    <div class="t-artist"><?= htmlspecialchars(implode(", ", $artists)) ?></div>
                </div>
            </div>

            <div class="track-album"><?= htmlspecialchars($track["album"]["name"]) ?></div>

            <div class="track-duration">
                <?php
                    $ms = $track["duration_ms"];
                    echo floor($ms/60000) . ":" . str_pad(floor(($ms%60000)/1000), 2, "0");
                ?>
            </div>
    </div>
    <?php endforeach; ?>
</div>

    </div>

    <!-- RIGHT: FERRAMENTAS / EXCEÇÕES -->
    <div class="tools-area">
        <h3>Exceções (grupos)</h3>
        <p>Crie grupos de músicas que devem permanecer juntas na ordem selecionada quando acionadas.</p>

        <div id="exceptionsRoot">
            <!-- instâncias serão inseridas aqui -->
        </div>

        <div style="margin-top:12px;">
            <button id="addExceptionBtn" class="small-btn">+ Adicionar exceção</button>
        </div>

        <hr style="margin:15px 0; border-color:#2a2a2a;">
        <p><strong>Observações:</strong></p>
        <ul style="opacity:0.8; font-size:0.9em;">
            <li>Uma faixa só pode pertencer a <strong>uma</strong> exceção.</li>
            <li>Você precisa adicionar pelo menos 2 músicas em uma exceção para que ela seja válida.</li>
        </ul>

        <div style="margin-top:18px;">
            <!-- Botão de embaralhar (moved to right) -->
            <button class="shuffle-btn" onclick="openAndPreparePopup()">Criar playlist embaralhada</button>
        </div>

    </div>
</div>

<?php endif; ?>

<!-- ================= JS ================= -->
<script>
const PLAYLIST_ID = "<?= $playlistData['id'] ?>";
let offset = 100;
let loadingMore = false;
let allTracks = <?= json_encode($tracks_for_js) ?>;


/* Tracks disponibilizadas pelo PHP */
let playlistTracks = allTracks;

/* Estado local das exceções */
let exceptions = []; // array de {id, name, uris: [uri,...], uiElement}
let nextExceptionId = 1;

/* Mapa de trackUri -> exceptionId (para impedir duplicatas) */
let trackToException = {};

/* Funções UI */
const exceptionsRoot = document.getElementById('exceptionsRoot');
const addExceptionBtn = document.getElementById('addExceptionBtn');

const trackListDiv = document.getElementById("trackList");

trackListDiv.addEventListener("scroll", () => {
    const scrollTop = trackListDiv.scrollTop;
    const scrollHeight = trackListDiv.scrollHeight;
    const clientHeight = trackListDiv.clientHeight;

    // Load next batch when near bottom (80%)
    if (!loadingMore && scrollTop + clientHeight >= scrollHeight * 0.8) {
        loadMoreTracks();
    }
});

function loadMoreTracks() {
    loadingMore = true;

    fetch(`load_tracks.php?playlist_id=${PLAYLIST_ID}&offset=${offset}`)
        .then(res => res.json())
        .then(data => {
            if (!data.tracks || data.tracks.length === 0) return;

            data.tracks.forEach((t, i) => {
                allTracks.push(t);

                const index = offset + i + 1;

                const div = document.createElement("div");
                div.className = "track-item";
                div.dataset.uri = t.uri;
                div.innerHTML = `
                    <div class="track-number">${index}</div>
                    <div class="track-title">
                        <img class="track-cover" src="${t.album_img}">
                        <div>
                            <div class="t-name">${t.name}</div>
                            <div class="t-artist">${t.artists.join(", ")}</div>
                        </div>
                    </div>
                    <div class="track-album">${t.album}</div>
                    <div class="track-duration">${formatMs(t.duration_ms)}</div>
                `;
                trackListDiv.appendChild(div);
            });

            offset += 100;
            loadingMore = false;
        });
}

function formatMs(ms) {
    return Math.floor(ms/60000) + ":" + String(Math.floor((ms%60000)/1000)).padStart(2, "0");
}


addExceptionBtn.addEventListener('click', () => {
    createExceptionInstance();
});

function createExceptionInstance() {
    const id = nextExceptionId++;
    const container = document.createElement('div');
    container.className = 'exception-instance';
    container.dataset.id = id;
    container.innerHTML = `
        <div class="ex-header">
            <input class="ex-name" placeholder="Nome do grupo (opcional)" value="Grupo ${id}">
            <button class="ex-remove">Remover</button>
        </div>
        <div class="ex-search-row">
            <input class="ex-search" placeholder="Pesquisar músicas da playlist..." autocomplete="off">
            <div class="suggestions" style="display:none;"></div>
        </div>
        <div class="ex-list">
            <!-- músicas selecionadas aparecem aqui -->
        </div>
    `;
    exceptionsRoot.appendChild(container);

    const ex = { id, el: container, uris: [] };
    exceptions.push(ex);

    // event listeners
    container.querySelector('.ex-remove').addEventListener('click', () => {
        removeExceptionInstance(id);
    });

    const searchInput = container.querySelector('.ex-search');
    const suggestionsBox = container.querySelector('.suggestions');

    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        if (!q) {
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
            return;
        }
        // filtrar músicas da playlist que não estejam atribuídas
        const results = playlistTracks.filter(t => {
            const inAssigned = trackToException[t.uri] !== undefined;
            const matches = (t.name + ' ' + t.artists.join(' ')).toLowerCase().includes(q);
            return matches && !inAssigned;
        }).slice(0,8);

        if (results.length === 0) {
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
            return;
        }

        suggestionsBox.innerHTML = results.map(r => {
            return `<div class="suggest-item" data-uri="${r.uri}"><strong>${escapeHtml(r.name)}</strong><div class="small">${escapeHtml(r.artists.join(', '))}</div></div>`;
        }).join('');
        suggestionsBox.style.display = 'block';

        // attach click
        suggestionsBox.querySelectorAll('.suggest-item').forEach(it => {
            it.addEventListener('click', (ev) => {
                const uri = ev.currentTarget.dataset.uri;
                addTrackToException(id, uri);
                searchInput.value = '';
                suggestionsBox.style.display = 'none';
                suggestionsBox.innerHTML = '';
            });
        });
    });
}

function removeExceptionInstance(id) {
    // liberar tracks atribuídas
    const ex = exceptions.find(x => x.id === id);
    if (!ex) return;
    ex.uris.forEach(u => { delete trackToException[u]; });
    // remover do DOM e do array
    ex.el.remove();
    exceptions = exceptions.filter(x => x.id !== id);
}

function addTrackToException(exceptionId, uri) {
    const ex = exceptions.find(x => x.id === exceptionId);
    if (!ex) return;
    // find track data
    const track = playlistTracks.find(t => t.uri === uri);
    if (!track) return;
    // push uri
    ex.uris.push(uri);
    trackToException[uri] = exceptionId;
    // render in UI
    const list = ex.el.querySelector('.ex-list');
    const item = document.createElement('div');
    item.className = 'ex-track';
    item.dataset.uri = uri;
    item.innerHTML = `<div class="ex-track-title"><strong>${escapeHtml(track.name)}</strong> <span class="small">- ${escapeHtml(track.artists.join(', '))}</span></div>
                      <button class="ex-remove-track">Remover</button>`;
    list.appendChild(item);
    // remove handler
    item.querySelector('.ex-remove-track').addEventListener('click', () => {
        // remover do ex.uris e mapa
        ex.uris = ex.uris.filter(u => u !== uri);
        delete trackToException[uri];
        item.remove();
    });
}

/* abrir popup e serializar exceções para o campo oculto */
function openAndPreparePopup() {
    // validar exceções: cada exceção precisa ter >=2 músicas para ser válida (se existir)
    const validExceptions = {};
    exceptions.forEach(ex => {
        if (ex.uris.length >= 2) {
            validExceptions[ex.id] = { name: ex.el.querySelector('.ex-name').value || ('Grupo ' + ex.id), uris: ex.uris.slice() };
        }
    });
    // prepare hidden input
    document.getElementById('exceptionsInput').value = JSON.stringify(validExceptions);
    // mostrar popup
    document.getElementById('popup').style.display = 'flex';
}

function closePopup() {
    document.getElementById('popup').style.display = 'none';
}

/* util: escapar HTML */
function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

/* Inicializar: você pode criar 0 instâncias inicialmente */
</script>

</body>
</html>
