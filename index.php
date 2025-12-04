<?php
    // Configurações da Spotify API
    $client_id = "SEU_CLIENT_ID_AQUI"; 
    $redirect_uri = "https://SEU-SITE.onrender.com/home.php";

    // Scopes – permissões do app
    $scopes = "playlist-read-private playlist-modify-private playlist-modify-public user-library-read";

    // Construindo a URL de autorização
    $authorize_url = "https://accounts.spotify.com/authorize" .
                     "?response_type=code" .
                     "&client_id=$client_id" .
                     "&scope=" . urlencode($scopes) .
                     "&redirect_uri=" . urlencode($redirect_uri);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mixify TCC</title>
    <link rel="stylesheet" href="indexStyle.css">
</head>
<body>

<header>
    <!-- Botão Login leva para a URL de autenticação Spotify -->
    <a href="<?php echo $authorize_url; ?>" class="login-btn">Login com Spotify</a>
</header>

<div class="sidebar">
    <button onclick="document.getElementById('intro-banner').scrollIntoView();">Início</button>
    <button onclick="document.getElementById('sec1').scrollIntoView();">Introdução</button>
    <button onclick="document.getElementById('sec2').scrollIntoView();">História</button>
    <button onclick="document.getElementById('sec3').scrollIntoView();">Detalhes</button>
    <button onclick="document.getElementById('sec4').scrollIntoView();">Conclusão</button>
</div>

<div class="content">

    <div id="intro-banner">
        <div class="banner-text">
            <h1>Bem-vindo ao Mixify</h1>
            <p>Gerencie e transforme suas playlists Spotify com inteligência.</p>
        </div>
    </div>

    <section id="sec1">
        <h2>Introdução</h2>
        <p>Texto da seção…</p>
    </section>

    <section id="sec2">
        <h2>História</h2>
        <p>Texto da seção…</p>
    </section>

    <section id="sec3">
        <h2>Detalhes</h2>
        <p>Texto da seção…</p>
    </section>

    <section id="sec4">
        <h2>Conclusão</h2>
        <p>Texto da seção…</p>
    </section>

</div>

</body>
</html>
