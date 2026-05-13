<?php
// 1. Ensure the session is active before trying to read variables!
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Safely establish the user ID
$id = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
if ($id > 0) {
    $_SESSION['user_id'] = $id; // Standardize it
}

$current_page = basename($_SERVER['PHP_SELF']);

// 3. FIXED GUEST LOGIC: If ID is 0, or role is explicitly 'guest', they are a guest.
$user_role = $_SESSION['user_role'] ?? '';
$is_guest = ($id === 0 || $user_role === 'guest');

// 4. Check Google Link Status (Only if NOT a guest)
$is_google_linked = false; 

if (!$is_guest) {
    require_once 'db.php'; 
    
    $check_stmt = $conn->prepare("SELECT googleid FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_stmt->bind_result($db_googleid);
    
    if ($check_stmt->fetch()) {
        // Robust check: ensure it is not null and not just empty spaces
        if ($db_googleid !== null && trim($db_googleid) !== '') {
            $is_google_linked = true; 
        }
    }
    $check_stmt->close();
}
?>

<aside class="sidebar" id="sidebar">
    <div class="nav-section-label mt-2">Realm Navigation</div>
    
    <a href="dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
        <i class="bi bi-grid-fill"></i>
        <span class="nav-link-text">Dashboard</span>
    </a>
    
    <a href="map.php" class="nav-link <?= ($current_page == 'map.php' || $current_page == 'directions.php' ) ? 'active' : '' ?>">
        <i class="bi bi-map-fill"></i>
        <span class="nav-link-text">Map</span>
    </a>
    
    <?php if (!$is_guest): ?>
    <a href="schedule.php" class="nav-link <?= ($current_page == 'schedule.php') ? 'active' : '' ?>">
        <i class="bi bi-journal-bookmark-fill"></i>
        <span class="nav-link-text">Schedule</span>
    </a>
    <?php endif; ?>
    
    <a href="events.php" class="nav-link <?= ($current_page == 'events.php' || $current_page == 'eventguide.php') ? 'active' : '' ?>">
        <i class="bi bi-stars"></i>
        <span class="nav-link-text">Events</span>
    </a>
    
    <a href="foodhubs.php" class="nav-link <?= ($current_page == 'foodhubs.php') ? 'active' : '' ?>">
        <i class="bi bi-shop"></i>
        <span class="nav-link-text">Food Hubs</span>
    </a>
    
    <?php if (!$is_guest): ?>
    <a href="social.php" class="nav-link <?= ($current_page == 'social.php' || $current_page == 'profile.php') ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i>
        <span class="nav-link-text">Community Realm</span>
    </a>
    <?php endif; ?>

    <div class="sidebar-footer">
        <!-- HIDE SETTINGS FROM GUESTS -->
        <?php if (!$is_guest): ?>
        <a href="#" data-bs-toggle="modal" data-bs-target="#settingsModal" class="mb-3">
            <i class="bi bi-gear-fill fs-5"></i>
            <span class="sidebar-footer-label">Account Settings</span>
        </a>
        <?php endif; ?>

        <a href="logout.php">
            <i class="bi bi-box-arrow-left fs-5"></i>
            <span class="sidebar-footer-label">Abandon Realm</span>
        </a>
    </div>
</aside>    

<!-- WRAP ENTIRE MODAL IN GUEST CHECK -->
<?php if (!$is_guest): ?>
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content realm-modal">
            <div class="modal-header">
                <h5 class="modal-title font-cinzel" id="settingsModalLabel">Identity Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_profile.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-gold-muted">Given Name</label>
                        <input type="text" name="first_name" class="form-control realm-input" value="<?= htmlspecialchars($_SESSION['user']['firstname'] ?? $_SESSION['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-gold-muted">Last Name</label>
                        <input type="text" name="last_name" class="form-control realm-input" value="<?= htmlspecialchars($_SESSION['user']['lastname'] ?? $_SESSION['last_name'] ?? '') ?>" required>
                    </div>
                    
                    <hr class="realm-hr">
                    
                    <div class="d-grid mt-4">
                        <label class="form-label text-gold-muted mb-2">Connected Accounts</label>
                        
                        <?php if (!$is_google_linked): ?>
                            <a href="googlelogin.php?from=settings" class="btn btn-outline-google">
                                <i class="bi bi-google me-2"></i> Link Google Account
                            </a>                        
                        <?php else: ?>
                            <div class="google-linked-status d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-check-circle-fill text-success"></i> 
                                    <span class="ms-2 text-white">Google Account Linked</span>
                                </div>
                                <a href="unlink_google.php" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to sever your tie to this Google account?');">
                                    Unlink
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-realm-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-realm-gold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    .sidebar {
        width: 230px;
        background: #000; 
        border-right: 1px solid var(--star-gold); 
        display: flex;
        flex-direction: column;
        padding: 12px 0;
        height: calc(100vh - 60px); 
        position: fixed;
        top: 60px;
        left: 0;
        transition: width .25s ease;
        z-index: 1000;
        box-shadow: 2px 0 15px rgba(255, 218, 108, 0.05);
    }

    .sidebar.collapsed { width: 54px; }
    
    .sidebar.collapsed .nav-section-label, 
    .sidebar.collapsed .nav-link-text, 
    .sidebar.collapsed .sidebar-footer-label {
        display: none;
    }

    .sidebar.collapsed .nav-link { justify-content: center; padding: 12px 0; border-right: none; }
    .sidebar.collapsed .sidebar-footer { padding: 15px 0 5px; display: flex; justify-content: center; }

    .sidebar .nav-section-label {
        font-family: 'Cinzel', serif;
        font-size: .65rem;
        font-weight: 800;
        color: rgba(255,255,255,0.3);
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 10px 20px 5px;
    }

    .sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        font-size: .9rem;
        font-weight: 700;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        transition: 0.3s ease;
        border-right: 3px solid transparent;
    }

    
    .sidebar .nav-link:hover { 
        background: rgba(182, 255, 146, 0.05);
        color: var(--firefly-glow); 
    }
    
    .sidebar .nav-link.active { 
        background: var(--card-bg); 
        color: var(--star-gold); 
        border-right: 3px solid var(--star-gold); 
        text-shadow: 0 0 8px rgba(255, 218, 108, 0.4);
    }
    
    .sidebar .nav-link i { font-size: 1.1rem; width: 20px; text-align: center; }


    .sidebar-footer { 
        margin-top: auto; 
        border-top: 1px dashed rgba(255, 218, 108, 0.2); 
        padding: 15px 20px 20px; 
    }
    
    .sidebar-footer a { 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        font-weight: 800; 
        color: #ff4c4c; 
        text-decoration: none; 
        transition: 0.3s; 
    }
    
    .sidebar-footer a:hover { 
        color: #ff8080; 
        text-shadow: 0 0 10px rgba(255, 76, 76, 0.6); 
    }

    .sidebar.collapsed .sidebar-footer a i { margin: 0 auto; }
    .sidebar.collapsed .sidebar-footer a { gap: 0; }
    
    @media(max-width: 960px) {
        .sidebar { transform: translateX(-100%); width: 230px !important; }
        .sidebar.mobile-open { transform: translateX(0); }
    }
    .realm-modal {
        background: #111;
        border: 1px solid var(--star-gold);
        box-shadow: 0 0 20px rgba(255, 218, 108, 0.2);
        color: #fff;
    }
    
    .modal-header {
        border-bottom: 1px solid rgba(255, 218, 108, 0.2);
    }
    
    .font-cinzel { font-family: 'Cinzel', serif; }
    
    .text-gold-muted {
        color: var(--star-gold);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.8;
    }
    
    .realm-input {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
    }
    
    .realm-input:focus {
        background: rgba(255, 255, 255, 0.08);
        border-color: var(--star-gold);
        color: #fff;
        box-shadow: 0 0 8px rgba(255, 218, 108, 0.3);
    }
    
    .btn-realm-gold {
        background: var(--star-gold);
        color: #000;
        font-weight: 700;
        border: none;
    }
    
    .btn-realm-gold:hover {
        background: #ffd04b;
        box-shadow: 0 0 10px rgba(255, 218, 108, 0.5);
    }
    
    .btn-realm-ghost {
        color: rgba(255,255,255,0.6);
        border: none;
    }
    
    .btn-outline-google {
        border: 1px solid #db4437;
        color: #db4437;
        transition: 0.3s;
    }
    
    .btn-outline-google:hover {
        background: #db4437;
        color: white;
    }
    
    .realm-hr { border-color: rgba(255, 218, 108, 0.2); }
    
    .google-linked-status {
        background: rgba(182, 255, 146, 0.1);
        padding: 10px 15px; 
        border-radius: 5px;
        border: 1px solid var(--firefly-glow);
        text-align: left;
    }
    
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Only attempt to show the modal if it actually exists in the DOM (not a guest)
    const settingsModalEl = document.getElementById('settingsModal');
    if (settingsModalEl) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('showSettings')) {
            var settingsModal = new bootstrap.Modal(settingsModalEl);
            settingsModal.show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
});
</script>