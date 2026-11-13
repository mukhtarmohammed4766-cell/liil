<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}

$userId = (int)($_SESSION['user_id'] ?? 1);

$message = '';
$messageType = '';

/* =====================================
   HELPER
===================================== */

function learningEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* =====================================
   HANDLE FORM ACTIONS
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* ADD LEARNING ITEM */

    if ($action === 'add') {

        $title = trim($_POST['title'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $targetDate = trim($_POST['target_date'] ?? '');
        $studyMinutes = max(0, (int)($_POST['study_minutes'] ?? 0));

        if ($title === '' || $subject === '') {

            $message = "Please enter the learning title and subject.";
            $messageType = "error";

        } else {

            $targetDateValue = $targetDate !== '' ? $targetDate : null;

            $stmt = $conn->prepare("
                INSERT INTO learning
                (userid, title, subject, description, target_date, study_minutes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                die("Learning SQL Error: " . learningEscape($conn->error));
            }

            $stmt->bind_param(
                "issssi",
                $userId,
                $title,
                $subject,
                $description,
                $targetDateValue,
                $studyMinutes
            );

            if ($stmt->execute()) {
                $message = "Your learning item has been added successfully!";
                $messageType = "success";
            } else {
                $message = "Unable to add this learning item.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }

    /* UPDATE PROGRESS AND STATUS */

    elseif ($action === 'update') {

        $id = (int)($_POST['id'] ?? 0);
        $progress = max(0, min(100, (int)($_POST['progress'] ?? 0)));
        $status = $_POST['status'] ?? 'pending';

        $allowedStatuses = [
            'pending',
            'in_progress',
            'completed'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        if ($status === 'completed') {
            $progress = 100;
        } elseif ($progress === 100) {
            $status = 'completed';
        } elseif ($progress > 0 && $status === 'pending') {
            $status = 'in_progress';
        }

        $stmt = $conn->prepare("
            UPDATE learning
            SET progress = ?, status = ?
            WHERE id = ? AND userid = ?
        ");

        if (!$stmt) {
            die("Learning SQL Error: " . learningEscape($conn->error));
        }

        $stmt->bind_param(
            "isii",
            $progress,
            $status,
            $id,
            $userId
        );

        if ($stmt->execute()) {
            $message = "Learning progress updated successfully!";
            $messageType = "success";
        } else {
            $message = "Unable to update progress.";
            $messageType = "error";
        }

        $stmt->close();
    }

    /* DELETE LEARNING ITEM */

    elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        $stmt = $conn->prepare("
            DELETE FROM learning
            WHERE id = ? AND userid = ?
        ");

        if (!$stmt) {
            die("Learning SQL Error: " . learningEscape($conn->error));
        }

        $stmt->bind_param("ii", $id, $userId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Learning item deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Learning item was not found or could not be deleted.";
            $messageType = "error";
        }

        $stmt->close();
    }
}

/* =====================================
   LEARNING STATISTICS
===================================== */

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'completed'), 0) AS completed,
        COALESCE(SUM(status = 'in_progress'), 0) AS in_progress,
        COALESCE(SUM(status = 'pending'), 0) AS pending,
        COALESCE(AVG(progress), 0) AS average_progress,
        COALESCE(SUM(study_minutes), 0) AS total_minutes
    FROM learning
    WHERE userid = ?
");

if (!$stmt) {
    die("Learning SQL Error: " . learningEscape($conn->error));
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalLearning = (int)($stats['total'] ?? 0);
$completedLearning = (int)($stats['completed'] ?? 0);
$inProgressLearning = (int)($stats['in_progress'] ?? 0);
$pendingLearning = (int)($stats['pending'] ?? 0);

$averageProgress = (int)round(
    (float)($stats['average_progress'] ?? 0)
);

$totalMinutes = (int)($stats['total_minutes'] ?? 0);

$totalHours = floor($totalMinutes / 60);
$remainingMinutes = $totalMinutes % 60;

/* =====================================
   GET LEARNING ITEMS
===================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        subject,
        description,
        target_date,
        status,
        progress,
        study_minutes,
        created_at
    FROM learning
    WHERE userid = ?
    ORDER BY
        CASE
            WHEN status = 'in_progress' THEN 1
            WHEN status = 'pending' THEN 2
            ELSE 3
        END,
        id DESC
");

if (!$stmt) {
    die("Learning SQL Error: " . learningEscape($conn->error));
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$learningItems = $stmt->get_result();

?>

<section class="learning-page">

    <!-- HEADER -->

    <div class="learning-header">

        <div class="learning-header-content">

            <span class="learning-label">
                GROW YOUR KNOWLEDGE
            </span>

            <h1>Learning Center</h1>

            <p>
                Every skill you learn is a step toward a better you.
                Plan your learning, track your progress, and keep growing.
            </p>

        </div>

        <div class="learning-header-icon">
            <span>✦</span>
            <strong>Learn</strong>
            <small>Grow. Improve. Achieve.</small>
        </div>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ''): ?>

        <div class="learning-message <?= learningEscape($messageType) ?>">
            <?= learningEscape($message) ?>
        </div>

    <?php endif; ?>


    <!-- STATISTICS -->

    <div class="learning-stats">

        <div class="learning-stat-card">

            <div class="learning-stat-icon blue">▤</div>

            <div>
                <span>Total Courses</span>
                <h3><?= $totalLearning ?></h3>
                <small>All your learning items</small>
            </div>

        </div>


        <div class="learning-stat-card">

            <div class="learning-stat-icon purple">◷</div>

            <div>
                <span>In Progress</span>
                <h3><?= $inProgressLearning ?></h3>
                <small>Currently learning</small>
            </div>

        </div>


        <div class="learning-stat-card">

            <div class="learning-stat-icon green">✓</div>

            <div>
                <span>Completed</span>
                <h3><?= $completedLearning ?></h3>
                <small>Successfully finished</small>
            </div>

        </div>


        <div class="learning-stat-card">

            <div class="learning-stat-icon orange">◴</div>

            <div>
                <span>Study Time</span>

                <h3>
                    <?= $totalHours ?>h
                    <?= $remainingMinutes ?>m
                </h3>

                <small>Total recorded study time</small>
            </div>

        </div>

    </div>


    <!-- OVERALL PROGRESS -->

    <div class="learning-overall-card">

        <div class="learning-overall-top">

            <div>
                <span class="learning-small-label">
                    YOUR LEARNING JOURNEY
                </span>

                <h3>Overall Progress</h3>

                <p>
                    Keep showing up. Small steps create big results.
                </p>
            </div>

            <div class="learning-overall-number">
                <?= $averageProgress ?>%
            </div>

        </div>

        <div class="learning-overall-track">
            <span style="width: <?= $averageProgress ?>%"></span>
        </div>

        <div class="learning-overall-footer">
            <span><?= $completedLearning ?> completed</span>
            <span><?= $pendingLearning ?> waiting to start</span>
        </div>

    </div>


    <!-- ADD LEARNING FORM -->

    <div class="learning-form-card" id="learning-form">

        <div class="learning-section-heading">

            <div>
                <span class="learning-small-label">START SOMETHING NEW</span>
                <h3>Add New Learning</h3>
                <p>Create a course, subject, or personal learning goal.</p>
            </div>

            <div class="learning-heading-icon">＋</div>

        </div>


        <form method="POST"
              class="learning-form"
              data-learning-form>

            <input type="hidden" name="action" value="add">

            <div class="learning-form-grid">

                <div class="learning-input-group">

                    <label for="learning-title">
                        Learning Title *
                    </label>

                    <input
                        type="text"
                        id="learning-title"
                        name="title"
                        placeholder="Example: Learn PHP"
                        maxlength="150"
                        required
                    >

                </div>


                <div class="learning-input-group">

                    <label for="learning-subject">
                        Subject / Category *
                    </label>

                    <input
                        type="text"
                        id="learning-subject"
                        name="subject"
                        placeholder="Example: Programming"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="learning-input-group">

                    <label for="learning-date">
                        Target Date
                    </label>

                    <input
                        type="date"
                        id="learning-date"
                        name="target_date"
                    >

                </div>


                <div class="learning-input-group">

                    <label for="learning-minutes">
                        Study Minutes
                    </label>

                    <input
                        type="number"
                        id="learning-minutes"
                        name="study_minutes"
                        min="0"
                        value="0"
                        placeholder="0"
                    >

                </div>


                <div class="learning-input-group learning-input-wide">

                    <label for="learning-description">
                        Description / What do you want to learn?
                    </label>

                    <textarea
                        id="learning-description"
                        name="description"
                        rows="3"
                        placeholder="Write your learning plan..."
                    ></textarea>

                </div>

            </div>


            <div class="learning-form-actions">

                <button type="submit" class="learning-save-btn">
                    <span>＋</span> Add to My Learning
                </button>

            </div>

        </form>

    </div>


    <!-- LEARNING LIST -->

    <div class="learning-list-section">

        <div class="learning-section-heading">

            <div>
                <span class="learning-small-label">YOUR PERSONAL LIBRARY</span>
                <h3>My Learning</h3>
                <p>Follow your progress and keep your goals moving.</p>
            </div>

            <span class="learning-record-count">
                <?= $totalLearning ?> items
            </span>

        </div>


        <?php if ($learningItems->num_rows > 0): ?>

            <div class="learning-list">

                <?php while ($item = $learningItems->fetch_assoc()): ?>

                    <?php
                    $statusLabels = [
                        'pending' => 'Not Started',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed'
                    ];

                    $status = $item['status'];
                    $progress = (int)$item['progress'];

                    $statusLabel = $statusLabels[$status] ?? 'Not Started';

                    $targetDateText = !empty($item['target_date'])
                        ? date('M d, Y', strtotime($item['target_date']))
                        : 'No target date';

                    $minutes = (int)$item['study_minutes'];
                    ?>

                    <article class="learning-card">

                        <div class="learning-card-top">

                            <div class="learning-card-title">

                                <div class="learning-book-icon">
                                    <span>▤</span>
                                </div>

                                <div>
                                    <span class="learning-subject">
                                        <?= learningEscape($item['subject']) ?>
                                    </span>

                                    <h4>
                                        <?= learningEscape($item['title']) ?>
                                    </h4>
                                </div>

                            </div>


                            <span class="learning-status <?= learningEscape($status) ?>">
                                <?= learningEscape($statusLabel) ?>
                            </span>

                        </div>


                        <?php if (!empty($item['description'])): ?>

                            <p class="learning-description">
                                <?= nl2br(learningEscape($item['description'])) ?>
                            </p>

                        <?php endif; ?>


                        <div class="learning-card-meta">

                            <span>
                                <strong>◷</strong>
                                <?= learningEscape($targetDateText) ?>
                            </span>

                            <span>
                                <strong>◴</strong>
                                <?= $minutes ?> study minutes
                            </span>

                        </div>


                        <div class="learning-progress-heading">

                            <span>Learning Progress</span>

                            <strong><?= $progress ?>%</strong>

                        </div>


                        <div class="learning-progress-track">
                            <span style="width: <?= $progress ?>%"></span>
                        </div>


                        <!-- UPDATE FORM -->

                        <form method="POST"
                              class="learning-update-form"
                              data-learning-form>

                            <input type="hidden" name="action" value="update">

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$item['id'] ?>"
                            >

                            <div class="learning-update-fields">

                                <div class="learning-input-group">

                                    <label>Progress (%)</label>

                                    <input
                                        type="number"
                                        name="progress"
                                        min="0"
                                        max="100"
                                        value="<?= $progress ?>"
                                        required
                                    >

                                </div>


                                <div class="learning-input-group">

                                    <label>Status</label>

                                    <select name="status">

                                        <option value="pending"
                                            <?= $status === 'pending' ? 'selected' : '' ?>>
                                            Not Started
                                        </option>

                                        <option value="in_progress"
                                            <?= $status === 'in_progress' ? 'selected' : '' ?>>
                                            In Progress
                                        </option>

                                        <option value="completed"
                                            <?= $status === 'completed' ? 'selected' : '' ?>>
                                            Completed
                                        </option>

                                    </select>

                                </div>


                                <button type="submit" class="learning-update-btn">
                                    Update Progress
                                </button>

                            </div>

                        </form>


                        <!-- DELETE FORM -->

                        <form method="POST"
                              class="learning-delete-form"
                              data-learning-form
                              onsubmit="return confirm('Are you sure you want to delete this learning item?');">

                            <input type="hidden" name="action" value="delete">

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$item['id'] ?>"
                            >

                            <button type="submit" class="learning-delete-btn">
                                Delete Learning
                            </button>

                        </form>

                    </article>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="learning-empty">

                <div class="learning-empty-icon">✦</div>

                <h3>Your learning journey starts here</h3>

                <p>
                    You haven't added any courses yet.
                    Add your first learning goal and start growing today.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>

<?php
$stmt->close();
?>


<style>/* =====================================
   LEARNING PAGE
===================================== */

.learning-page {
    color: #eaf3ff;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* HEADER */

.learning-header {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
    padding: 35px;
    border: 1px solid #2366ae;
    border-radius: 20px;
    background:
        radial-gradient(circle at 85% 20%, #1676d955, transparent 35%),
        linear-gradient(120deg, #06172f, #103b70, #08234b);
    box-shadow: 0 12px 30px #00152e66;
}

.learning-header-content {
    max-width: 650px;
    position: relative;
    z-index: 1;
}

.learning-label,
.learning-small-label {
    display: inline-block;
    color: #55c7ff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 10px;
}

.learning-header h1 {
    color: #fff;
    font-size: clamp(27px, 4vw, 36px);
    margin: 0 0 12px;
    font-weight: 800;
}

.learning-header p {
    color: #c4d9f7;
    line-height: 1.8;
    font-size: 14px;
    margin: 0;
}

.learning-header-icon {
    min-width: 150px;
    min-height: 150px;
    border: 1px solid #55c7ff55;
    border-radius: 50%;
    background: #0a2d55aa;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 35px #38bdf833;
}

.learning-header-icon span {
    color: #55d8ff;
    font-size: 38px;
    line-height: 1.2;
}

.learning-header-icon strong {
    color: #fff;
    font-size: 17px;
}

.learning-header-icon small {
    color: #a9c5e9;
    font-size: 10px;
    margin-top: 5px;
}

/* MESSAGE */

.learning-message {
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
}

.learning-message.success {
    color: #7cf5bd;
    background: #0d49354d;
    border: 1px solid #36bd8966;
}

.learning-message.error {
    color: #ff9caa;
    background: #551d2b55;
    border: 1px solid #ff718766;
}

/* STATISTICS */

.learning-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.learning-stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px;
    min-width: 0;
    border: 1px solid #24558e;
    border-radius: 16px;
    background: linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 8px 20px #04132b66;
}

.learning-stat-icon {
    width: 47px;
    height: 47px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 14px;
    font-size: 23px;
    font-weight: 800;
}

.learning-stat-icon.blue {
    color: #55c7ff;
    background: #0875e833;
}

.learning-stat-icon.purple {
    color: #c4a5ff;
    background: #8855df33;
}

.learning-stat-icon.green {
    color: #7cf5bd;
    background: #26b77b33;
}

.learning-stat-icon.orange {
    color: #ffc477;
    background: #e58a2c33;
}

.learning-stat-card span {
    color: #a9c5e9;
    font-size: 12px;
}

.learning-stat-card h3 {
    color: #fff;
    font-size: 23px;
    margin: 5px 0;
    overflow-wrap: anywhere;
}

.learning-stat-card small {
    color: #91afd3;
    font-size: 10px;
}

/* OVERALL PROGRESS */

.learning-overall-card {
    padding: 25px;
    border: 1px solid #24558e;
    border-radius: 18px;
    background: linear-gradient(120deg, #102e59, #071a38);
    box-shadow: 0 8px 20px #04132b66;
}

.learning-overall-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.learning-overall-top h3 {
    color: #fff;
    font-size: 20px;
    margin: 0 0 7px;
}

.learning-overall-top p {
    color: #a9c5e9;
    font-size: 12px;
    margin: 0;
    line-height: 1.7;
}

.learning-overall-number {
    color: #55d8ff;
    font-size: 32px;
    font-weight: 800;
}

.learning-overall-track,
.learning-progress-track {
    height: 10px;
    overflow: hidden;
    border-radius: 20px;
    background: #071b38;
    border: 1px solid #285486;
}

.learning-overall-track {
    margin-top: 22px;
}

.learning-overall-track span,
.learning-progress-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #0875e8, #38bdf8, #a5f3fc);
    transition: width 0.35s ease;
}

.learning-overall-footer {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-top: 12px;
    color: #91afd3;
    font-size: 11px;
}

/* FORM CARD */

.learning-form-card {
    padding: 25px;
    border: 1px solid #24558e;
    border-radius: 18px;
    background:
        radial-gradient(circle at 100% 0%, #174b8430, transparent 35%),
        linear-gradient(145deg, #0c2850, #061832);
    box-shadow: 0 10px 25px #04132b66;
}

.learning-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 22px;
}

.learning-section-heading h3 {
    color: #fff;
    font-size: 20px;
    margin: 0 0 7px;
}

.learning-section-heading p {
    color: #a9c5e9;
    font-size: 12px;
    line-height: 1.6;
    margin: 0;
}

.learning-heading-icon {
    width: 43px;
    height: 43px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    color: #06224a;
    background: linear-gradient(135deg, #38bdf8, #a5f3fc);
    border-radius: 13px;
    font-size: 25px;
    font-weight: 700;
}

.learning-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.learning-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.learning-input-group label {
    color: #c4d9f7;
    font-size: 12px;
    font-weight: 700;
}

.learning-input-group input,
.learning-input-group textarea,
.learning-input-group select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 12px 14px;
    color: #eaf3ff;
    background: #071b38;
    border: 1px solid #285486;
    border-radius: 10px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.learning-input-group input,
.learning-input-group select {
    min-height: 44px;
}

.learning-input-group textarea {
    resize: vertical;
    min-height: 95px;
}

.learning-input-group input::placeholder,
.learning-input-group textarea::placeholder {
    color: #718eaf;
}

.learning-input-group input:focus,
.learning-input-group textarea:focus,
.learning-input-group select:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px #38bdf81c;
}

.learning-input-group input[type="date"] {
    color-scheme: dark;
}

.learning-input-wide {
    grid-column: 1 / -1;
}

.learning-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}

.learning-save-btn,
.learning-update-btn {
    border: 1px solid #8de8ff;
    border-radius: 10px;
    color: #06224a;
    background: linear-gradient(120deg, #38bdf8, #a5f3fc);
    box-shadow: 0 5px 15px #0874b944;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.learning-save-btn {
    padding: 13px 21px;
    font-size: 13px;
}

.learning-save-btn span {
    font-size: 17px;
    margin-right: 5px;
}

.learning-save-btn:hover,
.learning-update-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px #0874b966;
}

/* LEARNING LIST */

.learning-list-section {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.learning-list-section > .learning-section-heading {
    margin-bottom: 0;
}

.learning-record-count {
    flex-shrink: 0;
    color: #55d8ff;
    background: #0875e822;
    border: 1px solid #285486;
    border-radius: 20px;
    padding: 8px 13px;
    font-size: 11px;
    font-weight: 700;
}

.learning-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.learning-card {
    min-width: 0;
    padding: 21px;
    border: 1px solid #24558e;
    border-radius: 16px;
    background: linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 8px 20px #04132b55;
}

.learning-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.learning-card-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.learning-book-icon {
    width: 43px;
    height: 43px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    color: #55d8ff;
    background: #0875e833;
    border: 1px solid #285486;
    border-radius: 12px;
    font-size: 21px;
}

.learning-subject {
    display: block;
    color: #55c7ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.learning-card-title h4 {
    color: #fff;
    font-size: 16px;
    margin: 0;
    overflow-wrap: anywhere;
}

.learning-status {
    flex-shrink: 0;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

.learning-status.pending {
    color: #ffc477;
    background: #e58a2c22;
    border: 1px solid #e58a2c55;
}

.learning-status.in_progress {
    color: #55d8ff;
    background: #0875e822;
    border: 1px solid #38bdf855;
}

.learning-status.completed {
    color: #7cf5bd;
    background: #26b77b22;
    border: 1px solid #26b77b66;
}

.learning-description {
    color: #a9c5e9;
    font-size: 12px;
    line-height: 1.8;
    margin: 16px 0;
    overflow-wrap: anywhere;
}

.learning-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 18px;
    margin: 17px 0;
    color: #91afd3;
    font-size: 11px;
}

.learning-card-meta strong {
    color: #55d8ff;
    margin-right: 4px;
}

.learning-progress-heading {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 9px;
    color: #c4d9f7;
    font-size: 11px;
}

.learning-progress-heading strong {
    color: #55d8ff;
}

.learning-progress-track {
    height: 8px;
}

.learning-update-form {
    margin-top: 20px;
    padding-top: 17px;
    border-top: 1px solid #24558e88;
}

.learning-update-fields {
    display: grid;
    grid-template-columns: 0.7fr 1fr auto;
    align-items: end;
    gap: 10px;
}

.learning-update-fields .learning-input-group label {
    font-size: 10px;
}

.learning-update-fields .learning-input-group input,
.learning-update-fields .learning-input-group select {
    padding: 10px;
    font-size: 11px;
}

.learning-update-btn {
    min-height: 42px;
    padding: 0 13px;
    font-size: 11px;
}

.learning-delete-form {
    display: flex;
    justify-content: flex-end;
    margin-top: 13px;
}

.learning-delete-btn {
    padding: 7px 11px;
    color: #ff9caa;
    background: #551d2b33;
    border: 1px solid #ff718755;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}

.learning-delete-btn:hover {
    background: #8f293d55;
}

/* EMPTY STATE */

.learning-empty {
    padding: 45px 20px;
    text-align: center;
    border: 1px dashed #3471aa;
    border-radius: 17px;
    background: linear-gradient(145deg, #102e59, #071a38);
}

.learning-empty-icon {
    width: 60px;
    height: 60px;
    display: grid;
    place-items: center;
    margin: 0 auto 15px;
    color: #55d8ff;
    background: #0875e833;
    border-radius: 18px;
    font-size: 30px;
}

.learning-empty h3 {
    color: #fff;
    font-size: 18px;
    margin: 0 0 9px;
}

.learning-empty p {
    max-width: 430px;
    margin: auto;
    color: #a9c5e9;
    font-size: 12px;
    line-height: 1.8;
}

/* RESPONSIVE */

@media (max-width: 1050px) {
    .learning-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .learning-list {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
    .learning-page {
        gap: 17px;
    }

    .learning-header {
        padding: 24px 20px;
    }

    .learning-header-icon {
        display: none;
    }

    .learning-stats {
        gap: 10px;
    }

    .learning-stat-card {
        padding: 14px 11px;
        gap: 9px;
    }

    .learning-stat-icon {
        width: 37px;
        height: 37px;
        font-size: 18px;
    }

    .learning-stat-card h3 {
        font-size: 19px;
    }

    .learning-stat-card small {
        font-size: 9px;
    }

    .learning-form-card,
    .learning-overall-card {
        padding: 19px 15px;
    }

    .learning-form-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .learning-input-wide {
        grid-column: auto;
    }

    .learning-form-actions {
        justify-content: stretch;
    }

    .learning-save-btn {
        width: 100%;
    }

    .learning-overall-number {
        font-size: 26px;
    }

    .learning-card {
        padding: 17px 14px;
    }

    .learning-card-top {
        flex-direction: column;
    }

    .learning-update-fields {
        grid-template-columns: 1fr 1fr;
    }

    .learning-update-btn {
        grid-column: 1 / -1;
        width: 100%;
    }

    .learning-overall-footer {
        font-size: 9px;
    }
}

@media (max-width: 380px) {
    .learning-stats {
        grid-template-columns: 1fr;
    }

    .learning-update-fields {
        grid-template-columns: 1fr;
    }

    .learning-overall-top {
        align-items: flex-start;
    }
}</style>