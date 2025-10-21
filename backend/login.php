<?php
// login.php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Teacher-evaluation/'); exit;
}

require_once __DIR__ . '/db_connect.php';

$uname = trim($_POST['uname'] ?? '');
$psw   = $_POST['psw'] ?? '';

$stmt = $mysqli->prepare("SELECT id, name, password, role FROM user WHERE matricula = ?");
$stmt->bind_param('s', $uname);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user || md5($psw) !== $user['password']) {
    $_SESSION['error'] = 'Usuario o contraseña incorrectos';
    header('Location: /Teacher-evaluation/'); exit;
}


// Login OK
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

$role = strtolower(trim($user['role']));
switch ($role) {
    case 'alumnx':
        header('Location: /Teacher-evaluation/frontend/alumnxs/inicio-alumnxs.html');
        break;
    case 'docente':
        header('Location: /Teacher-evaluation/frontend/teachers/inicio-teachers.html');
        break;
    case 'admin':
        header('Location: /Teacher-evaluation/frontend/admin/inicio-admin.html');
        break;

}
exit;
