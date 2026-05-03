<?php
$signatoryInfo     = $signatoryInfo ?? $signatoryData ?? [];
$signatoryFullName = $signatoryFullName ?? ($signatoryInfo['full_name'] ?? 'Signatory');
?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="styles.css">

<style>
    /* ========== ACTIVE PAGE HIGHLIGHT (PERMANENT) ========== */
    /* This keeps the highlight on the clicked button so you know which page you're on */
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
    
    /* Ensure smooth transitions */
    .sidebar li a {
        transition: all 0.3s ease;
    }
</style>

<nav class="sidebar">
    <header>
        <div class="image-text">
            <span class="image"><img src="bpc-logo.png" alt="Logo"></span>
            <div class="text header-text">
                <span class="name"><?php echo htmlspecialchars($signatoryFullName); ?></span>
                <span class="role"><?php echo htmlspecialchars($signatoryInfo['signatory_type'] ?? 'Signatory'); ?></span>
            </div>
        </div>
    </header>

    <div class="menu-bar">
        <div class="menu">
            <ul class="menu-links">
                <li class="nav-link">
                    <a href="signatory_dashboard.php">
                        <i class='bx bx-home-alt icon'></i>
                        <span class="text nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="requirementssigna.php">
                        <i class='bx bx-list-check icon'></i>
                        <span class="text nav-text">Requirements</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="signatory_history.php">
                        <i class='bx bx-history icon'></i>
                        <span class="text nav-text">History</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="announcement.php">
                        <i class='bx bx-message icon'></i>
                        <span class="text nav-text">Announcements</span>
                    </a>
                </li>
                <li class="nav-link">
                    <a href="signatory_profile.php">
                        <i class='bx bx-user icon'></i>
                        <span class="text nav-text">Profile</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="bottom-content">
            <li class="">
                <a href="#" onclick="confirmLogout()">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">Logout</span>
                </a>
            </li>
        </div>
    </div>
</nav>

<script>
// ========== ORIGINAL TOGGLE FUNCTION (FIXED TO HAVE MAIN SIDEBAR ID) ==========
(function() {
    // Make sure the sidebar has an ID for the toggle functionality
    const sidebar = document.querySelector('.sidebar');
    if (sidebar && !sidebar.id) {
        sidebar.id = 'mainSidebar';
    }
    
    const toggle = document.getElementById('sidebarToggle');
    
    // If there's no toggle button in the original code, don't throw an error
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
    // Get all navigation links in the sidebar (excluding logout)
    const allNavLinks = document.querySelectorAll('.sidebar .menu-links a');
    const logoutLink = document.querySelector('.bottom-content a[onclick="confirmLogout()"]');
    
    // Function to apply flash effect (temporary - so you know you clicked something)
    function addFlashEffect(element) {
        element.classList.add('click-flash');
        setTimeout(() => {
            element.classList.remove('click-flash');
        }, 150);
    }
    
    // Function to set the active link (permanent highlight)
    function setActiveLink(activeElement) {
        // Remove active class from all links
        allNavLinks.forEach(link => {
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
        
        allNavLinks.forEach(link => {
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
    allNavLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add flash effect so you know you clicked something
            addFlashEffect(this);
            
            // Set as active link (permanent highlight)
            setActiveLink(this);
            
            // Let the browser navigate normally (we don't prevent default behavior)
        });
    });
    
    // For the logout button, only add flash effect, not active highlight
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            addFlashEffect(this);
            // Don't add active class for logout
        });
    }
    
    // When the page loads, highlight the current page button
    highlightCurrentPage();
})();
</script>