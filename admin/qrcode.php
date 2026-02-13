<?php
require __DIR__ . "/auth.php";
require_admin();

// URL du formulaire (WAMP local)
$formUrl = "http://localhost/lasergame/scan.html";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>QR Code – LaserGame</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="panel" style="max-width:480px;margin:40px auto;text-align:center;">
  <h1>QR Code du formulaire</h1>
  <p class="muted">À scanner par les joueurs après la partie</p>

  <img
    src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($formUrl) ?>"
    alt="QR Code formulaire"
    style="margin:20px 0;"
  >

  <p style="font-size:14px;opacity:.7;">
    URL : <br>
    <code><?= htmlspecialchars($formUrl, ENT_QUOTES, "UTF-8") ?></code>
  </p>

  <a href="index.php" class="btn btn--ghost">← Retour admin</a>
</div>

</body>
</html>
