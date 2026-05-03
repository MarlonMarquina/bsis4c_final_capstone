<style>
   :root {
    --sidebar-width:  250px;  /* ← Change back to 320px */
    --sidebar-close-width: 88px;
        --sidebar-color: #006400 !important; /* Ensured Green */
        --text-color: #ffffff;
        --tran-05: all 0.5s ease;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: var(--sidebar-width);
        padding: 10px 14px;
        background: var(--sidebar-color);
        transition: var(--tran-05);
        z-index: 9999 !important; /* Highest layer */
        box-shadow: 4px 0 10px rgba(0,0,0,0.3);
    }

   

    /* Header Section */
    .sidebar .image-text {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
    }

    .sidebar .image img {
        width: 45px;
        border-radius: 6px;
    }

    .sidebar .header-text {
        display: flex;
        flex-direction: column;
        white-space: nowrap;
        overflow: hidden;
        opacity: 1;
        transition: var(--tran-05);
    }

    

    .header-text .name {
        font-size: 18px; 
        font-weight: 600;
        color: var(--text-color);
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .header-text .role {
        font-size: 13px;
        color: rgba(255,255,255,0.7);
        display: block;
    }

    /* Toggle Button - CRITICAL FIX */
    .sidebar .toggle {
        position: absolute;
        top: 30px; /* Moved higher to avoid being covered by dashboard cards */
        right: -15px;
        height: 30px;
        width: 30px;
        background-color: #f39c12 !important; /* Orange for visibility */
        color: #fff !important;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        cursor: pointer !important;
        z-index: 10000 !important; /* Above the sidebar */
        transition: var(--tran-05);
    }

   

    

    /* Menu Links */
    .sidebar .menu-bar {
        height: calc(100% - 70px);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        margin-top: 20px;
    }

    .sidebar li {
        height: 50px;
        list-style: none;
        display: flex;
        align-items: center;
        margin-top: 10px;
    }

    .sidebar li .icon, .sidebar li .text {
        color: var(--text-color);
    }

    .sidebar li a {
        display: flex;
        align-items: center;
        height: 100%;
        width: 100%;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .sidebar li a:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .sidebar li .icon {
        min-width: 60px;
        font-size: 20px;
        display: flex;
        justify-content: center;
    }

    .sidebar .text {
        font-size: 16px;
        font-weight: 500;
        transition: var(--tran-05);
    }

    .sidebar.close .text {
        opacity: 0;
    }
    
    /* ========== ADDED: ACTIVE PAGE HIGHLIGHT (PERMANENT) ========== */
    /* This highlights the clicked button and keeps it highlighted */
    .sidebar li a.active {
        background-color: #b6c1ab !important; /* Soft green highlight */
        color: #1a3c1a !important;
        border-left: 4px solid #2d5016 !important;
        font-weight: 600 !important;
    }
    
    .sidebar li a.active .icon,
    .sidebar li a.active .text {
        color: #1a3c1a !important;
    }
    
    /* Flash effect when clicking (temporary feedback) */
    .sidebar li a.click-flash {
        background-color: #b6c1ab !important;
        transform: scale(0.97);
        transition: all 0.08s ease-out;
    }
    
    .sidebar li a.click-flash .icon,
    .sidebar li a.click-flash .text {
        color: #1a3c1a !important;
    }
</style>

<nav class="sidebar" id="mainSidebar">
    <header style="position: relative;">
        <div class="image-text">
            <span class="image"><img src="bpc-logo.png" alt="Logo"></span>
            <div class="text header-text">
                <span class="name"><?php echo htmlspecialchars($signatoryFullName ?? 'Admin'); ?></span>
                <span class="role"><?php echo htmlspecialchars($userRole ?? 'Administrator'); ?></span>
            </div>
        </div>
        
    </header>

    <div class="menu-bar">
        <div class="menu">
            <ul class="menu-links" style="padding: 0;">
                <li class="nav-link">
                    <a href="admin_dashboard.php">
                        <i class='bx bx-home-alt icon'></i>
                        <span class="text nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="manage_students.php">
                        <i class='bx bx-list-check icon'></i>
                        <span class="text nav-text">Manage Students</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="manage_signatories.php">
                        <i class='bx bx-user-check icon'></i>
                        <span class="text nav-text">Manage Signatories</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="manage_applications.php">
                        <i class='bx bx-file icon'></i>
                        <span class="text nav-text">Applications</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="admin_users.php">
                        <i class='bx bx-group icon'></i>
                        <span class="text nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-link">
    <a href="power_admin.php">
        <i class='bx bx-cog icon'></i>
        <span class="text nav-text">System Settings</span>
    </a>
</li>
<li class="nav-link">
    <a href="archived_records.php">
        <i class='bx bx-archive icon'></i>
        <span class="text nav-text">Archived Records</span>
    </a>
</li>
    </li>
            </ul>
        </div>
        <div class="bottom-content" style="padding: 0;">
            <li>
                <a href="#" onclick="confirmLogout()">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">Logout</span>
                </a>
            </li>
        </div>
    </div>
</nav>

<script>
// We'll use direct ID access for reliability
(function() {
    const sidebar = document.getElementById('mainSidebar');
    const toggle = document.getElementById('sidebarToggle');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent triggering other scripts
            sidebar.classList.toggle('close');
        });
    }
})();

function confirmLogout() {
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
    }
}

// ========== ADDED: ACTIVE PAGE HIGHLIGHT FUNCTIONALITY ==========
// This keeps the highlight on the clicked button so you know which page you're on
(function() {
    // Get all navigation links in the sidebar
    const allSidebarLinks = document.querySelectorAll('.sidebar .menu-links a, .sidebar .bottom-content a');
    
    // Function to apply flash effect (temporary feedback)
    function addFlashEffect(element) {
        element.classList.add('click-flash');
        setTimeout(() => {
            element.classList.remove('click-flash');
        }, 150);
    }
    
    // Function to set the active link (permanent highlight)
    function setActiveLink(activeElement) {
        // Remove active class from all links
        allSidebarLinks.forEach(link => {
            link.classList.remove('active');
        });
        // Add active class to the clicked link
        if (activeElement) {
            activeElement.classList.add('active');
        }
    }
    
    // Function to get the current page URL (to know which page we're on)
    function getCurrentPageUrl() {
        return window.location.pathname.split('/').pop();
    }
    
    // Auto-detect which page is currently open and highlight the matching button
    function highlightCurrentPage() {
        const currentPage = getCurrentPageUrl();
        
        allSidebarLinks.forEach(link => {
            const linkHref = link.getAttribute('href');
            if (linkHref && linkHref !== '#' && linkHref !== 'javascript:void(0)') {
                // Get the filename from the href
                const linkPage = linkHref.split('/').pop();
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            }
        });
    }
    
    // When a link is clicked, add flash effect and set as active
    allSidebarLinks.forEach(link => {
        // Exclude the logout link from setting active (optional)
        if (link.getAttribute('onclick') !== 'confirmLogout()') {
            link.addEventListener('click', function(e) {
                // Add flash effect so you know you clicked something
                addFlashEffect(this);
                
                // Set as active link (permanent highlight)
                setActiveLink(this);
                
                // Let the browser navigate normally (we don't prevent default behavior)
                // We don't prevent it so it actually redirects
            });
        }
    });
    
    // When the page loads, highlight the current page button
    highlightCurrentPage();
    
    // For the logout button, only add flash effect, not active highlight
    const logoutLink = document.querySelector('.bottom-content a[onclick="confirmLogout()"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            addFlashEffect(this);
            // Don't add active class for logout
        });
    }
})();
</script>