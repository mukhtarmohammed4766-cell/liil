


<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/db.php';

$userId = (int)($_SESSION['user_id'] ?? 1);
date_default_timezone_set('Africa/Mogadishu');

function calendarEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start = $_POST['start_datetime'] ?? '';
        $end = $_POST['end_datetime'] ?? '';

        if ($title === '' || $start === '') {
            $message = 'Please enter an event title and start date.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO calendar_events
                (userid, title, description, start_datetime, end_datetime)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "issss",
                $userId,
                $title,
                $description,
                $start,
                $end
            );

            if ($stmt->execute()) {
                $message = 'Event added successfully!';
                $messageType = 'success';
            } else {
                $message = 'Could not add event.';
                $messageType = 'error';
            }

            $stmt->close();
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start = $_POST['start_datetime'] ?? '';
        $end = $_POST['end_datetime'] ?? '';

        if ($id > 0 && $title !== '' && $start !== '') {
            $stmt = $conn->prepare(
                "UPDATE calendar_events
                 SET title = ?, description = ?,
                     start_datetime = ?, end_datetime = ?
                 WHERE id = ? AND userid = ?"
            );

            $stmt->bind_param(
                "ssssii",
                $title,
                $description,
                $start,
                $end,
                $id,
                $userId
            );

            $stmt->execute();
            $stmt->close();

            $message = 'Event updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Please complete the required fields.';
            $messageType = 'error';
        }
    }
$start = str_replace('T', ' ', $start);
if (strlen($start) === 16) {
    $start .= ':00';
}

$end = !empty($end) ? str_replace('T', ' ', $end) : null;

if ($end !== null && strlen($end) === 16) {
    $end .= ':00';
}
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM calendar_events WHERE id = ? AND userid = ?"
        );
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        $stmt->close();

        $message = 'Event deleted successfully!';
        $messageType = 'success';
    }
}

$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$monthStart = DateTime::createFromFormat('!Y-m-d', $month . '-01');

if (!$monthStart || $monthStart->format('Y-m') !== $month) {
    $monthStart = new DateTime('first day of this month');
    $month = $monthStart->format('Y-m');
}

$monthEnd = clone $monthStart;
$monthEnd->modify('last day of this month');

$previousMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');

$firstWeekday = (int)$monthStart->format('N');
$daysInMonth = (int)$monthStart->format('t');

$monthLabel = $monthStart->format('F Y');

$stmt = $conn->prepare(
    "SELECT id, title, description, start_datetime, end_datetime
     FROM calendar_events
     WHERE userid = ?
       AND start_datetime >= ?
       AND start_datetime < ?
     ORDER BY start_datetime ASC"
);

$rangeStart = $monthStart->format('Y-m-d 00:00:00');
$rangeEnd = (clone $monthStart)->modify('+1 month')->format('Y-m-d 00:00:00');

$stmt->bind_param("iss", $userId, $rangeStart, $rangeEnd);
$stmt->execute();

$result = $stmt->get_result();
$events = [];

while ($row = $result->fetch_assoc()) {
    $day = (int)date('j', strtotime($row['start_datetime']));
    $events[$day][] = $row;
}

$stmt->close();

$totalEvents = array_sum(array_map('count', $events));
$today = date('Y-m-d');
?>

