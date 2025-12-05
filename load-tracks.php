<?php
session_start();

if (!isset($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not authenticated"]);
    exit;
}

if (!isset($_GET['playlist_id']) || !isset($_GET['offset'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing parameters"]);
    exit;
}

$access_token = $_SESSION['access_token'];
$playlist_id = $_GET['playlist_id'];
$offset = intval($_GET['offset']);

function spotifyAPI($endpoint, $access_token) {
    $url = "https://api.spotify.com/v1/" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

$response = spotifyAPI("playlists/$playlist_id/tracks?limit=100&offset=$offset", $access_token);

$tracks = [];

foreach ($response["items"] as $item) {
    $t = $item["track"];

    $tracks[] = [
        "uri" => $t["uri"],
        "name" => $t["name"],
        "artists" => array_map(fn($a) => $a["name"], $t["artists"]),
        "album" => $t["album"]["name"],
        "duration_ms" => $t["duration_ms"],
        "album_img" => $t["album"]["images"][2]["url"] ?? ""
    ];
}

echo json_encode([
    "tracks" => $tracks,
    "next" => count($tracks) === 100
]);
