<?php
require __DIR__ . "/auth.php";
require_admin();
require __DIR__ . "/../api/config.php";

if (empty($_SESSION["center_id"])) {
  http_response_code(400);
  exit("Centre manquant en session. Reconnecte-toi.");
}

$centerId = (int)$_SESSION["center_id"];

// Récupérer le nom du centre
$stmt = $pdo->prepare("
  SELECT id, name, slug
  FROM centers
  WHERE id = :id
  LIMIT 1
");
$stmt->execute([":id" => $centerId]);
$center = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$center) {
  http_response_code(404);
  exit("Centre introuvable.");
}

// URL du formulaire (WAMP local)
$formUrl = "http://localhost/lasergame/scan.html";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>QR Code – LaserGame</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="panel" style="max-width:520px;margin:40px auto;text-align:center;">
  <h1>QR Code du formulaire</h1>
  <p class="muted">À scanner par les joueurs après la partie</p>

  <p style="margin-top:10px;">
    <span class="badge badge--gold">Centre : <?= h($center["name"]) ?></span>
  </p>

  <img
    src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($formUrl) ?>"
    alt="QR Code formulaire"
    style="margin:20px 0;"
  >

  <p style="font-size:14px;opacity:.7;">
    URL : <br>
    <code><?= h($formUrl) ?></code>
  </p>

  <p class="muted" style="margin-top:10px;">
    Le centre est identifié automatiquement via le code de partie généré pour ce centre.
  </p>

  <a href="index.php" class="btn btn--ghost">← Retour admin</a>
</div>

</body>
</html>
