<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/db.php';

$userId = (int)($_SESSION['user_id'] ?? 1);

date_default_timezone_set('Africa/Mogadishu');

function journalEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';

$allowedMoods = [
    'Happy', 'Good', 'Calm', 'Excited',
    'Sad', 'Stressed', 'Tired', 'Grateful'
];

// Add or delete journal entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $mood = $_POST['mood'] ?? 'Good';
        $entryDate = $_POST['entry_date'] ?? '';

        $validDate = DateTime::createFromFormat('Y-m-d', $entryDate);

        if (
            $title === '' ||
            $content === '' ||
            mb_strlen($title) > 180 ||
            !in_array($mood, $allowedMoods, true) ||
            !$validDate ||
            $validDate->format('Y-m-d') !== $entryDate
        ) {
            $message = 'Please fill in all required fields correctly.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO journal
                (userid, title, content, mood, entry_date)
                VALUES (?, ?, ?, ?, ?)"
            );

            if ($stmt) {
                $stmt->bind_param(
                    "issss",
                    $userId,
                    $title,
                    $content,
                    $mood,
                    $entryDate
                );

                if ($stmt->execute()) {
                    $message = 'Your journal entry has been saved!';
                    $messageType = 'success';
                } else {
                    $message = 'Could not save your journal entry.';
                    $messageType = 'error';
                }

                $stmt->close();
            } else {
                $message = 'Database error while preparing your entry.';
                $messageType = 'error';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM journal WHERE id = ? AND userid = ?"
        );

        if ($stmt) {
            $stmt->bind_param("ii", $id, $userId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'Journal entry deleted.';
                $messageType = 'success';
            } else {
                $message = 'Journal entry was not found.';
                $messageType = 'error';
            }

            $stmt->close();
        }
    }
}

// Statistics
$stats = [
    'total' => 0,
    'today' => 0,
    'happy' => 0
];

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(entry_date = CURDATE()), 0) AS today,
        COALESCE(SUM(mood IN ('Happy', 'Good', 'Excited', 'Grateful')), 0) AS happy
     FROM journal
     WHERE userid = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = array_merge($stats, $result->fetch_assoc() ?: []);
    $stmt->close();
}

// Fetch entries
$entries = [];

$stmt = $conn->prepare(
    "SELECT id, title, content, mood, entry_date, created_at
     FROM journal
     WHERE userid = ?
     ORDER BY entry_date DESC, id DESC"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }

    $stmt->close();
}

$moodIcons = [
    'Happy' => '😊',
    'Good' => '🙂',
    'Calm' => '😌',
    'Excited' => '🤩',
    'Sad' => '😔',
    'Stressed' => '😣',
    'Tired' => '😴',
    'Grateful' => '🙏'
];
?>

