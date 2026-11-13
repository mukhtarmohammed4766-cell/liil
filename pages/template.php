
<?php

require_once "../database/db.php";

$page = $_GET['page'] ?? 'Life Management';

$allowed = [
    'Tasks',
    'Goals',
    'Health',
    'Learning',
    'Finance',
    'Journal',
    'Calendar',
    'Settings'
];

if (!in_array($page, $allowed, true)) {
    $page = 'Life Management';
}

$name = "Salman";

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LifeOS - <?php echo htmlspecialchars($page); ?></title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>
.content-box {
    background:white;
    padding:30px;
    border-radius:18px;
    margin-top:25px;
    box-shadow:0 5px 25px #00000008;
}

.content-box h2 {
    margin-bottom:12px;
}

.content-box p {
    color:#64748b;
}

.page-icon {
    font-size:50px;
    margin-bottom:15px;
}
</style>

</head>

<body>

<div class="app">

<aside class="sidebar">

    <div class="logo">🌎 LifeOS</div>

    <div class="menu-title">WORKSPACE</div>

    <nav>

        <a href="../index.php" class="menu">⌂ Dashboard</a>

        <?php
        $pages = [
            'Tasks' => 'tasks',
            'Goals' => 'goals',
            'Health' => 'health',
            'Learning' => 'learning',
            'Finance' => 'finance',
            'Journal' => 'journal',
            'Calendar' => 'calendar'
        ];

        foreach ($pages as $label => $file):
        ?>

        <a href="<?php echo $file; ?>.php"
           class="menu <?php echo $page === $label ? 'active' : ''; ?>">
            <?php echo $label; ?>
        </a>

        <?php endforeach; ?>

        <a href="settings.php"
           class="menu <?php echo $page === 'Settings' ? 'active' : ''; ?>">
           ⚙ Settings
        </a>

    </nav>

</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h1><?php echo htmlspecialchars($page); ?></h1>
            <p>Manage your life with LifeOS.</p>
        </div>
    </header>

    <div class="content-box">

        <div class="page-icon">
            <?php
            $icons = [
                'Tasks'=>'📋',
                'Goals'=>'🎯',
                'Health'=>'❤️',
                'Learning'=>'📚',
                'Finance'=>'💰',
                'Journal'=>'📔',
                'Calendar'=>'📅',
                'Settings'=>'⚙️'
            ];

            echo $icons[$page] ?? '🌎';
            ?>
        </div>

        <h2>Welcome to <?php echo htmlspecialchars($page); ?>!</h2>

        <p>Your <?php echo htmlspecialchars($page); ?> workspace is ready for development.</p>

    </div>

</main>

</div>

</body>
</html>
