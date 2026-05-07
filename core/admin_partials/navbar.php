<?php
// ============================================================
// admin/partials/navbar.php
// ============================================================
$admin   = current_admin();
$current = basename($_SERVER['PHP_SELF']);
?>
<style>
  /* Theme-matched Navbar Styles */
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,600&family=Montserrat:wght@400;500;600;700&display=swap');

  .admin-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    background: #ffffff;
    padding: 16px 32px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    border-bottom: 3px solid #cc0000;
    font-family: 'Montserrat', sans-serif;
  }
  
  .nav-brand {
    display: flex;
    align-items: center;
    text-decoration: none;
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-weight: 700;
    color: #222;
    letter-spacing: 0.5px;
  }
  
  .nav-brand img {
    height: 36px;
    width: auto;
    margin-right: 12px;
  }

  .nav-brand span {
    color: #cc0000;
    margin-left: 6px;
    font-family: 'Montserrat', sans-serif;
    font-size: 1rem;
    font-weight: 600;
    background: #ffe5e5;
    padding: 2px 8px;
    border-radius: 4px;
  }

  .mobile-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 1.8rem;
    color: #cc0000;
    cursor: pointer;
    padding: 0;
  }

  .nav-menu {
    display: flex;
    align-items: center;
    gap: 32px;
  }

  .nav-links {
    display: flex;
    gap: 24px;
  }

  .nav-links a {
    text-decoration: none;
    color: #666666;
    font-weight: 500;
    font-size: 0.95rem;
    transition: color 0.2s ease;
    padding: 6px 0;
  }

  .nav-links a:hover {
    color: #cc0000;
  }

  .nav-links a.active {
    color: #cc0000;
    font-weight: 600;
    border-bottom: 2px solid #cc0000;
  }

  .nav-user {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 0.9rem;
    color: #444;
  }

  .role-badge {
    background: #ffe5e5;
    color: #cc0000;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .nav-logout {
    color: #666;
    text-decoration: none;
    font-weight: 600;
    border-left: 1px solid #eee;
    padding-left: 16px;
    transition: color 0.2s ease;
  }

  .nav-logout:hover {
    color: #cc0000;
  }

/* ── Mobile Responsiveness ── */
  @media (max-width: 850px) {
    .admin-nav {
      padding: 12px 20px;
      position: relative; /* Anchors the dropdown menu to the nav bar */
    }
    
    .mobile-toggle {
      display: block;
    }

    .nav-menu {
      display: none; /* Hidden by default on mobile */
      position: absolute; /* Forces the menu to float above page content */
      top: 100%; /* Pushes it exactly below the bottom edge of the nav */
      left: 0;
      width: 100%;
      background: #ffffff; /* Adds the solid background */
      z-index: 1000; /* Ensures it stays on top of all other elements */
      box-shadow: 0 10px 15px rgba(0, 0, 0, 0.05); /* Adds a subtle drop shadow */
      box-sizing: border-box;
      padding: 20px 32px 32px 32px; /* Adds breathing room inside the menu */
      flex-direction: column;
      align-items: flex-start;
      gap: 16px;
      margin-top: 0;
      border-top: 1px solid #eee;
    }

    .nav-menu.active {
      display: flex; /* Shown when toggled */
    }

    .nav-links {
      flex-direction: column;
      width: 100%;
      gap: 16px;
    }

    .nav-links a {
      font-size: 1.05rem; /* Slightly larger for easier mobile tapping */
      display: block;
      width: 100%;
    }

    .nav-links a.active {
      border-bottom: none;
      border-left: 3px solid #cc0000;
      padding-left: 10px;
    }

    .nav-user {
      width: 100%;
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;
      padding-top: 20px;
      margin-top: 8px;
      border-top: 1px solid #eee;
    }

    .nav-logout {
      border-left: none;
      padding-left: 0;
      margin-top: 8px;
    }
  }
</style>

<nav class="admin-nav">
  <a href="/admin/dashboard.php" class="nav-brand">
    <img src="/assets/dma-logo.png" alt="Dhaka Model Agency">
    <span>Admin</span>
  </a>
  
  <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Menu">☰</button>

  <div class="nav-menu" id="navMenu">
    <div class="nav-links">
      <a href="/admin/dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>">📋 Registrations</a>
      
      <?php if ($admin['role'] === 'superadmin'): ?>
      <a href="/admin/users.php"     class="<?= $current==='users.php'    ?'active':'' ?>">👥 Users</a>
      <a href="/admin/history.php"   class="<?= $current==='history.php'  ?'active':'' ?>">🕒 Activity</a>
      <?php endif; ?>
      
      <?php if ($admin['role'] !== 'viewer'): ?>
      <a href="/admin/settings.php"  class="<?= $current==='settings.php' ?'active':'' ?>">⚙️ Settings</a>
      <?php endif; ?>
    </div>

    <div class="nav-user">
      <span>👤 <?= htmlspecialchars($admin['full_name']) ?></span>
      <span class="role-badge"><?= $admin['role'] ?></span>
      <a href="/admin/logout.php" class="nav-logout">Logout</a>
    </div>
  </div>
</nav>

<script>
  // Simple toggle for the mobile menu
  document.getElementById('mobileToggle').addEventListener('click', function() {
    const menu = document.getElementById('navMenu');
    menu.classList.toggle('active');
    // Change icon between hamburger and close (X)
    this.innerHTML = menu.classList.contains('active') ? '✕' : '☰';
  });
</script>