<div class="journal-page">

    <div class="journal-header">
        <div>
            <span class="journal-label">YOUR PERSONAL SPACE</span>
            <h1>My Journal</h1>
            <p>A place for your thoughts, memories, feelings, and daily reflections.</p>
        </div>
        <div class="journal-header-icon">📖</div>
    </div>

    <?php if ($message): ?>
        <div class="journal-message <?= journalEscape($messageType) ?>">
            <?= journalEscape($message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="journal-stats">

        <div class="journal-stat-card">
            <div class="journal-stat-icon blue">📚</div>
            <div>
                <span>Total Entries</span>
                <h2><?= (int)$stats['total'] ?></h2>
                <small>Your saved memories</small>
            </div>
        </div>

        <div class="journal-stat-card">
            <div class="journal-stat-icon green">✍️</div>
            <div>
                <span>Today's Entries</span>
                <h2><?= (int)$stats['today'] ?></h2>
                <small>Written today</small>
            </div>
        </div>

        <div class="journal-stat-card">
            <div class="journal-stat-icon purple">😊</div>
            <div>
                <span>Positive Moods</span>
                <h2><?= (int)$stats['happy'] ?></h2>
                <small>Happy, good and grateful entries</small>
            </div>
        </div>

    </div>

    <!-- New Journal Entry -->
    <section class="journal-form-card">

        <div class="journal-section-heading">
            <div>
                <span class="journal-label">WRITE YOUR HEART OUT</span>
                <h2>Create a New Entry</h2>
                <p>Take a moment to reflect on your day.</p>
            </div>
            <span class="journal-heading-icon">✎</span>
        </div>

        <form method="POST" data-journal-form>
            <input type="hidden" name="action" value="add">

            <div class="journal-form-grid">

                <div class="journal-input-group">
                    <label>Entry Title</label>
                    <input
                        type="text"
                        name="title"
                        maxlength="180"
                        placeholder="Give your day a title..."
                        required
                    >
                </div>

                <div class="journal-input-group">
                    <label>How are you feeling?</label>
                    <select name="mood" required>
                        <?php foreach ($moodIcons as $mood => $emoji): ?>
                            <option value="<?= journalEscape($mood) ?>">
                                <?= $emoji ?> <?= journalEscape($mood) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="journal-input-group journal-input-wide">
                    <label>What's on your mind?</label>
                    <textarea
                        name="content"
                        rows="7"
                        placeholder="Write about your day, your thoughts, your goals, or anything you want to remember..."
                        required
                    ></textarea>
                </div>

                <div class="journal-input-group">
                    <label>Entry Date</label>
                    <input
                        type="date"
                        name="entry_date"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

            </div>

            <button type="submit" class="journal-save-btn">
                ✍️ Save My Entry
            </button>
        </form>

    </section>

    <!-- Journal History -->
    <section class="journal-history">

        <div class="journal-section-heading">
            <div>
                <span class="journal-label">YOUR MEMORIES</span>
                <h2>Previous Entries</h2>
                <p>Look back at your thoughts and experiences.</p>
            </div>

            <span class="journal-record-count">
                <?= count($entries) ?> Entries
            </span>
        </div>

        <?php if (empty($entries)): ?>

            <div class="journal-empty">
                <div>📔</div>
                <h3>Your journal is waiting</h3>
                <p>Write your first entry and start collecting your memories.</p>
            </div>

        <?php else: ?>

            <div class="journal-entry-list">

                <?php foreach ($entries as $entry): ?>
                    <article class="journal-entry-card">

                        <div class="journal-entry-top">
                            <div>
                                <span class="journal-entry-date">
                                    <?= date(
                                        'F d, Y',
                                        strtotime($entry['entry_date'])
                                    ) ?>
                                </span>

                                <h3><?= journalEscape($entry['title']) ?></h3>
                            </div>

                            <span class="journal-mood">
                                <?= $moodIcons[$entry['mood']] ?? '🙂' ?>
                                <?= journalEscape($entry['mood']) ?>
                            </span>
                        </div>

                        <p class="journal-entry-content"><?= journalEscape($entry['content']) ?></p>

                        <div class="journal-entry-footer">
                            <span>📅 <?= date(
                                'M d, Y',
                                strtotime($entry['entry_date'])
                            ) ?></span>

                            <form method="POST" data-journal-form
                                  onsubmit="return confirm('Delete this journal entry?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"
                                       value="<?= (int)$entry['id'] ?>">

                                <button type="submit" class="journal-delete-btn">
                                    Delete Entry
                                </button>
                            </form>
                        </div>

                    </article>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>
<style>.journal-page {
    color: #eaf3ff;
    padding: 10px 2px 30px;
}

.journal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 28px;
    margin-bottom: 24px;
    border: 1px solid #2366ae;
    border-radius: 20px;
    background:
        radial-gradient(circle at top right, #1676d955, transparent 40%),
        linear-gradient(120deg, #06172f, #103b70, #08234b);
    box-shadow: 0 12px 30px #00152e66;
}

.journal-label {
    display: block;
    margin-bottom: 8px;
    color: #55c7ff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
}

.journal-header h1 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 30px;
}

.journal-header p,
.journal-section-heading p {
    margin: 0;
    color: #c4d9f7;
    font-size: 14px;
}

.journal-header-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 70px;
    height: 70px;
    border: 1px solid #55c7ff66;
    border-radius: 20px;
    background: #0b3a6c;
    font-size: 34px;
}

.journal-message {
    padding: 13px 17px;
    margin-bottom: 20px;
    border-radius: 12px;
    font-size: 14px;
}

.journal-message.success {
    color: #7cf5bd;
    border: 1px solid #278c69;
    background: #103d36;
}

.journal-message.error {
    color: #ff9caa;
    border: 1px solid #a7475a;
    background: #451e32;
}

/* Statistics */

.journal-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.journal-stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border: 1px solid #24558e;
    border-radius: 17px;
    background: linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 8px 20px #04132b55;
}

.journal-stat-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    font-size: 22px;
}

.journal-stat-icon.blue {
    color: #55d8ff;
    background: #124b76;
}

.journal-stat-icon.green {
    color: #7cf5bd;
    background: #145b4a;
}

.journal-stat-icon.purple {
    color: #c4b5fd;
    background: #42366f;
}

.journal-stat-card span {
    display: block;
    margin-bottom: 7px;
    color: #a9c5e9;
    font-size: 12px;
}

.journal-stat-card h2 {
    margin: 0 0 5px;
    color: #fff;
    font-size: 23px;
}

