<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['user']);

$projectFolder = explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '', '/'))[0] ?? '';
$basePath = $projectFolder ? '/' . $projectFolder : '';
$logoUrl = $basePath . '/assets/images/logo.png';
$pagesBase = $basePath . '/Pages';
?>

<!-- Add font -->

<style>
    @media (max-width: 768px) {
        #fes-nav {
            padding: 12px 16px !important;
            gap: 12px !important;
            flex-wrap: wrap !important;
        }

        #fes-nav-toggle {
            display: inline-flex !important;
        }

        #fes-nav-menu {
            width: 100% !important;
            justify-content: flex-start !important;
            gap: 10px !important;
            flex-wrap: nowrap !important;
            flex-direction: column !important;
            align-items: stretch !important;
            display: none !important;
        }

        #fes-nav.is-open #fes-nav-menu {
            display: flex !important;
        }

        #fes-nav-menu a {
            font-size: 12px !important;
            padding: 10px 12px !important;
            border-radius: 8px !important;
        }
    }

    #fes-nav-toggle {
        display: none;
        height: 40px;
        width: 40px;
        align-items: center;
        justify-content: center;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #ffffff;
        color: #4b5563;
        cursor: pointer;
    }
</style>

<!-- Navigation -->
<nav id="fes-nav" style="background-color: #ffffff; padding: 20px 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid #f3f4f6; font-family: Georgia, 'Times New Roman', serif;">
    <!-- Logo -->
    <div style="display: flex; align-items: center;">
        <a href="<?php echo htmlspecialchars($pagesBase . '/index.php'); ?>" style="text-decoration: none;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="FES Logo" style="height: 34px; width: auto; display: block;">

            </div>
        </a>
    </div>

    <button id="fes-nav-toggle" type="button" aria-label="Toggle menu" aria-controls="fes-nav-menu" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>
    
    <!-- Menu -->
    <div id="fes-nav-menu" style="display: flex; gap: 32px; align-items: center;">
        <a href="<?php echo htmlspecialchars($pagesBase . '/index.php'); ?>" style="text-decoration: none; color: #4b5563; font-weight: 700; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#d32f2f'" onmouseout="this.style.color='#4b5563'">HOME</a>
        <a href="<?php echo htmlspecialchars($pagesBase . '/about.php'); ?>" style="text-decoration: none; color: #4b5563; font-weight: 700; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#d32f2f'" onmouseout="this.style.color='#4b5563'">ABOUT</a>
        <a href="<?php echo htmlspecialchars($pagesBase . '/equipment.php'); ?>" style="text-decoration: none; color: #4b5563; font-weight: 700; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#d32f2f'" onmouseout="this.style.color='#4b5563'">EQUIPMENT</a>
        <a href="<?php echo htmlspecialchars($pagesBase . '/contact.php'); ?>" style="text-decoration: none; color: #4b5563; font-weight: 700; font-size: 14px; transition: color 0.2s;" onmouseover="this.style.color='#d32f2f'" onmouseout="this.style.color='#4b5563'">CONTACT</a>
        
        <?php if ($isLoggedIn): ?>
            <!-- Profile Button (when logged in) -->
            <a href="<?php echo htmlspecialchars($pagesBase . '/profile.php'); ?>" style="text-decoration: none; background-color: #fef2f2; color: #d32f2f; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: all 0.2s; display: flex; align-items: center; gap: 8px; border: 1px solid #fee2e2;" onmouseover="this.style.backgroundColor='#fee2e2'" onmouseout="this.style.backgroundColor='#fef2f2'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                PROFILE
            </a>
        <?php else: ?>
            <!-- Login Button (when not logged in) -->
            <a href="<?php echo htmlspecialchars($pagesBase . '/auth/signin.php'); ?>" style="text-decoration: none; background-color: #d32f2f; color: #ffffff; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: background-color 0.2s; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);" onmouseover="this.style.backgroundColor='#b71c1c'" onmouseout="this.style.backgroundColor='#d32f2f'">LOGIN</a>
        <?php endif; ?>
    </div>
</nav>

<script>
    (function () {
        var nav = document.getElementById('fes-nav');
        var toggle = document.getElementById('fes-nav-toggle');
        if (!nav || !toggle) return;

        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    })();
</script>