<div class="calendar-page">

    <div class="calendar-header">
        <div>
            <span class="calendar-label">PLAN YOUR LIFE</span>
            <h1>My Calendar</h1>
            <p>Organize your days, events and important moments.</p>
        </div>
        <div class="calendar-header-icon">📅</div>
    </div>

    <?php if ($message): ?>
        <div class="calendar-message <?= $messageType ?>">
            <?= calendarEscape($message) ?>
        </div>
    <?php endif; ?>

    <div class="calendar-stats">
        <div class="calendar-stat-card">
            <span class="calendar-stat-icon">📌</span>
            <div>
                <small>Events This Month</small>
                <h2><?= $totalEvents ?></h2>
            </div>
        </div>

        <div class="calendar-stat-card">
            <span class="calendar-stat-icon">🗓️</span>
            <div>
                <small>Current Month</small>
                <h2><?= calendarEscape($monthStart->format('M')) ?></h2>
            </div>
        </div>
    </div>

    <section class="calendar-main">

        <div class="calendar-month-bar">
            <h2><?= calendarEscape($monthLabel) ?></h2>

            <div class="calendar-navigation">
                <button type="button" data-calendar-month="<?= $previousMonth ?>">‹</button>
                <button type="button" data-calendar-month="<?= date('Y-m') ?>">Today</button>
                <button type="button" data-calendar-month="<?= $nextMonth ?>">›</button>
            </div>
        </div>

        <div class="calendar-grid">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName): ?>
                <div class="calendar-weekday"><?= $dayName ?></div>
            <?php endforeach; ?>

            <?php for ($i = 1; $i < $firstWeekday; $i++): ?>
                <div class="calendar-day empty-day"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                    $dateString = $monthStart->format('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $isToday = $dateString === $today;
                ?>

                <div class="calendar-day <?= $isToday ? 'today' : '' ?>">
                    <span class="calendar-day-number"><?= $day ?></span>

                    <?php if (!empty($events[$day])): ?>
                        <?php foreach (array_slice($events[$day], 0, 2) as $event): ?>
                            <div class="calendar-event"
                                 title="<?= calendarEscape($event['title']) ?>">
                                <?= calendarEscape($event['title']) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($events[$day]) > 2): ?>
                            <small class="calendar-more">
                                +<?= count($events[$day]) - 2 ?> more
                            </small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="calendar-form-card">
        <div class="calendar-section-heading">
            <div>
                <h2>➕ Add New Event</h2>
                <p>Save an important date or appointment.</p>
            </div>
        </div>

        <form method="POST" data-calendar-form>
            <input type="hidden" name="action" value="add">

            <div class="calendar-form-grid">
                <div class="calendar-input-group">
                    <label>Event Title *</label>
                    <input type="text" name="title" placeholder="e.g. Study session" required>
                </div>

                <div class="calendar-input-group">
                    <label>Start Date & Time *</label>
                    <input type="datetime-local" name="start_datetime" required>
                </div>

                <div class="calendar-input-group">
                    <label>End Date & Time</label>
                    <input type="datetime-local" name="end_datetime">
                </div>

                <div class="calendar-input-group">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Optional details">
                </div>
            </div>

            <button type="submit" class="calendar-save-btn">Save Event</button>
        </form>
    </section>

    <section class="calendar-events-section">
        <div class="calendar-section-heading">
            <div>
                <h2>📍 This Month's Events</h2>
                <p>Manage your scheduled events.</p>
            </div>
        </div>

        <?php if ($totalEvents > 0): ?>
            <div class="calendar-event-list">

                <?php foreach ($events as $dayEvents): ?>
                    <?php foreach ($dayEvents as $event): ?>
                        <div class="calendar-event-card">
                            <div class="calendar-event-info">
                                <span class="calendar-event-date">
                                    <?= date('M d, Y · h:i A', strtotime($event['start_datetime'])) ?>
                                </span>

                                <h3><?= calendarEscape($event['title']) ?></h3>

                                <?php if (!empty($event['description'])): ?>
                                    <p><?= calendarEscape($event['description']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($event['end_datetime'])): ?>
                                    <small>
                                        Ends: <?= date('M d, h:i A', strtotime($event['end_datetime'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>

                            <div class="calendar-event-actions">
                                <details class="calendar-edit">
                                    <summary>Edit</summary>

                                    <form method="POST" data-calendar-form>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">

                                        <input type="text" name="title"
                                               value="<?= calendarEscape($event['title']) ?>" required>

                                        <input type="datetime-local" name="start_datetime"
                                               value="<?= date('Y-m-d\TH:i', strtotime($event['start_datetime'])) ?>" required>

                                        <input type="datetime-local" name="end_datetime"
                                               value="<?= !empty($event['end_datetime']) ? date('Y-m-d\TH:i', strtotime($event['end_datetime'])) : '' ?>">

                                        <input type="text" name="description"
                                               value="<?= calendarEscape($event['description']) ?>"
                                               placeholder="Description">

                                        <button type="submit">Update</button>
                                    </form>
                                </details>

                                <form method="POST" data-calendar-form
                                      onsubmit="return confirm('Delete this event?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                                    <button type="submit" class="calendar-delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            <div class="calendar-empty">
                <span>📅</span>
                <h3>No events this month</h3>
                <p>Add your first event to start organizing your schedule.</p>
            </div>
        <?php endif; ?>
    </section>

</div>

<style>.calendar-page {
    color: #eaf3ff;
    padding: 8px 3px 20px;
}

.calendar-header {
    background:
        radial-gradient(circle at top right, #1676d955, transparent 45%),
        linear-gradient(120deg, #06172f, #103b70, #08234b);
    border: 1px solid #2366ae;
    border-radius: 17px;
    padding: 22px 25px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 8px 22px #00152e66;
}

.calendar-label {
    color: #55c7ff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
}

.calendar-header h1 {
    color: white;
    font-size: 26px;
    margin: 7px 0;
}

.calendar-header p {
    color: #c4d9f7;
    font-size: 13px;
    margin: 0;
}

.calendar-header-icon {
    font-size: 34px;
    background: #ffffff12;
    padding: 12px;
    border-radius: 14px;
}

.calendar-message {
    padding: 11px 15px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.calendar-message.success {
    background: #123e35;
    color: #7cf5bd;
}

.calendar-message.error {
    background: #481f2b;
    color: #ff9caa;
}

.calendar-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 13px;
    margin-bottom: 18px;
}

.calendar-stat-card,
.calendar-main,
.calendar-form-card,
.calendar-events-section {
    background: linear-gradient(135deg, #102e59, #071a38);
    border: 1px solid #24558e;
    border-radius: 15px;
    box-shadow: 0 6px 18px #04132b55;
}

.calendar-stat-card {
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.calendar-stat-icon {
    font-size: 24px;
    background: #55c7ff18;
    padding: 10px;
    border-radius: 11px;
}

.calendar-stat-card small {
    color: #a9c5e9;
}

.calendar-stat-card h2 {
    margin: 4px 0 0;
    color: #55d8ff;
    font-size: 22px;
}

.calendar-main,
.calendar-form-card,
.calendar-events-section {
    padding: 20px;
    margin-bottom: 18px;
}

.calendar-month-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.calendar-month-bar h2,
.calendar-section-heading h2 {
    margin: 0;
    font-size: 19px;
    color: white;
}

.calendar-navigation {
    display: flex;
    gap: 7px;
}

.calendar-navigation button {
    background: #0b2850;
    color: #55d8ff;
    border: 1px solid #285486;
    border-radius: 8px;
    padding: 7px 12px;
    cursor: pointer;
}

.calendar-navigation button:hover {
    background: #174b84;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 7px;
}

.calendar-weekday {
    text-align: center;
    color: #55c7ff;
    font-size: 12px;
    font-weight: 700;
    padding: 8px 2px;
}

.calendar-day {
    min-height: 85px;
    min-width: 0;
    background: #071b38;
    border: 1px solid #285486;
    border-radius: 9px;
    padding: 8px;
}

.calendar-day.today {
    border: 2px solid #38bdf8;
    background: #10345e;
}

.calendar-day-number {
    display: block;
    color: #eaf3ff;
    font-size: 13px;
    margin-bottom: 6px;
}

.empty-day {
    background: transparent;
    border-color: transparent;
}

.calendar-event {
    background: #0875a8;
    color: white;
    border-radius: 4px;
    padding: 3px 5px;
    margin-top: 4px;
    font-size: 10px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.calendar-more {
    color: #a9c5e9;
    font-size: 10px;
}

.calendar-section-heading {
    margin-bottom: 16px;
}

.calendar-section-heading p {
    color: #a9c5e9;
    font-size: 12px;
    margin: 6px 0 0;
}

.calendar-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.calendar-input-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.calendar-input-group label {
    color: #55c7ff;
    font-size: 12px;
    font-weight: 600;
}

.calendar-input-group input,
.calendar-edit input {
    width: 100%;
    box-sizing: border-box;
    background: #071b38;
    color: white;
    border: 1px solid #285486;
    border-radius: 8px;
    padding: 10px 11px;
    font-size: 12px;
    outline: none;
}

.calendar-input-group input:focus,
.calendar-edit input:focus {
    border-color: #38bdf8;
}

.calendar-save-btn,
.calendar-edit button {
    margin-top: 14px;
    background: linear-gradient(90deg, #38bdf8, #a5f3fc);
    color: #06224a;
    border: 1px solid #8de8ff;
    border-radius: 8px;
    padding: 10px 19px;
    font-weight: 700;
    cursor: pointer;
}

.calendar-event-list {
    display: grid;
    gap: 11px;
}

.calendar-event-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    background: #071b38;
    border: 1px solid #285486;
    border-radius: 11px;
    padding: 15px;
}

.calendar-event-date {
    color: #55d8ff;
    font-size: 11px;
}

.calendar-event-info h3 {
    margin: 6px 0;
    font-size: 15px;
}

.calendar-event-info p,
.calendar-event-info small {
    color: #a9c5e9;
    font-size: 12px;
    margin: 5px 0;
}

.calendar-event-actions {
    display: flex;
    align-items: center;
    gap: 9px;
}

.calendar-edit summary {
    color: #55d8ff;
    cursor: pointer;
    font-size: 12px;
}

.calendar-edit form {
    display: grid;
    gap: 8px;
    min-width: 210px;
    margin-top: 10px;
}

.calendar-edit button {
    margin-top: 0;
}

.calendar-delete-btn {
    background: #552536;
    color: #ffb4c0;
    border: 1px solid #a64b65;
    border-radius: 7px;
    padding: 8px 11px;
    cursor: pointer;
}

.calendar-empty {
    text-align: center;
    padding: 30px 12px;
    color: #a9c5e9;
}

.calendar-empty span {
    font-size: 35px;
}

.calendar-empty h3 {
    color: white;
}

@media (max-width: 700px) {
    .calendar-stats,
    .calendar-form-grid {
        grid-template-columns: 1fr;
    }

    .calendar-main,
    .calendar-form-card,
    .calendar-events-section {
        padding: 13px;
    }

    .calendar-grid {
        gap: 3px;
    }

    .calendar-day {
        min-height: 65px;
        padding: 4px;
    }

    .calendar-weekday {
        font-size: 10px;
    }

    .calendar-event {
        font-size: 8px;
        padding: 3px;
    }

    .calendar-month-bar {
        align-items: flex-start;
        flex-direction: column;
    }

    .calendar-event-card {
        align-items: flex-start;
        flex-direction: column;
    }

    .calendar-event-actions {
        flex-wrap: wrap;
    }
}</style>