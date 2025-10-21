<?php
// submit_form.php
session_start();
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "No autorizado";
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$form_id = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
if ($form_id <= 0) {
    http_response_code(400);
    echo "form_id inválido";
    exit;
}

// validar form y permisos (igual que get_form_questions)
$stmt = $mysqli->prepare("SELECT id, id_docente, id_materia, active, start_at, end_at FROM form WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $form_id);
$stmt->execute();
$fres = $stmt->get_result();
$form = $fres->fetch_assoc();
$stmt->close();

if (!$form || !$form['active']) {
    http_response_code(404);
    echo "Formulario no disponible";
    exit;
}
$now = date('Y-m-d H:i:s');
if (($form['start_at'] && $form['start_at'] > $now) || ($form['end_at'] && $form['end_at'] < $now)) {
    http_response_code(403);
    echo "Formulario fuera de tiempo";
    exit;
}

// validar permiso del alumnx
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
    echo "No puedes contestar este formulario";
    exit;
}

// leer answers: preferimos JSON en campo 'answers'
$answers_json = $_POST['answers'] ?? null;
$answers = [];

if ($answers_json) {
    $decoded = json_decode($answers_json, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        echo "Formato de respuestas inválido";
        exit;
    }
    $answers = $decoded;
} else {

    // traer preguntas para mapear
    $qres = $mysqli->query("SELECT id, tipo FROM question WHERE id_form = " . (int)$form_id);
    while ($qrow = $qres->fetch_assoc()) {
        $qid = $qrow['id'];
        $choiceField = 'choice_' . $qid;
        $textField = 'q' . $qid;
        if (isset($_POST[$choiceField])) {
            $answers[] = ['question_id' => $qid, 'choice_id' => (int)$_POST[$choiceField], 'answer_text' => null, 'answer_value' => null];
        } elseif (isset($_POST[$textField])) {
            $answers[] = ['question_id' => $qid, 'choice_id' => null, 'answer_text' => $_POST[$textField], 'answer_value' => null];
        }
    }
}

// validación básica: al menos una respuesta
if (empty($answers)) {
    http_response_code(400);
    echo "No hay respuestas para guardar";
    exit;
}

// transacción para insertar response + answers
$mysqli->begin_transaction();

try {
    // check duplicado
    $chk = $mysqli->prepare("SELECT id FROM response WHERE id_form = ? AND id_alumnx = ? LIMIT 1");
    $chk->bind_param('ii', $form_id, $user_id);
    $chk->execute();
    $rr = $chk->get_result();
    if ($existing = $rr->fetch_assoc()) {
        $chk->close();
        $mysqli->rollback();
        http_response_code(409);
        echo "Ya respondiste este formulario";
        exit;
    }
    $chk->close();

    // insert response
    $ins = $mysqli->prepare("INSERT INTO response (id_form, id_alumnx) VALUES (?, ?)");
    $ins->bind_param('ii', $form_id, $user_id);
    $ins->execute();
    $response_id = $mysqli->insert_id;
    $ins->close();

    // inserción de answers
    $insAns = $mysqli->prepare("INSERT INTO answer (response_id, id_question, choice_id, texto_respuesta) VALUES (?, ?, ?, ?)");
    foreach ($answers as $a) {
        $qid = isset($a['question_id']) ? (int)$a['question_id'] : 0;
        $cid = isset($a['choice_id']) && $a['choice_id'] !== '' ? (int)$a['choice_id'] : null;
        $txt = isset($a['answer_text']) ? $a['answer_text'] : null;
        $insAns->bind_param('iiis', $response_id, $qid, $cid, $txt);
        $insAns->execute();
    }
    $insAns->close();

    $mysqli->commit();
    // respuesta exitosa 
    echo json_encode(['success' => true, 'message' => 'Encuesta enviada correctamente']);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Error al guardar encuesta: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar la encuesta']);
    exit;
}
