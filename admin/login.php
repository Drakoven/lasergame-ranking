<?php
require __DIR__ . "/auth.php";
require __DIR__ . "/../api/config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $user = trim($_POST["username"] ?? "");
  $pass = (string)($_POST["password"] ?? "");

  $stmt = $pdo->prepare("
    SELECT id, username, password_hash
    FROM admins
    WHERE username = :u
    LIMIT 1
  ");
  $stmt->execute([":u" => $user]);
  $admin = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($admin && password_verify($pass, $admin["password_hash"])) {
    session_regenerate_id(true);
    $_SESSION["is_admin"]   = true;
    $_SESSION["admin_id"]   = (int)$admin["id"];
    $_SESSION["admin_user"] = $admin["username"];

    // CSRF token
    if (empty($_SESSION["csrf"])) {
      $_SESSION["csrf"] = bin2hex(random_bytes(16));
    }

    header("Location: /lasergame/admin/");
    exit;
  }

  $error = "Identifiants incorrects.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion Admin</title>
  <link rel="stylesheet" href="./admin.css" />
</head>
<body>
  <div class="panel" style="max-width:420px;margin:60px auto;">
    <h1 style="margin-bottom:8px;">Connexion Admin</h1>
    <p class="muted" style="margin-bottom:16px;">Accès réservé au personnel.</p>

    <?php if ($error): ?>
      <p style="color:crimson;font-weight:800;margin-bottom:12px;">
        <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
      </p>
    <?php endif; ?>

    <form method="post" style="display:grid;gap:10px;">
      <label class="control">
        <span>Identifiant</span>
        <input name="username" required />
      </label>

      <label class="control">
        <span>Mot de passe</span>
        <input name="password" type="password" required />
      </label>

      <button class="btn" type="submit">Se connecter</button>
    </form>
  </div>
</body>
</html>
