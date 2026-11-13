<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Mogadishu');

require_once __DIR__ . '/../database/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}

$user_id = (int)($_SESSION['user_id'] ?? 1);

function dashboardQuery($conn, $sql, $user_id)
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Dashboard SQL Error: " . $conn->error);
        die("Unable to load dashboard data.");
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        error_log("Dashboard Execute Error: " . $stmt->error);
        die("Unable to execute dashboard query.");
    }

    return $stmt;
}

function dashboardEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* USER */

$stmt = dashboardQuery(
    $conn,
    "SELECT name FROM users WHERE id = ? LIMIT 1",
    $user_id
);

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$name = $user['name'] ?? 'User';

$hour = (int)date("H");

if ($hour < 12) {
    $greetingKey = "greeting_morning";
} elseif ($hour < 18) {
    $greetingKey = "greeting_afternoon";
} else {
    $greetingKey = "greeting_evening";
}

/* TASKS */

$stmt = dashboardQuery(
    $conn,
    "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(LOWER(status) = 'completed'), 0) AS completed,
        COALESCE(SUM(LOWER(status) = 'pending'), 0) AS pending,
        COALESCE(SUM(LOWER(status) = 'in progress'), 0) AS in_progress,
        COALESCE(
            SUM(
                due_date IS NOT NULL
                AND due_date < CURDATE()
                AND LOWER(COALESCE(status, '')) != 'completed'
            ), 0
        ) AS overdue
    FROM tasks
    WHERE user_id = ?
    ",
    $user_id
);

$taskStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalTasks = (int)($taskStats['total'] ?? 0);
$completedTasks = (int)($taskStats['completed'] ?? 0);
$pendingTasks = (int)($taskStats['pending'] ?? 0);
$overdueTasks = (int)($taskStats['overdue'] ?? 0);

$taskPercentage = $totalTasks > 0
    ? round(($completedTasks / $totalTasks) * 100)
    : 0;

/* GOALS */

$stmt = dashboardQuery(
    $conn,
    "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(LOWER(status) = 'completed'), 0) AS completed,
        COALESCE(AVG(progress), 0) AS average_progress
    FROM goals
    WHERE userid = ?
    ",
    $user_id
);

$goalStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalGoals = (int)($goalStats['total'] ?? 0);
$completedGoals = (int)($goalStats['completed'] ?? 0);
$activeGoals = max(0, $totalGoals - $completedGoals);

$averageGoalProgress = round(
    (float)($goalStats['average_progress'] ?? 0)
);

/* HEALTH */

$stmt = dashboardQuery(
    $conn,
    "
    SELECT weight, height, sleep_hours, water_liters, exercise_minutes
    FROM health_logs
    WHERE userid = ?
      AND log_date = CURDATE()
    ORDER BY id DESC
    LIMIT 1
    ",
    $user_id
);

$health = $stmt->get_result()->fetch_assoc();
$stmt->close();

$weight = (float)($health['weight'] ?? 0);
$sleep = (float)($health['sleep_hours'] ?? 0);
$water = (float)($health['water_liters'] ?? 0);
$exercise = (int)($health['exercise_minutes'] ?? 0);

$sleepProgress = min(100, max(0, ($sleep / 8) * 100));
$waterProgress = min(100, max(0, ($water / 2.5) * 100));
$exerciseProgress = min(100, max(0, ($exercise / 30) * 100));

$healthPercentage = round(
    ($sleepProgress + $waterProgress + $exerciseProgress) / 3
);

/* FINANCE */

$stmt = dashboardQuery(
    $conn,
    "
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(type) = 'income' THEN amount
                    WHEN LOWER(type) = 'expense' THEN -amount
                    ELSE 0
                END
            ), 0
        ) AS balance
    FROM finance
    WHERE user_id = ?
    ",
    $user_id
);

$financeStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$balance = (float)($financeStats['balance'] ?? 0);

/* RECENT TASKS */

$stmt = dashboardQuery(
    $conn,
    "
    SELECT title, priority, status, due_date
    FROM tasks
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 5
    ",
    $user_id
);

$recentTasks = $stmt->get_result();
?>

<section class="welcome">
    <div class="welcome-content">

        <span class="welcome-label" data-i18n="personal_journey">
            YOUR PERSONAL JOURNEY
        </span>

        <h2>
            <span data-i18n="<?= $greetingKey ?>"></span>,
            <?= dashboardEscape($name) ?>!
        </h2>

        <p data-i18n="journey_description">
            Turn your dreams into daily actions.
        </p>

        <a href="#" class="primary-btn"
           onclick="loadPage('tasks'); return false;">
            <span data-i18n="continue_journey">Continue Journey</span>
            <span>→</span>
        </a>

    </div>

    <div class="welcome-quote">
        <span data-i18n="better_you">A better you,</span><br>
        <span data-i18n="every_day">every day.</span>
    </div>
</section>

