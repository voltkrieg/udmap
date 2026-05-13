<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="sidebar">
    <div class="nav-section-label">Analytics</div>
    
    <a href="admindashboard.php" class="nav-link <?= ($current_page == 'admindashboard.php') ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span class="nav-link-text">Statistical Dashboard</span>
    </a>
    
    <a href="userstatistics.php" class="nav-link <?= ($current_page == 'userstatistics.php') ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i>
        <span class="nav-link-text">User Statistics</span>
    </a>

    <a href="auditlog.php" class="nav-link <?= ($current_page == 'auditlog.php') ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i>
        <span class="nav-link-text">Audit Log</span>
    </a>

    <div class="nav-section-label">Management</div>

    <a href="addlocation.php" class="nav-link <?= ($current_page == 'addlocation.php') ? 'active' : '' ?>">
        <i class="bi bi-geo-alt-fill"></i>
        <span class="nav-link-text">Add Locations (Map)</span>
    </a>

    <a href="addevents.php" class="nav-link <?= ($current_page == 'addevents.php') ? 'active' : '' ?>">
        <i class="bi bi-calendar-event-fill"></i>
        <span class="nav-link-text">Add Events (Map)</span>
    </a>

    <a href="manage_places.php" class="nav-link <?= ($current_page == 'manage_places.php') ? 'active' : '' ?>">
        <i class="bi bi-image-fill"></i>
        <span class="nav-link-text">Add Place & Pictures</span>
    </a>


    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link text-danger logout-link p-0">
            <i class="bi bi-box-arrow-left"></i>
            <span class="nav-link-text">Log Out</span>
        </a>
    </div>
</aside>

<style>
    .sidebar {
        width: 230px;
        background: rgba(1, 26, 16, 0.95);
        border-right: 1px solid var(--border-glow);
        display: flex;
        flex-direction: column;
        padding: 12px 0;
        height: calc(100vh - 60px); 
        position: fixed;
        top: 60px;
        left: 0;
        transition: width .25s ease;
        z-index: 1000;
        backdrop-filter: blur(10px);
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.5);
    }

    .sidebar .nav-section-label {
        font-size: .75rem;
        font-family: 'Cinzel', serif;
        font-weight: 700;
        color: var(--star-gold);
        text-transform: uppercase;
        padding: 20px 20px 8px;
        letter-spacing: 2px;
        text-shadow: 0 0 5px rgba(255, 218, 108, 0.3);
    }

    .sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        font-size: .9rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .sidebar .nav-link:hover { 
        background: rgba(182, 255, 146, 0.05); 
        color: var(--firefly-glow); 
        text-shadow: 0 0 8px rgba(182, 255, 146, 0.4);
    }

    .sidebar .nav-link.active { 
        background: rgba(2, 36, 21, 0.85); 
        color: var(--firefly-glow); 
        border-right: 3px solid var(--firefly-glow); 
        box-shadow: inset 5px 0 15px rgba(182, 255, 146, 0.1);
        text-shadow: 0 0 8px rgba(182, 255, 146, 0.5);
    }

    .sidebar .nav-link i { 
        font-size: 1.2rem; 
        width: 24px; 
        text-align: center; 
        color: inherit;
    }

    .sidebar.collapsed { width: 54px; }
    .sidebar.collapsed .nav-link-text, 
    .sidebar.collapsed .nav-section-label { display: none; }
    .sidebar.collapsed .nav-link { justify-content: center; padding: 15px 0; border-right: none; }

    .sidebar-footer { 
        margin-top: auto; 
        border-top: 1px dashed rgba(255, 255, 255, 0.1); 
        padding: 15px 20px; 
    }
    
    .sidebar-footer .logout-link {
        color: #ff6b6b !important;
        padding: 10px 0;
    }

    .sidebar-footer .logout-link:hover {
        background: transparent;
        color: #ff8787 !important;
        text-shadow: 0 0 8px rgba(255, 107, 107, 0.6);
    }

    @media(max-width: 960px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.mobile-open { transform: translateX(0); }
    }
</style>