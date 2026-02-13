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
$verified = $_POST["verified"] ?? null;

if ($id <= 0 || !in_array($verified, ["0","1"], true)) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Paramètres invalides"]);
  exit;
}

$v = (int)$verified;
$adminId = (int)$_SESSION["admin_id"];
$now = date("Y-m-d H:i:s");

$stmt = $pdo->prepare("
  UPDATE scores
  SET verified = :v,
      verified_at = :va,
      verified_by_admin_id = :aid
  WHERE id = :id
");
$stmt->execute([
  ":v" => $v,
  ":va" => $now,
  ":aid" => $adminId,
  ":id" => $id
]);

echo json_encode(["ok"=>true, "id"=>$id, "verified"=>$v, "verified_at"=>$now]);
