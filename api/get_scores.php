<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION["is_admin"])) {
  http_response_code(401);
  echo json_encode(["ok"=>false, "error"=>"Non autorisé"]);
  exit;
}

require __DIR__ . "/config.php";

$ym = $_GET["ym"] ?? date("Y-m");
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date("Y-m");

$q = trim($_GET["q"] ?? "");
$verified = $_GET["verified"] ?? "all";
$sort = $_GET["sort"] ?? "scoreDesc";
$limit = 500;

$start = $ym . "-01 00:00:00";
$end   = date("Y-m-d H:i:s", strtotime($start . " +1 month"));

$orderBy = "score DESC, party_datetime DESC";
if ($sort === "scoreAsc") $orderBy = "score ASC, party_datetime DESC";
if ($sort === "dateDesc") $orderBy = "party_datetime DESC, score DESC";
if ($sort === "dateAsc")  $orderBy = "party_datetime ASC, score DESC";

$sql = "
  SELECT id, email, party_datetime, vest_pseudo, player_pseudo, score, party_code,
         photo_path, photo_mime, photo_size, verified, created_at, verified_at, verified_by_admin_id
  FROM scores
  WHERE party_datetime >= :start AND party_datetime < :end
";
$params = [":start"=>$start, ":end"=>$end];

if ($q !== "") {
  $sql .= " AND (email LIKE :q OR vest_pseudo LIKE :q OR player_pseudo LIKE :q OR party_code LIKE :q)";
  $params[":q"] = "%$q%";
}

if ($verified === "0" || $verified === "1") {
  $sql .= " AND verified = :v";
  $params[":v"] = (int)$verified;
}

$sql .= " ORDER BY $orderBy LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["ok"=>true, "scores"=>$scores]);
