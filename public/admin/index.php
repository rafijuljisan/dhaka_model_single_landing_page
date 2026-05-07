<?php
// ============================================================
// admin/index.php  — Admin Login
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

// Already logged in → go to dashboard
if (is_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error = '';
$msg   = '';

// System messages
$msg_map = [
    'session_expired' => 'Your session expired. Please log in again.',
    'logged_out'      => 'You have been logged out.',
];
if (!empty($_GET['msg'])) {
    $msg = $msg_map[clean($_GET['msg'])] ?? '';
}

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = attempt_login($username, $password);
        if ($result['success']) {
            redirect('/admin/dashboard.php');
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Secure Login – Dhaka Model Agency</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,600&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary-red: #cc0000;
    --primary-hover: #a30000;
    --bg-light: #f9f9fa;
    --text-dark: #222222;
    --text-muted: #666666;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  
  body { 
    background: var(--bg-light); 
    display: flex; 
    align-items: center;
    justify-content: center; 
    min-height: 100vh; 
    font-family: 'Montserrat', sans-serif; 
    color: var(--text-dark);
  }
  
  .card { 
    background: #fff; 
    border-radius: 12px; 
    padding: 48px 40px;
    width: 100%; 
    max-width: 420px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
    border-top: 5px solid var(--primary-red);
  }
  
  h2 { 
    text-align: center; 
    margin-bottom: 8px; 
    color: var(--text-dark); 
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
  }
  
  .subtitle {
    text-align: center;
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 32px;
  }
  
  label { 
    display: block; 
    font-size: 0.85rem; 
    font-weight: 600;
    color: var(--text-dark); 
    margin-bottom: 6px; 
    margin-top: 20px; 
  }
  
  input[type=text], input[type=password] {
    width: 100%; 
    padding: 12px 16px; 
    border: 1.5px solid #e5e7eb;
    border-radius: 6px; 
    font-size: 1rem; 
    font-family: 'Montserrat', sans-serif;
    transition: all 0.2s ease;
    background: #f9f9fa;
  }
  
  input:focus { 
    outline: none; 
    border-color: var(--primary-red); 
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(204, 0, 0, 0.1);
  }
  
  .btn { 
    width: 100%; 
    margin-top: 32px; 
    padding: 14px;
    background: var(--primary-red); 
    color: #fff; 
    border: none;
    border-radius: 6px; 
    font-size: 0.95rem; 
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer; 
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
  }
  
  .btn:hover { 
    background: var(--primary-hover); 
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(204, 0, 0, 0.2);
  }
  
  .error { 
    background: #fee2e2; 
    border: 1px solid #fca5a5; 
    border-radius: 6px;
    padding: 12px 16px; 
    color: #991b1b; 
    font-size: 0.9rem; 
    margin-bottom: 20px; 
  }
  
  .msg { 
    background: #dcfce7; 
    border: 1px solid #86efac; 
    border-radius: 6px;
    padding: 12px 16px; 
    color: #166534; 
    font-size: 0.9rem; 
    margin-bottom: 20px; 
  }
</style>
</head>
<body>
<div class="card">
  <h2>DMA Portal</h2>
  <p class="subtitle">Authorized Personnel Only</p>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($msg):   ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <form method="POST" action="/admin/index.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

   <label for="username">Username or Email</label>
    <input type="text" id="username" name="username" 
           placeholder="admin or admin@example.com" 
           required autofocus
           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn">Secure Login</button>
  </form>
</div>
</body>
</html>