.journal-stat-card small {
    color: #91afd3;
    font-size: 11px;
}

/* Form */

.journal-form-card,
.journal-history {
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid #24558e;
    border-radius: 19px;
    background: linear-gradient(145deg, #0c2850, #061832);
    box-shadow: 0 10px 25px #04132b55;
}

.journal-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 22px;
}

.journal-section-heading h2 {
    margin: 0 0 7px;
    color: #fff;
    font-size: 21px;
}

.journal-heading-icon {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    border-radius: 13px;
    background: #124b76;
    color: #55d8ff;
    font-size: 25px;
}

.journal-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.journal-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.journal-input-wide {
    grid-column: 1 / -1;
}

.journal-input-group label {
    color: #c4d9f7;
    font-size: 13px;
    font-weight: 700;
}

.journal-input-group input,
.journal-input-group select,
.journal-input-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #285486;
    border-radius: 10px;
    outline: none;
    background: #071b38;
    color: #eaf3ff;
    font: inherit;
    font-size: 13px;
    transition: border-color .2s, box-shadow .2s;
}

.journal-input-group input:focus,
.journal-input-group select:focus,
.journal-input-group textarea:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px #38bdf822;
}

.journal-input-group textarea {
    resize: vertical;
    line-height: 1.7;
}

.journal-input-group input::placeholder,
.journal-input-group textarea::placeholder {
    color: #718eaf;
}

.journal-input-group select option {
    background: #071b38;
    color: #eaf3ff;
}

.journal-save-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    margin-top: 20px;
    border: 1px solid #8de8ff;
    border-radius: 10px;
    background: linear-gradient(100deg, #38bdf8, #a5f3fc);
    color: #06224a;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 5px 15px #0874b955;
    transition: transform .2s, filter .2s;
}

.journal-save-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.08);
}

/* Entry history */

.journal-record-count {
    flex-shrink: 0;
    padding: 8px 12px;
    border: 1px solid #285486;
    border-radius: 20px;
    background: #071b38;
    color: #55d8ff;
    font-size: 12px;
    font-weight: 700;
}

.journal-entry-list {
    display: grid;
    gap: 16px;
}

.journal-entry-card {
    padding: 20px;
    border: 1px solid #285486;
    border-radius: 15px;
    background: linear-gradient(145deg, #102e59, #071a38);
    transition: border-color .2s, transform .2s;
}

.journal-entry-card:hover {
    border-color: #55c7ff;
    transform: translateY(-2px);
}

.journal-entry-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.journal-entry-date {
    display: block;
    margin-bottom: 8px;
    color: #55c7ff;
    font-size: 11px;
    font-weight: 700;
}

.journal-entry-top h3 {
    margin: 0;
    color: #fff;
    font-size: 18px;
    overflow-wrap: anywhere;
}

.journal-mood {
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid #285486;
    border-radius: 20px;
    background: #071b38;
    color: #c4d9f7;
    font-size: 12px;
}

.journal-entry-content {
    margin: 17px 0;
    color: #c4d9f7;
    font-size: 14px;
    line-height: 1.9;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.journal-entry-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding-top: 13px;
    border-top: 1px solid #24558e66;
}

.journal-entry-footer > span {
    color: #91afd3;
    font-size: 11px;
}

.journal-delete-btn {
    padding: 8px 12px;
    border: 1px solid #a7475a;
    border-radius: 8px;
    background: #451e32;
    color: #ffb0bb;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.journal-delete-btn:hover {
    background: #69283f;
}

.journal-empty {
    padding: 45px 15px;
    border: 1px dashed #35689d;
    border-radius: 15px;
    background: linear-gradient(145deg, #102e59, #071a38);
    text-align: center;
}

.journal-empty div {
    margin-bottom: 12px;
    font-size: 38px;
}

.journal-empty h3 {
    margin: 0 0 8px;
    color: #fff;
}

.journal-empty p {
    margin: 0;
    color: #a9c5e9;
    font-size: 13px;
}

/* Responsive */

@media (max-width: 850px) {
    .journal-stats {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
    .journal-header {
        padding: 20px;
    }

    .journal-header h1 {
        font-size: 24px;
    }

    .journal-header-icon {
        width: 52px;
        height: 52px;
        font-size: 26px;
    }

    .journal-form-grid {
        grid-template-columns: 1fr;
    }

    .journal-input-wide {
        grid-column: auto;
    }

    .journal-form-card,
    .journal-history {
        padding: 17px;
    }

    .journal-entry-top {
        flex-direction: column;
    }

    .journal-section-heading h2 {
        font-size: 18px;
    }
}</style>