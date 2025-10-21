<?php
// get_form_questions.php
session_start();
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;
if ($form_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'form_id requerido']);
    exit;
}

// validar que form exista y el alumnx puede verlo (mismo criterio que en get_available_forms)
$stmt = $mysqli->prepare("SELECT id, title, description, id_docente, id_materia, start_at, end_at, active FROM form WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $form_id);
$stmt->execute();
$fres = $stmt->get_result();
$form = $fres->fetch_assoc();
$stmt->close();

if (!$form || !$form['active']) {
    http_response_code(404);
    echo json_encode(['error' => 'Formulario no encontrado o inactivo']);
    exit;
}
$now = date('Y-m-d H:i:s');
if (($form['start_at'] && $form['start_at'] > $now) || ($form['end_at'] && $form['end_at'] < $now)) {
    http_response_code(403);
    echo json_encode(['error' => 'Formulario fuera de tiempo']);
    exit;
}

// validar permiso del alumnx para ese form
$allowed = false;
if ($form['id_materia']) {
    $chk = $mysqli->prepare("SELECT 1 FROM alumnx_materia WHERE id_alumnx = ? AND id_course = ? LIMIT 1");
    $chk->bind_param('ii', $user_id, $form['id_materia']);
    $chk->execute();
    $r = $chk->get_result();
    $allowed = $r->fetch_row() ? true : false;
    $chk->close();
}
if (!$allowed && $form['id_docente']) {
    $chk = $mysqli->prepare("
        SELECT 1 FROM alumnx_materia am
        JOIN docente_materia dm ON dm.id_materia = am.id_course
        WHERE am.id_alumnx = ? AND dm.id_docente = ? LIMIT 1
    ");
    $chk->bind_param('ii', $user_id, $form['id_docente']);
    $chk->execute();
    $r = $chk->get_result();
    $allowed = $r->fetch_row() ? true : false;
    $chk->close();
}
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'No tienes permiso para ver este formulario']);
    exit;
}

// traer preguntas y opciones
$qstmt = $mysqli->prepare("SELECT id, texto_pregunta, tipo FROM question WHERE id_form = ? ORDER BY id ASC");
$qstmt->bind_param('i', $form_id);
$qstmt->execute();
$qres = $qstmt->get_result();

$questions = [];
while ($q = $qres->fetch_assoc()) {
    $question = [
        'id' => (int)$q['id'],
        'text' => $q['texto_pregunta'],
        'type' => $q['tipo'],
        'choices' => []
    ];

    if ($q['tipo'] === 'multiple') {
        $cstmt = $mysqli->prepare("SELECT id, choice_text FROM choice WHERE id_question = ? ORDER BY sort_order ASC, id ASC");
        $cstmt->bind_param('i', $q['id']);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        while ($c = $cres->fetch_assoc()) {
            $question['choices'][] = ['id' => (int)$c['id'], 'text' => $c['choice_text']];
        }
        $cstmt->close();
    }

    $questions[] = $question;
}
$qstmt->close();

echo json_encode([
    'form' => ['id' => (int)$form['id'], 'title' => $form['title'], 'description' => $form['description']],
    'questions' => $questions
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
