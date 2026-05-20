<?php
ob_start();
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis_cache:6379');
session_start();

// header-e anti-cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['username'])) {
    header("Location: auth.php");
    exit();
}

$master_conf = ['host' => 'db_master', 'db' => 'library_app_db', 'user' => 'root', 'pass' => 'root'];
$slave_conf  = ['host' => 'db_slave',  'db' => 'library_app_db', 'user' => 'root', 'pass' => 'root'];

function getConn($config) {
    try {
        return new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4", $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $e) { return null; }
}

$u_id = $_SESSION['user_id'] ?? null;

// create - master
if (isset($_POST['add_book'])) {
    $db = getConn($master_conf);
    if ($db && $u_id) {
        $stmt = $db->prepare("INSERT INTO books (title, author, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['author'], $u_id]);
        header("Location: index.php?msg=added"); exit;
    }
}

// update - maser
if (isset($_POST['update_book'])) {
    $db = getConn($master_conf);
    if ($db && $u_id) {
        $stmt = $db->prepare("UPDATE books SET title = ?, author = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['title'], $_POST['author'], $_POST['book_id'], $u_id]);
        header("Location: index.php?msg=updated"); exit;
    }
}

// delete - master
if (isset($_GET['delete'])) {
    $db = getConn($master_conf);
    if ($db && $u_id) {
        $stmt = $db->prepare("DELETE FROM books WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['delete'], $u_id]);
        header("Location: index.php?msg=deleted"); exit;
    }
}

// citire - slave
sleep(1); // folosit pt replicare
$db_read = getConn($slave_conf);
$books = [];
if ($db_read && $u_id) {
    $stmt = $db_read->prepare("SELECT * FROM books WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$u_id]);
    $books = $stmt->fetchAll();
}

// redis counter
$redis = new Redis();
$vizite = 0;
try {
    $redis->connect('redis_cache', 6379);
    if (strpos($_SERVER['REQUEST_URI'], 'favicon.ico') === false) {
        $vizite = $redis->incr('vizite_pagina');
    }
} catch (Exception $e) { $vizite = "N/A"; }

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrate library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --glass: rgba(255, 255, 255, 0.9); }
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .navbar { background: #1a1a1a; color: white; }
        .main-card { background: var(--glass); border: none; border-radius: 15px; box-shadow: 0 8px 32px rgba(0,0,0,0.05); }
        .btn-custom { border-radius: 8px; transition: all 0.3s; }
        .table-container { background: white; border-radius: 15px; overflow: hidden; }
        .badge-server { font-size: 0.7rem; letter-spacing: 0.5px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg mb-5 shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold"><i class="bi bi-book-half me-2"></i>Library app</span>
        <div class="d-flex align-items-center">
            <span class="badge bg-secondary me-3 badge-server">HOST: <?= gethostname() ?></span>
            <span class="badge bg-secondary me-3 badge-server">IP: <?= gethostbyname(gethostname()) ?></span>
            <span class="text-light small me-4"><i class="bi bi-eye me-1"></i> <?= $vizite ?> vizite</span>
            <a href="logout.php" class="btn btn-outline-light btn-sm px-3">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="card main-card p-4 mb-4">
                <h5 class="fw-bold mb-3">Add a new book</h5>
                <form method="POST" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" name="title" class="form-control" placeholder="Title" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="author" class="form-control" placeholder="Author" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="add_book" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-container shadow-sm">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <h6 class="m-0 fw-bold text-uppercase text-muted">Collection</h6>
                </div>
                <table class="table table-hover align-middle m-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Book Info</th>
                            <th>Added at</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($books)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">There's no book.</td></tr>
                        <?php else: ?>
                            <?php foreach ($books as $book): ?>
                            <tr>
                                <td class="ps-4 text-muted small">#<?= $book['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($book['title']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($book['author']) ?></div>
                                </td>
                                <td class="small text-muted">
                                    <?= isset($book['created_at']) ? date('d M Y, H:i', strtotime($book['created_at'])) : 'Recent' ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-warning border-0" data-bs-toggle="modal" data-bs-target="#editModal<?= $book['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?delete=<?= $book['id'] ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Are you sure?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal<?= $book['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">Edit book</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Title</label>
                                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($book['title']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Author</label>
                                                    <input type="text" name="author" class="form-control" value="<?= htmlspecialchars($book['author']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-0">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="update_book" class="btn btn-primary btn-sm px-4">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>


        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
