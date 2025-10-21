<?php
// get_available_forms.php
session_start();
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

// Traer forms activas y que correspondan al alumnx 
$sql = "
SELECT f.id, f.title, f.description, f.id_docente, f.id_materia, f.start_at, f.end_at, f.active
FROM form f
WHERE f.active = 1
  AND ( (f.start_at IS NULL OR f.start_at <= ?) AND (f.end_at IS NULL OR f.end_at >= ?) )
  AND (
      -- forma 1: encuesta vinculada a materia y el alumno está inscrito
      (f.id_materia IS NOT NULL AND EXISTS (
          SELECT 1 FROM alumnx_materia am WHERE am.id_alumnx = ? AND am.id_course = f.id_materia
      ))
      OR
      -- forma 2: encuesta vinculada a docente y ese docente imparte alguna materia en la que está el alumno
      (f.id_docente IS NOT NULL AND EXISTS (
          SELECT 1 FROM alumnx_materia am
          JOIN docente_materia dm ON dm.id_materia = am.id_course
          WHERE am.id_alumnx = ? AND dm.id_docente = f.id_docente
      ))
  )
ORDER BY f.start_at IS NULL, f.start_at DESC, f.id DESC
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error preparando consulta']);
    exit;
}
$stmt->bind_param('ssis', $now, $now, $user_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

$forms = [];
while ($row = $res->fetch_assoc()) {
    // comprobar si ya respondió
    $chk = $mysqli->prepare("SELECT 1 FROM response WHERE id_form = ? AND id_alumnx = ? LIMIT 1");
    $chk->bind_param('ii', $row['id'], $user_id);
    $chk->execute();
    $r = $chk->get_result();
    $answered = $r->fetch_row() ? true : false;
    $chk->close();

    $forms[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'id_docente' => $row['id_docente'] !== null ? (int)$row['id_docente'] : null,
        'id_materia' => $row['id_materia'] !== null ? (int)$row['id_materia'] : null,
        'start_at' => $row['start_at'],
        'end_at' => $row['end_at'],
        'answered' => $answered
    ];
}

echo json_encode(['forms' => $forms], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
