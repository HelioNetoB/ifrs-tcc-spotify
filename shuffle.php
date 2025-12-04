<?php
session_start();

// Verificar login
if (!isset($_SESSION['access_token'])) {
    header("Location: index.php");
    exit;
}

$access_token = $_SESSION['access_token'];

// Função auxiliar para chamadas à Spotify API
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
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => json_decode($response, true), 'http' => $httpcode];
}

// Receber parâmetros
$playlist_id = $_POST['playlist_id'] ?? '';
$exceptions_raw = $_POST['exceptions'] ?? '';
$new_playlist_name = $_POST['new_playlist_name'] ?? '';

if (!$playlist_id) {
    echo "Nenhuma playlist enviada.";
    exit;
}

// Buscar usuário
$userResp = spotifyAPI("me", $access_token);
if (!isset($userResp['body']['id'])) {
    echo "Não foi possível obter dados do usuário.";
    exit;
}
$user_id = $userResp['body']['id'];

// Buscar playlist e todas as faixas (atenção à paginação)
$all_tracks = [];
$offset = 0;
while (true) {
    $resp = spotifyAPI("playlists/$playlist_id/tracks?limit=100&offset=$offset", $access_token);
    if (!isset($resp['body']['items'])) break;
    $items = $resp['body']['items'];
    foreach ($items as $it) {
        if (isset($it['track']['uri'])) $all_tracks[] = $it['track']['uri'];
    }
    if (count($items) < 100) break;
    $offset += 100;
}

// Parse exceptions JSON
$exceptions = [];
if ($exceptions_raw) {
    $parsed = json_decode($exceptions_raw, true);
    if (is_array($parsed)) {
        // parsed is map of id -> {name, uris}
        foreach ($parsed as $eid => $obj) {
            if (isset($obj['uris']) && is_array($obj['uris']) && count($obj['uris']) >= 2) {
                // keep only URIs that are present in original playlist and unique
                $clean = [];
                foreach ($obj['uris'] as $u) {
                    if (in_array($u, $all_tracks) && !in_array($u, $clean)) $clean[] = $u;
                }
                if (count($clean) >= 2) {
                    $exceptions[$eid] = ['name' => $obj['name'] ?? "Grupo $eid", 'uris' => $clean];
                }
            }
        }
    }
}

// Build mapping uri -> exception id (first found)
$uriToException = [];
foreach ($exceptions as $eid => $obj) {
    foreach ($obj['uris'] as $u) {
        $uriToException[$u] = $eid;
    }
}

// Build non-exception list
$non_exception_uris = [];
foreach ($all_tracks as $u) {
    if (!isset($uriToException[$u])) $non_exception_uris[] = $u;
}

// Shuffle non-exception uris
shuffle($non_exception_uris);

// Now build final order inserting exception groups when a member is encountered
$final = [];
$appliedExceptions = []; // eid => true
$added = []; // uri => true

foreach ($non_exception_uris as $u) {
    // if this uri belongs to an exception (shouldn't happen since we filtered), skip
    if (isset($added[$u])) continue;

    if (isset($uriToException[$u])) {
        $eid = $uriToException[$u];
        if (!isset($appliedExceptions[$eid])) {
            // insert full exception in defined order
            foreach ($exceptions[$eid]['uris'] as $eu) {
                if (!isset($added[$eu])) {
                    $final[] = $eu;
                    $added[$eu] = true;
                }
            }
            $appliedExceptions[$eid] = true;
        }
    } else {
        // not in any exception, just add if not already added
        if (!isset($added[$u])) {
            $final[] = $u;
            $added[$u] = true;
        }
    }
}

// After iterating, ensure any exceptions that were not applied (none of their URIs were in non_exception_uris) are appended
foreach ($exceptions as $eid => $obj) {
    if (!isset($appliedExceptions[$eid])) {
        foreach ($obj['uris'] as $eu) {
            if (!isset($added[$eu])) {
                $final[] = $eu;
                $added[$eu] = true;
            }
        }
        $appliedExceptions[$eid] = true;
    }
}

// Finally, ensure any remaining tracks not yet added are appended (shouldn't be necessary)
foreach ($all_tracks as $u) {
    if (!isset($added[$u])) {
        $final[] = $u;
        $added[$u] = true;
    }
}

// Create new playlist
if (!$new_playlist_name) $new_playlist_name = "Nova playlist embaralhada";
$newResp = spotifyAPI("users/$user_id/playlists", $access_token, "POST", [
    'name' => $new_playlist_name,
    'description' => 'Criada por Mixify - versão embaralhada (com exceções)',
    'public' => false
]);
if (!isset($newResp['body']['id'])) {
    echo "Erro ao criar playlist: ";
    echo "<pre>" . print_r($newResp, true) . "</pre>";
    exit;
}

$new_playlist_id = $newResp['body']['id'];

// Add tracks in batches (100)
$chunks = array_chunk($final, 100);
foreach ($chunks as $chunk) {
    $addResp = spotifyAPI("playlists/$new_playlist_id/tracks", $access_token, "POST", [
        'uris' => $chunk
    ]);
    // no deep error handling here; in production check $addResp['http']
}

// Redirect to new playlist on Spotify (or back to home with id)
header("Location: home.php?created=$new_playlist_id");
exit;
?>
