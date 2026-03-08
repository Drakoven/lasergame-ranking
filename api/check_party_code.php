<?php
header("Content-Type: application/json; charset=utf-8");
require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok"=>false, "error"=>"Méthode non autorisée"]);
  exit;
}

$code = strtoupper(trim($_POST["partyCode"] ?? ""));
if ($code === "" || strlen($code) < 3) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Code manquant"]);
  exit;
}

$now = date("Y-m-d H:i:s");

$stmt = $pdo->prepare("
  SELECT id, center_id, code, valid_from, valid_to
  FROM party_codes
  WHERE code = :c
    AND valid_from <= :now
    AND valid_to >= :now
  ORDER BY id DESC
  LIMIT 1
");
$stmt->execute([":c"=>$code, ":now"=>$now]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  echo json_encode(["ok"=>false, "error"=>"Code invalide ou expiré. Demande un code au staff."]);
  exit;
}

echo json_encode([
  "ok" => true,
  "code" => $row["code"],
  "center_id" => (int)$row["center_id"],
  "valid_to" => $row["valid_to"]
]);
exit;
