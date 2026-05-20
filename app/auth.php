<?php
ob_start();

// configurare sesiuni pe Redis
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis_cache:6379');
session_start();

// configuratie Mail
ini_set("SMTP", "mailpit");
ini_set("smtp_port", "1025");

// conexiune DB Master
$master_conf = ['host' => 'db_master', 'db' => 'library_app_db', 'user' => 'root', 'pass' => 'root'];

try {
    $db = new PDO("mysql:host={$master_conf['host']};dbname={$master_conf['db']};charset=utf8mb4", $master_conf['user'], $master_conf['pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Error: Couldn't connect to db_master.");
}

// functie pentru Mail
function send_direct_mail($to, $subject, $message) {
    $socket = @fsockopen("mailpit", 1025, $errno, $errstr, 2);
    if (!$socket) return false;
    
    fputs($socket, "HELO localhost\r\n"); fgets($socket);
    fputs($socket, "MAIL FROM: <admin@biblioteca.ro>\r\n"); fgets($socket);
    fputs($socket, "RCPT TO: <$to>\r\n"); fgets($socket);
    fputs($socket, "DATA\r\n"); fgets($socket);
    fputs($socket, "Subject: $subject\r\n\r\n$message\r\n.\r\n"); fgets($socket);
    fputs($socket, "QUIT\r\n"); fclose($socket);
    return true;
}

$msg = "";
$msg_type = "danger";

// login
if (isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userRow && password_verify($pass, $userRow['password'])) {
        $_SESSION['user_id'] = $userRow['id'];
        $_SESSION['username'] = $userRow['username'];
        header("Location: index.php");
        exit();
    } else {
        $msg = "Incorrect username or password!";
    }
}

// register
if (isset($_POST['register'])) {
    $user = trim($_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        if ($stmt->execute([$user, $pass])) {
            // mail de confirmare
            send_direct_mail($user . "@library.ro", "New account", "Hello $user! Your account was succesfully activated.");
            header("Location: auth.php?success=1");
            exit();
        }
    } catch (Exception $e) {
        $msg = "Error: User already exists.";
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acces library | Authentication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; height: 100vh; display: flex; align-items: center; }
        .auth-card { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .auth-header { background: #1a1a1a; color: white; padding: 2rem; text-align: center; }
        .nav-pills .nav-link { color: #666; font-weight: 600; border-radius: 8px; }
        .nav-pills .nav-link.active { background-color: #1a1a1a; color: white; }
        .form-control { padding: 0.75rem; border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(26,26,26,0.1); border-color: #1a1a1a; }
        .btn-auth { padding: 0.8rem; border-radius: 10px; font-weight: 600; transition: all 0.3s; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            
            <?php if($msg): ?>
                <div class="alert alert-danger shadow-sm border-0 mb-4"><?= $msg; ?></div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success shadow-sm border-0 mb-4">Account created successfully!</div>
            <?php endif; ?>

            <div class="card auth-card">
                <div class="auth-header">
                    <h2 class="m-0 fw-bold">Library App</h2>
                </div>
                <div class="card-body p-4">
                    
                    <ul class="nav nav-pills nav-fill mb-4" id="authTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="pill" data-bs-target="#login" type="button">Login</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="register-tab" data-bs-toggle="pill" data-bs-target="#register" type="button">Register</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="login">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">User</label>
                                    <input type="text" name="username" class="form-control" placeholder="Type username" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted">Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                                </div>
                                <button type="submit" name="login" class="btn btn-dark w-100 btn-auth shadow-sm">Login</button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="register">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">New user</label>
                                    <input type="text" name="username" class="form-control" placeholder="Choose an username" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted">New password</label>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                                </div>
                                <button type="submit" name="register" class="btn btn-primary w-100 btn-auth shadow-sm">Create account</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
