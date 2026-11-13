<?php
session_start();

require_once __DIR__ . "/database/db.php";

$userId = (int)($_SESSION['user_id'] ?? 1);

// Load user settings
$stmt = $conn->prepare(
    "SELECT language, theme, week_start, notifications, compact_mode
     FROM user_settings
     WHERE userid = ?
     LIMIT 1"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$userSettings = $stmt->get_result()->fetch_assoc() ?: [
    'language' => 'English',
    'theme' => 'dark',
    'week_start' => 'Monday',
    'notifications' => 1,
    'compact_mode' => 0
];

$stmt->close();

$isArabic = $userSettings['language'] === 'Arabic';
$currentTheme = $userSettings['theme'];
?>

<!DOCTYPE html>
<html lang="<?= $isArabic ? 'ar' : 'en' ?>"
      dir="<?= $isArabic ? 'rtl' : 'ltr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>LifeOS Dashboard</title>

    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/style.css">

    <!-- Pages CSS -->
    <link rel="stylesheet" href="assets/css/goals.css">
    <link rel="stylesheet" href="assets/css/health.css">
    <link rel="stylesheet" href="assets/css/learning.css">
    <link rel="stylesheet" href="assets/css/finance.css">
    <link rel="stylesheet" href="assets/css/calendar.css">
    <link rel="stylesheet" href="assets/css/setting.css">

    <!-- JavaScript -->
    <script src="assets/js/tasks.js" defer></script>
    <script src="assets/js/goals.js" defer></script>
    <script src="assets/js/health.js" defer></script>
    <script src="assets/js/learning.js" defer></script>
    <script src="assets/js/finance.js" defer></script>
    <script src="assets/js/journal.js" defer></script>
    <script src="assets/js/calendar.js" defer></script>
    <script src="assets/js/setting.js" defer></script>
    <script src="assets/js/translation.js" defer></script>
    <script src="assets/js/notifications.js" defer></script>
</head>

<body
    class="<?= $currentTheme === 'light' ? 'light-theme' : 'dark-theme' ?>"
    data-language="<?= $isArabic ? 'ar' : 'en' ?>"
    data-week-start="<?= htmlspecialchars($userSettings['week_start']) ?>"
    data-compact="<?= (int)$userSettings['compact_mode'] ?>"
>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

        <div class="logo">
            <div class="logo-icon">🌍</div>
            <span>LifeOS</span>
        </div>

        <div class="menu-title">MAIN MENU</div>

        <nav>
            <a href="#" class="menu active" data-page="dashboard"
               onclick="loadPage('dashboard', this); return false;">
                <span>⌂</span>
                <span data-i18n="dashboard">Dashboard</span>
            </a>

            <a href="#" class="menu" data-page="tasks"
               onclick="loadPage('tasks', this); return false;">
                <span>☑</span>
                <span data-i18n="tasks">Tasks</span>
            </a>

            <a href="#" class="menu" data-page="goals"
               onclick="loadPage('goals', this); return false;">
                <span>◎</span>
                <span data-i18n="goals">Goals</span>
            </a>

            <a href="#" class="menu" data-page="health"
               onclick="loadPage('health', this); return false;">
                <span>♥️</span>
                <span data-i18n="health">Health</span>
            </a>

            <a href="#" class="menu" data-page="learning"
               onclick="loadPage('learning', this); return false;">
                <span>🎓</span>
                <span data-i18n="learning">Learning</span>
            </a>

            <a href="#" class="menu" data-page="finance"
               onclick="loadPage('finance', this); return false;">
                <span>▣</span>
                <span data-i18n="finance">Finance</span>
            </a>

            <a href="#" class="menu" data-page="journal"
               onclick="loadPage('journal', this); return false;">
                <span>📖</span>
                <span data-i18n="journal">Journal</span>
            </a>

            <a href="#" class="menu" data-page="calendar"
               onclick="loadPage('calendar', this); return false;">
                <span>▦</span>
                <span data-i18n="calendar">Calendar</span>
            </a>

            <a href="#" class="menu" data-page="settings"
               onclick="loadPage('settings', this); return false;">
                <span>⚙</span>
                <span data-i18n="settings">Settings</span>
            </a>
        </nav>

        <div class="sidebar-bottom">

            <div class="user-card">
                <div class="avatar">JD</div>
                <div>
                    <strong>LifeOS User</strong>
                    <small>Personal Workspace</small>
                </div>
            </div>

            <a href="logout.php" class="logout-link">
                <span>↪</span> Logout
            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <header class="topbar">

            <button class="mobile-menu" onclick="toggleSidebar()">☰</button>

            <div class="search-box">
                <span>⌕</span>
                <input type="search" placeholder="Search anything...">
            </div>

            <div class="top-actions">

                <button class="notification" title="Notifications">
                    ♧
                    <span class="notification-count">2</span>
                </button>

                <div class="date">
                    📅 <span id="currentDate"></span>
                </div>

            </div>

        </header>

        <!-- PAGE CONTENT -->
        <div id="content">
            <?php include __DIR__ . "/pages/dashboard.php"; ?>
        </div>

    </main>

</div>

<script>
function loadPage(page, element) {

    const allowedPages = [
        "dashboard",
        "tasks",
        "goals",
        "health",
        "learning",
        "finance",
        "journal",
        "calendar",
        "settings"
    ];

    if (!allowedPages.includes(page)) {
        return;
    }

    document.querySelectorAll(".menu").forEach(link => {
        link.classList.remove("active");
    });

    if (element) {
        element.classList.add("active");
    }

    const content = document.getElementById("content");

    content.innerHTML = `
        <div class="loading-panel">
            <div class="loader"></div>
            <p>Loading your page...</p>
        </div>
    `;
fetch("pages/" + page + ".php")
    .then(response => {
        if (!response.ok) {
            throw new Error("Page could not be found");
        }

        return response.text();
    })
    .then(html => {
        content.innerHTML = html;

        if (window.applyLifeOSTranslations) {
            window.applyLifeOSTranslations(content);
        }
    })
    .catch(error => {
        content.innerHTML = `
            <div class="panel error-panel">
                <h2 data-i18n="something_wrong">Something went wrong</h2>
                <p data-i18n="could_not_load">
                    Could not load this page. Check the PHP file.
                </p>
            </div>
        `;

        if (window.applyLifeOSTranslations) {
            window.applyLifeOSTranslations(content);
        }

        console.error(error);
    });

    document.getElementById("sidebar").classList.remove("show");
}

function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("show");
}

// Current date
window.updateCurrentDate = function () {
    const language = document.documentElement.lang === "ar" ? "ar" : "en";

    const dateElement = document.getElementById("currentDate");
    if (!dateElement) return;

    dateElement.textContent = new Date().toLocaleDateString(language, {
        weekday: "short",
        month: "short",
        day: "numeric",
        year: "numeric"
    });
};

window.updateCurrentDate();
</script>

</body>
</html>