<section class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon blue">✓</div>
        <div class="stat-info">
            <span data-i18n="tasks">Tasks</span>
            <h2><?= $totalTasks ?></h2>
            <small data-i18n="all_tasks">All your tasks</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">◎</div>
        <div class="stat-info">
            <span data-i18n="goals">Goals</span>
            <h2><?= $totalGoals ?></h2>
            <small>
                <?= $activeGoals ?>
                <span data-i18n="active_goals">active goals</span>
            </small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">♥️</div>
        <div class="stat-info">
            <span data-i18n="health">Health</span>
            <h2><?= $healthPercentage ?>%</h2>
            <small data-i18n="daily_wellness">Daily wellness</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">▣</div>
        <div class="stat-info">
            <span data-i18n="finance">Finance</span>
            <h2>$<?= number_format($balance, 2) ?></h2>
            <small data-i18n="current_balance">Current balance</small>
        </div>
    </div>

</section>

<section class="dashboard-grid">

    <div class="panel task-panel">

        <div class="panel-heading">
            <h3 data-i18n="task_overview">Task Overview</h3>

            <a href="#" onclick="loadPage('tasks'); return false;">
                <span data-i18n="view_all">View All</span> →
            </a>
        </div>

        <div class="task-overview">

            <div class="circle" style="--progress: <?= $taskPercentage ?>">
                <div>
                    <strong><?= $taskPercentage ?>%</strong>
                    <small data-i18n="completed">Completed</small>
                </div>
            </div>

            <div class="task-legend">

                <div>
                    <span class="dot completed-dot"></span>
                    <span data-i18n="completed">Completed</span>
                    <strong><?= $completedTasks ?></strong>
                </div>

                <div>
                    <span class="dot pending-dot"></span>
                    <span data-i18n="pending">Pending</span>
                    <strong><?= $pendingTasks ?></strong>
                </div>

                <div>
                    <span class="dot overdue-dot"></span>
                    <span data-i18n="overdue">Overdue</span>
                    <strong><?= $overdueTasks ?></strong>
                </div>

            </div>
        </div>
    </div>

    <div class="panel health-panel">

        <div class="panel-heading">
            <h3 data-i18n="health_overview">Health Overview</h3>
            <span class="health-icon">♡</span>
        </div>

        <div class="health-item">
            <div>
                <span data-i18n="sleep">Sleep</span>
                <strong><?= number_format($sleep, 1) ?> / 8h</strong>
            </div>
            <div class="health-bar purple-bar">
                <span style="width: <?= $sleepProgress ?>%"></span>
            </div>
        </div>

        <div class="health-item">
            <div>
                <span data-i18n="water">Water</span>
                <strong><?= number_format($water, 1) ?> / 2.5 L</strong>
            </div>
            <div class="health-bar cyan-bar">
                <span style="width: <?= $waterProgress ?>%"></span>
            </div>
        </div>

        <div class="health-item">
            <div>
                <span data-i18n="exercise">Exercise</span>
                <strong><?= $exercise ?> / 30 min</strong>
            </div>
            <div class="health-bar">
                <span style="width: <?= $exerciseProgress ?>%"></span>
            </div>
        </div>

        <div class="health-item">
            <div>
                <span data-i18n="weight">Weight</span>
                <strong>
                    <?= $weight > 0
                        ? number_format($weight, 1) . ' kg'
                        : '--'
                    ?>
                </strong>
            </div>
        </div>

    </div>

</section>

<section class="bottom-grid">

    <div class="panel recent-panel">

        <div class="panel-heading">
            <h3 data-i18n="recent_tasks">Recent Tasks</h3>

            <a href="#" onclick="loadPage('tasks'); return false;">
                <span data-i18n="view_all">View All</span> →
            </a>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th data-i18n="task">Task</th>
                        <th data-i18n="priority">Priority</th>
                        <th data-i18n="status">Status</th>
                        <th data-i18n="due_date">Due Date</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($recentTasks->num_rows > 0): ?>

                        <?php while ($task = $recentTasks->fetch_assoc()): ?>

                            <?php
                            $priorityClass = strtolower(
                                preg_replace(
                                    '/[^a-z0-9-]/',
                                    '',
                                    (string)$task['priority']
                                )
                            );

                            $statusClass = strtolower(
                                str_replace(' ', '-', (string)$task['status'])
                            );

                            $dueDate = !empty($task['due_date'])
                                ? date("M d, Y", strtotime($task['due_date']))
                                : null;
                            ?>

                            <tr>
                                <td><?= dashboardEscape($task['title']) ?></td>

                                <td>
                                    <span class="priority <?= dashboardEscape($priorityClass) ?>">
                                        <?= dashboardEscape($task['priority']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status <?= dashboardEscape($statusClass) ?>">
                                        <?= dashboardEscape($task['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($dueDate): ?>
                                        <?= dashboardEscape($dueDate) ?>
                                    <?php else: ?>
                                        <span data-i18n="no_date">No date</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4" class="empty"
                                data-i18n="no_tasks">
                                No tasks yet. Create your first task!
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="ai-panel">

        <div class="ai-robot">🤖</div>

        <div class="ai-content">

            <h3 data-i18n="ai_assistant">AI Life Assistant</h3>

            <p>
                <span data-i18n="doing_great">You're doing great!</span><br>
                <span data-i18n="keep_going">Keep going towards your goals.</span>
            </p>

            <a href="#" class="ai-button"
               onclick="loadPage('goals'); return false;">
                <span data-i18n="explore_goals">Explore Goals</span> →
            </a>

        </div>

    </div>

</section>

<footer>
    ©️ <?= date("Y") ?>
    <span data-i18n="footer_text">LifeOS. Build your best life.</span>
</footer>