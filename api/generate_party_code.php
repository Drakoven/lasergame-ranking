<?php
session_set_cookie_params([
  "httponly" => true,
  "secure"   => !empty($_SERVER["HTTPS"]),
  "samesite" => "Lax",
]);
session_start();

header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION["is_admin"]) || empty($_SESSION["admin_id"])) {
  http_response_code(401);
  echo json_encode(["ok"=>false, "error"=>"Non autorisé"]);
  exit;
}

if (empty($_SESSION["center_id"])) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Centre manquant (reconnecte-toi)."]);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok"=>false, "error"=>"Méthode non autorisée"]);
  exit;
}

if (empty($_POST["csrf"]) || empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $_POST["csrf"])) {
  http_response_code(403);
  echo json_encode(["ok"=>false, "error"=>"CSRF invalide"]);
  exit;
}

require __DIR__ . "/config.php";

$minutes = 30;

function make_code($len = 6) {
  $alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  $out = "";
  for ($i = 0; $i < $len; $i++) {
    $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  }
  return $out;
}

$adminId  = (int)$_SESSION["admin_id"];
$centerId = (int)$_SESSION["center_id"];

$validFrom = date("Y-m-d H:i:s");
$validTo   = date("Y-m-d H:i:s", time() + $minutes * 60);

try {
  for ($i = 0; $i < 10; $i++) {
    $code = make_code(6);

    try {
      $stmt = $pdo->prepare("
        INSERT INTO party_codes (center_id, code, valid_from, valid_to, created_by_admin_id)
        VALUES (:center_id, :c, :vf, :vt, :a)
      ");
      $stmt->execute([
        ":center_id" => $centerId,
        ":c"  => $code,
        ":vf" => $validFrom,
        ":vt" => $validTo,
        ":a"  => $adminId
      ]);

      echo json_encode([
        "ok" => true,
        "code" => $code,
        "valid_from" => $validFrom,
        "valid_to" => $validTo,
        "minutes" => $minutes
      ]);
      exit;

    } catch (PDOException $e) {
      // Si "code" est UNIQUE, il peut y avoir collision très rare -> on réessaie
      // MySQL duplicate key = 1062
      if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        continue;
      }
      throw $e;
    }
  }

  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Impossible de générer un code (réessaie)."]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Erreur serveur"]);
  exit;
}
