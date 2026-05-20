<?php
// conectare la sesiune cu redis
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis_cache:6379');
session_start();

// sterge toate variabilele din sesiune
$_SESSION = array();

// distrugerea completa a sesiunii
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// distruge sesiunea in Redis
session_destroy();

// redirectioneaza catre pagina de login
header("Location: auth.php");
exit();
?>
