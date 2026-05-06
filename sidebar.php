<?php
// Ensure session variables are accessible (assuming session_start() is called in the parent file)
$current_page = basename($_SERVER['PHP_SELF']);
$is_guest = (!isset($_SESSION['user_role']) || $_SESSION['user_role'] === 'guest');
?>

<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
        <i class="bi bi-grid-fill"></i>
        <span class="nav-link-text">Dashboard</span>
    </a>
    
    <a href="map.php" class="nav-link <?= ($current_page == 'map.php') ? 'active' : '' ?>">
        <i class="bi bi-map-fill"></i>
        <span class="nav-link-text">Map</span>
    </a>
    
    <?php if (!$is_guest): ?>
    <a href="schedule.php" class="nav-link <?= ($current_page == 'schedule.php') ? 'active' : '' ?>">
        <i class="bi bi-calendar3"></i>
        <span class="nav-link-text">Schedule</span>
    </a>
    <?php endif; ?>
    
    <a href="events.php" class="nav-link <?= ($current_page == 'events.php') ? 'active' : '' ?>">
        <i class="bi bi-calendar-event-fill"></i>
        <span class="nav-link-text">Events</span>
    </a>
    
    <a href="foodhubs.php" class="nav-link <?= ($current_page == 'foodhubs.php') ? 'active' : '' ?>">
        <i class="bi bi-shop"></i>
        <span class="nav-link-text">Food Hubs</span>
    </a>
    
    <a href="library.php" class="nav-link <?= ($current_page == 'library.php') ? 'active' : '' ?>">
        <i class="bi bi-book-fill"></i>
        <span class="nav-link-text">Library</span>
    </a>

    <div class="sidebar-footer">
        <a href="logout.php">
            <i class="bi bi-box-arrow-left"></i>
            <span class="sidebar-footer-label">Log Out</span>
        </a>
    </div>
</aside>    

<style>
    :root {
        --primary: #2ECC40;
        --bg-hover: #f0fdf2;
        --sidebar-w: 230px;
        --sidebar-c: 54px;
    }

    .sidebar {
        width: var(--sidebar-w);
        background: #fff;
        border-right: 1px solid #e8e8e8;
        display: flex;
        flex-direction: column;
        padding: 12px 0;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        transition: width .25s ease;
        z-index: 1000;
    }

    .sidebar.collapsed { 
    width: var(--sidebar-c); 
    }

    .sidebar.collapsed .nav-section-label, 
    .sidebar.collapsed .nav-link-text, 
    .sidebar.collapsed .sidebar-footer-label {
        display: none;
    }

    .sidebar.collapsed .nav-link {
    justify-content: center;
    padding: 10px 0;
    border-right: none; 
    }

    .sidebar.collapsed .sidebar-footer {
    padding: 15px 0 5px;
    display: flex;
    justify-content: center;
    }

    .sidebar .nav-section-label {
        font-size: .65rem;
        font-weight: 800;
        color: #ccc;
        text-transform: uppercase;
        padding: 15px 20px 5px;
    }

    .sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 20px;
        font-size: .88rem;
        font-weight: 700;
        color: #444;
        text-decoration: none;
    }

    /* 1. Top Navbar setup */
.ud-topbar {
    height: 54px; /* Fix the height */
    position: sticky;
    top: 0;
    z-index: 1001; /* Ensure it stays above the sidebar */
}

    .sidebar {
        position: fixed;
        top: 54px; /* Start exactly where the navbar ends */
        height: calc(100vh - 54px); /* Fill the rest of the screen */
        width: 230px;
        z-index: 1000;
    }

    .content-area {
        margin-top: 0; /* Content starts under the topbar automatically */
        margin-left: 230px; /* Offset by the sidebar width */
        transition: margin-left 0.25s ease;
    }


    .sidebar.collapsed + .content-area {
        margin-left: 54px; /* Match the collapsed width */
    }

    .sidebar .nav-link:hover { background: var(--bg-hover); color: var(--primary); }
    .sidebar .nav-link.active { background: #eafbec; color: var(--primary); border-right: 3px solid var(--primary); }
    .sidebar .nav-link i { font-size: 1.1rem; width: 20px; text-align: center; }

    .sidebar-footer { margin-top: auto; border-top: 1px solid #eee; padding: 15px 20px 5px; }
    .sidebar-footer a { color: #e74c3c; }

    @media(max-width: 640px) {
        .sidebar { transform: translateX(-100%); width: var(--sidebar-w) !important; }
        .sidebar.mobile-open { transform: translateX(0); }
    }
</style>