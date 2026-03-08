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

$id = (int)($_POST["id"] ?? 0);
$status = $_POST["status"] ?? null;

if ($id <= 0 || !in_array($status, ["1","2"], true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Paramètres invalides"]);
  exit;
}

$st = (int)$status;
$adminId = (int)$_SESSION["admin_id"];
$centerId = (int)$_SESSION["center_id"];
$now = date("Y-m-d H:i:s");

// Compat temporaire avec l'ancien champ verified
$verifiedCompat = ($st === 1) ? 1 : 0;

$stmt = $pdo->prepare("
  UPDATE scores
  SET status = :st,
      verified = :v,
      verified_at = :va,
      verified_by_admin_id = :aid
  WHERE id = :id
    AND center_id = :center_id
");
$stmt->execute([
  ":st" => $st,
  ":v"  => $verifiedCompat,
  ":va" => $now,
  ":aid" => $adminId,
  ":id" => $id,
  ":center_id" => $centerId
]);

if ($stmt->rowCount() === 0) {
  http_response_code(404);
  echo json_encode(["ok"=>false, "error"=>"Score introuvable pour ce centre."]);
  exit;
}

echo json_encode([
  "ok" => true,
  "id" => $id,
  "status" => $st,
  "verified" => $verifiedCompat,
  "verified_at" => $now
]);
exit;
