<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="sidebar">
    <div class="nav-section-label">Analytics</div>
    
    <a href="admindashboard.php" class="nav-link <?= ($current_page == 'admindashboard.php') ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span class="nav-link-text">Statistical Dashboard</span>
    </a>
    
    <a href="user_statistics.php" class="nav-link <?= ($current_page == 'userstatistics.php') ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i>
        <span class="nav-link-text">User Statistics</span>
    </a>

    <a href="auditlog.php" class="nav-link <?= ($current_page == 'auditlog.php') ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i>
        <span class="nav-link-text">Audit Log</span>
    </a>

    <div class="nav-section-label">Management</div>

    <a href="add_location.php" class="nav-link <?= ($current_page == 'addlocation.php') ? 'active' : '' ?>">
        <i class="bi bi-geo-alt-fill"></i>
        <span class="nav-link-text">Add Locations (Map)</span>
    </a>

    <a href="manage_places.php" class="nav-link <?= ($current_page == 'manageplaces.php') ? 'active' : '' ?>">
        <i class="bi bi-image-fill"></i>
        <span class="nav-link-text">Add Place & Pictures</span>
    </a>


    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link text-danger p-0">
            <i class="bi bi-box-arrow-left"></i>
            <span class="nav-link-text">Log Out</span>
        </a>
    </div>
</aside>

<style>
    :root {
        --primary: #2ECC40;
        --bg-hover: #f0fdf2;
        --sidebar-w: 230px;
        --sidebar-c: 54px;
        --danger: #e74c3c;
    }

    .sidebar {
        width: var(--sidebar-w);
        background: #fff;
        border-right: 1px solid #e8e8e8;
        display: flex;
        flex-direction: column;
        padding: 12px 0;
        height: calc(100vh - 54px);
        position: fixed;
        top: 54px;
        left: 0;
        transition: width .25s ease;
        z-index: 1000;
    }

    /* Section Labels */
    .sidebar .nav-section-label {
        font-size: .65rem;
        font-weight: 800;
        color: #aaa;
        text-transform: uppercase;
        padding: 20px 20px 8px;
        letter-spacing: 1px;
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
        transition: all 0.2s;
    }

    .sidebar .nav-link:hover { 
        background: var(--bg-hover); 
        color: var(--primary); 
    }

    .sidebar .nav-link.active { 
        background: #eafbec; 
        color: var(--primary); 
        border-right: 3px solid var(--primary); 
    }

    .sidebar .nav-link i { 
        font-size: 1.1rem; 
        width: 20px; 
        text-align: center; 
    }

    /* Collapsed States */
    .sidebar.collapsed { width: var(--sidebar-c); }
    .sidebar.collapsed .nav-link-text, 
    .sidebar.collapsed .nav-section-label { display: none; }
    .sidebar.collapsed .nav-link { justify-content: center; padding: 12px 0; border-right: none; }

    /* Footer / Logout */
    .sidebar-footer { 
        margin-top: auto; 
        border-top: 1px solid #eee; 
        padding: 15px 20px; 
    }
    
    .sidebar-footer .nav-link-text {
        color: var(--danger);
    }

    /* Mobile handling */
    @media(max-width: 640px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.mobile-open { transform: translateX(0); }
    }
</style>