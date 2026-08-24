<?php

/*
|--------------------------------------------------------------------------
| Sidebar Navigation (Management System Layout)
|--------------------------------------------------------------------------
|
| Renders the fixed left sidebar + opens the main content wrapper.
| The menu is role-aware: it detects the current section
| (admin / teacher / student) from the script path and shows the
| relevant menu. The current page is highlighted automatically.
|
*/

$currentScript = basename($_SERVER['PHP_SELF']);

$currentView = $_GET['view'] ?? '';

$root = '/school-funfair';

$isAdmin   = strpos($_SERVER['PHP_SELF'], $root . '/admin/')   !== false;
$isTeacher = strpos($_SERVER['PHP_SELF'], $root . '/teacher/') !== false;

if ($isAdmin) {

    $sectionTitle = 'Admin Panel';

    $menuItems = [
        ['href' => 'index.php',   'label' => 'Dashboard', 'icon' => 'bi-house-door'],
        ['href' => 'events.php',  'label' => 'Events',    'icon' => 'bi-calendar-event'],
        ['href' => 'tickets.php', 'label' => 'Tickets',   'icon' => 'bi-ticket-perforated'],
        ['href' => 'prizes.php',  'label' => 'Prizes',    'icon' => 'bi-trophy'],
        ['href' => 'draw.php',    'label' => 'Draw',      'icon' => 'bi-dice-5'],
        ['href' => 'winners.php', 'label' => 'Winners',   'icon' => 'bi-award'],
        ['href' => 'reports.php', 'label' => 'Reports',   'icon' => 'bi-graph-up-arrow'],
        ['href' => 'live.php',    'label' => 'Live TV',   'icon' => 'bi-broadcast-pin'],
    ];

} elseif ($isTeacher) {

    $sectionTitle = 'Teacher Portal';

    $menuItems = [
        ['href' => 'index.php',         'label' => 'Dashboard',     'icon' => 'bi-house-door'],
        ['href' => 'registrations.php', 'label' => 'Registrations', 'icon' => 'bi-clipboard-check'],
        ['href' => 'tickets.php',       'label' => 'My Tickets',    'icon' => 'bi-ticket-perforated'],
    ];

} else {

    $sectionTitle = 'Student Portal';

    $menuItems = [
        [
            'href'   => 'index.php',
            'label'  => 'FunFair Home',
            'icon'   => 'bi-mortarboard',
            'active' => $currentScript === 'index.php' && $currentView === '',
        ],
        [
            'href'   => 'index.php?view=events',
            'label'  => 'Available Events',
            'icon'   => 'bi-calendar-event',
            'active' => $currentScript === 'index.php' && $currentView === 'events',
        ],
        [
            'href'   => 'index.php?view=registrations',
            'label'  => 'My Registrations',
            'icon'   => 'bi-clipboard-check',
            'active' => $currentScript === 'index.php' && $currentView === 'registrations',
        ],
        [
            'href'   => 'tickets.php',
            'label'  => 'My Tickets',
            'icon'   => 'bi-ticket-perforated',
            'active' => $currentScript === 'tickets.php',
        ],
    ];

}

?>

<div class="app-wrapper">

    <aside
        class="app-sidebar offcanvas-lg offcanvas-start"
        tabindex="-1"
        id="appSidebar"
        aria-label="Sidebar navigation"
    >

        <div class="app-sidebar-head">

            <a class="app-brand" href="<?= $menuItems[0]['href'] ?>">

                <span class="app-brand-icon">
                    <i class="bi bi-ferris-wheel"></i>
                </span>

                <span class="app-brand-text">
                    FunFair
                    <small>Lucky Draw System</small>
                </span>

            </a>

            <button
                type="button"
                class="btn-close btn-close-white d-lg-none"
                data-bs-dismiss="offcanvas"
                aria-label="Close"
            ></button>

        </div>

        <div class="app-sidebar-body">

            <div class="app-sidebar-section">
                <?= htmlspecialchars($sectionTitle) ?>
            </div>

            <ul class="nav flex-column">

                <?php foreach ($menuItems as $item): ?>

                    <?php

                    $isActive = $item['active']
                        ?? ($currentScript === basename($item['href']));

                    ?>

                    <li class="nav-item">

                        <a
                            class="app-nav-link <?= $isActive ? 'active' : '' ?>"
                            href="<?= $item['href'] ?>"
                        >
                            <span class="app-nav-icon">
                                <i class="bi <?= $item['icon'] ?>"></i>
                            </span>
                            <?= $item['label'] ?>
                        </a>

                    </li>

                <?php endforeach; ?>

            </ul>

            <div class="app-sidebar-divider"></div>

            <div class="app-sidebar-section">Switch Portal</div>

            <ul class="nav flex-column mb-3">

                <?php if (!$isTeacher): ?>

                    <li class="nav-item">
                        <a class="app-nav-link" href="<?= $root ?>/teacher/index.php">
                            <span class="app-nav-icon"><i class="bi bi-person-badge"></i></span>
                            Teacher Portal
                        </a>
                    </li>

                <?php endif; ?>

                <?php if (!$isAdmin): ?>

                    <li class="nav-item">
                        <a class="app-nav-link" href="<?= $root ?>/admin/index.php">
                            <span class="app-nav-icon"><i class="bi bi-shield-lock"></i></span>
                            Admin Panel
                        </a>
                    </li>

                <?php endif; ?>

                <?php if (!$isTeacher && !$isAdmin): ?>

                    <li class="nav-item">
                        <a class="app-nav-link" href="<?= $root ?>/student/index.php">
                            <span class="app-nav-icon"><i class="bi bi-mortarboard"></i></span>
                            Student Portal
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </aside>

    <main class="app-main">

        <nav class="app-topbar d-lg-none">

            <button
                class="btn btn-sm app-topbar-btn"
                type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#appSidebar"
                aria-controls="appSidebar"
            >
                <i class="bi bi-list"></i> Menu
            </button>

            <span class="fw-bold text-white">
                <i class="bi bi-ferris-wheel me-1"></i> FunFair
            </span>

        </nav>

        <div class="app-content">
