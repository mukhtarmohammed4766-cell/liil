<?php
/* =========================================
   LIFEOS - HEALTH PAGE
========================================= */

require_once __DIR__ . '/../database/db.php';

/*
 * Temporary user ID for testing.
 * Replace this with the logged-in user's ID
 * when authentication is available.
 */
$userId = 1;

$message = '';
$messageType = 'success';

$escape = static function ($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

/* =========================================
   ADD HEALTH RECORD
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_health'])) {

    $weight = filter_var($_POST['weight'] ?? null, FILTER_VALIDATE_FLOAT);
    $height = filter_var($_POST['height'] ?? null, FILTER_VALIDATE_FLOAT);
    $sleep = filter_var($_POST['sleep_hours'] ?? null, FILTER_VALIDATE_FLOAT);
    $water = filter_var($_POST['water_liters'] ?? null, FILTER_VALIDATE_FLOAT);
    $exercise = filter_var($_POST['exercise_minutes'] ?? null, FILTER_VALIDATE_INT);

    $logDate = trim($_POST['log_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $dateObject = DateTime::createFromFormat('!Y-m-d', $logDate);
    $validDate = $dateObject && $dateObject->format('Y-m-d') === $logDate;

    if (
        $weight === false || $weight <= 0 || $weight > 500 ||
        $height === false || $height <= 0 || $height > 300 ||
        $sleep === false || $sleep < 0 || $sleep > 24 ||
        $water === false || $water < 0 || $water > 30 ||
        $exercise === false || $exercise < 0 || $exercise > 1440 ||
        !$validDate
    ) {
        $message = 'Please enter valid health information.';
        $messageType = 'error';

    } else {

        $stmt = $conn->prepare("
            INSERT INTO health_logs
            (
                userid,
                weight,
                height,
                sleep_hours,
                water_liters,
                exercise_minutes,
                notes,
                log_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
            $stmt->bind_param(
                "iddddiss",
                $userId,
                $weight,
                $height,
                $sleep,
                $water,
                $exercise,
                $notes,
                $logDate
            );

            if ($stmt->execute()) {
                $message = 'Health record added successfully!';
            } else {
                $message = 'Could not save your health record.';
                $messageType = 'error';
            }

            $stmt->close();
        } else {
            $message = 'Database error. Please try again.';
            $messageType = 'error';
        }
    }
}

/* =========================================
   DELETE HEALTH RECORD
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_health'])) {

    $recordId = filter_var(
        $_POST['record_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($recordId && $recordId > 0) {

        $stmt = $conn->prepare("
            DELETE FROM health_logs
            WHERE id = ? AND userid = ?
        ");

        if ($stmt) {
            $stmt->bind_param("ii", $recordId, $userId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = 'Health record deleted successfully!';
            } else {
                $message = 'Record not found.';
                $messageType = 'error';
            }

            $stmt->close();
        }
    } else {
        $message = 'Invalid record.';
        $messageType = 'error';
    }
}

/* =========================================
   GET HEALTH STATISTICS
========================================= */

$stats = [
    'total_records' => 0,
    'average_weight' => 0,
    'average_sleep' => 0,
    'average_water' => 0,
    'total_exercise' => 0
];

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_records,
        COALESCE(AVG(weight), 0) AS average_weight,
        COALESCE(AVG(sleep_hours), 0) AS average_sleep,
        COALESCE(AVG(water_liters), 0) AS average_water,
        COALESCE(SUM(exercise_minutes), 0) AS total_exercise
    FROM health_logs
    WHERE userid = ?
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $stats = $result->fetch_assoc() ?: $stats;

    $stmt->close();
}

/* =========================================
   GET LATEST HEALTH RECORD
========================================= */

$latestRecord = null;

$stmt = $conn->prepare("
    SELECT weight, height
    FROM health_logs
    WHERE userid = ?
    ORDER BY log_date DESC, id DESC
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $latestRecord = $result->fetch_assoc();

    $stmt->close();
}

/* =========================================
   CALCULATE BMI
========================================= */

$bmi = null;
$bmiLabel = 'No data';

if ($latestRecord) {
    $heightInMeters = (float)$latestRecord['height'] / 100;

    if ($heightInMeters > 0) {
        $bmi = (float)$latestRecord['weight'] /
            ($heightInMeters * $heightInMeters);

        if ($bmi < 18.5) {
            $bmiLabel = 'Underweight';
        } elseif ($bmi < 25) {
            $bmiLabel = 'Normal';
        } elseif ($bmi < 30) {
            $bmiLabel = 'Overweight';
        } else {
            $bmiLabel = 'Obesity range';
        }
    }
}

/* =========================================
   GET ALL HEALTH RECORDS
========================================= */

$healthRecords = [];

$stmt = $conn->prepare("
    SELECT
        id,
        weight,
        height,
        sleep_hours,
        water_liters,
        exercise_minutes,
        notes,
        log_date
    FROM health_logs
    WHERE userid = ?
    ORDER BY log_date DESC, id DESC
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $healthRecords[] = $row;
    }

    $stmt->close();
}
?>

<div class="health-page">

    <!-- PAGE HEADER -->
    <div class="health-header">
        <div>
            <span class="health-label">HEALTH TRACKER</span>
            <h1>Your Health</h1>
            <p>Track your daily habits and take care of your body.</p>
        </div>

        <button type="button" class="health-add-btn"
                onclick="showHealthForm()">
            + Add Record
        </button>
    </div>

    <!-- MESSAGE -->
    <?php if ($message !== ''): ?>
        <div class="health-message <?= $escape($messageType) ?>">
            <?= $escape($message) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="health-stats">

        <div class="health-stat-card">
            <div class="health-stat-icon blue">⚖️</div>
            <div>
                <p>Average Weight</p>
                <h2><?= number_format((float)$stats['average_weight'], 1) ?> <small>kg</small></h2>
            </div>
        </div>

        <div class="health-stat-card">
            <div class="health-stat-icon purple">😴</div>
            <div>
                <p>Average Sleep</p>
                <h2><?= number_format((float)$stats['average_sleep'], 1) ?> <small>hrs</small></h2>
            </div>
        </div>

        <div class="health-stat-card">
            <div class="health-stat-icon green">💧</div>
            <div>
                <p>Average Water</p>
                <h2><?= number_format((float)$stats['average_water'], 1) ?> <small>L</small></h2>
            </div>
        </div>

        <div class="health-stat-card">
            <div class="health-stat-icon orange">🏃</div>
            <div>
                <p>Total Exercise</p>
                <h2><?= (int)$stats['total_exercise'] ?> <small>min</small></h2>
            </div>
        </div>

    </div>

    <!-- BMI CARD -->
    <div class="health-bmi-card">
        <div>
            <span class="health-label">BODY MASS INDEX</span>
            <h2>Your BMI</h2>

            <?php if ($bmi !== null): ?>
                <p class="bmi-description">
                    Your latest recorded BMI is
                    <strong><?= number_format($bmi, 1) ?></strong>.
                </p>
            <?php else: ?>
                <p class="bmi-description">
                    Add your first health record to calculate your BMI.
                </p>
            <?php endif; ?>
        </div>

        <div class="bmi-result">
            <strong>
                <?= $bmi !== null ? number_format($bmi, 1) : '--' ?>
            </strong>
            <span><?= $escape($bmiLabel) ?></span>
        </div>
    </div>

    <!-- ADD RECORD FORM -->
    <div class="health-form-card" id="healthForm">

        <div class="health-section-heading">
            <div>
                <h2>Add Health Record</h2>
                <p>Enter your health information below.</p>
            </div>

            <button type="button" class="health-close-btn"
                    onclick="hideHealthForm()">✕</button>
        </div>

        <form class="health-form" action="pages/health.php" method="POST">

            <div class="health-form-grid">

                <div class="health-input-group">
                    <label for="weight">Weight (kg)</label>
                    <input type="number" id="weight" name="weight"
                           min="0.1" max="500" step="0.1"
                           placeholder="e.g. 70" required>
                </div>

                <div class="health-input-group">
                    <label for="height">Height (cm)</label>
                    <input type="number" id="height" name="height"
                           min="1" max="300" step="0.1"
                           placeholder="e.g. 175" required>
                </div>

                <div class="health-input-group">
                    <label for="sleep_hours">Sleep Hours</label>
                    <input type="number" id="sleep_hours" name="sleep_hours"
                           min="0" max="24" step="0.5"
                           placeholder="e.g. 8" required>
                </div>

                <div class="health-input-group">
                    <label for="water_liters">Water (Liters)</label>
                    <input type="number" id="water_liters" name="water_liters"
                           min="0" max="30" step="0.1"
                           placeholder="e.g. 2.5" required>
                </div>

                <div class="health-input-group">
                    <label for="exercise_minutes">Exercise (Minutes)</label>
                    <input type="number" id="exercise_minutes"
                           name="exercise_minutes"
                           min="0" max="1440" step="1"
                           placeholder="e.g. 30" required>
                </div>

                <div class="health-input-group">
                    <label for="log_date">Date</label>
                    <input type="date" id="log_date" name="log_date"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="health-input-group health-input-wide">
                    <label for="notes">Notes (Optional)</label>
                    <textarea id="notes" name="notes" rows="3"
                              placeholder="How are you feeling today?"></textarea>
                </div>

            </div>

            <div class="health-form-actions">
                <button type="button" class="health-cancel-btn"
                        onclick="hideHealthForm()">Cancel</button>

                <button type="submit" name="add_health" value="1"
                        class="health-save-btn">
                    Save Record
                </button>
            </div>

        </form>
    </div>

    <!-- HEALTH HISTORY -->
    <div class="health-history">

        <div class="health-section-heading">
            <div>
                <h2>Health History</h2>
                <p>Your previous health records.</p>
            </div>

            <span class="health-record-count">
                <?= count($healthRecords) ?> Records
            </span>
        </div>

        <?php if (empty($healthRecords)): ?>

            <div class="health-empty">
                <div>❤️</div>
                <h3>No health records yet</h3>
                <p>Start tracking your health by adding your first record.</p>
                <button type="button" onclick="showHealthForm()">
                    Add Your First Record
                </button>
            </div>

        <?php else: ?>

            <div class="health-record-list">

                <?php foreach ($healthRecords as $record): ?>

                    <?php
                    $recordHeight = (float)$record['height'] / 100;
                    $recordBmi = $recordHeight > 0
                        ? (float)$record['weight'] / ($recordHeight * $recordHeight)
                        : 0;
                    ?>

                    <div class="health-record-card">

                        <div class="health-record-top">
                            <div>
                                <span class="health-record-date">
                                    <?= $escape(date(
                                        'M d, Y',
                                        strtotime($record['log_date'])
                                    )) ?>
                                </span>

                                <h3>
                                    <?= number_format((float)$record['weight'], 1) ?> kg
                                </h3>
                            </div>

                            <form class="health-form health-delete-form"
                                  action="pages/health.php" method="POST">

                                <input type="hidden" name="record_id"
                                       value="<?= (int)$record['id'] ?>">

                                <button type="submit" name="delete_health"
                                        value="1" class="health-delete-btn">
                                    Delete
                                </button>
                            </form>
                        </div>

                        <div class="health-record-details">

                            <div>
                                <span>Height</span>
                                <strong><?= number_format((float)$record['height'], 1) ?> cm</strong>
                            </div>

                            <div>
                                <span>BMI</span>
                                <strong><?= number_format($recordBmi, 1) ?></strong>
                            </div>

                            <div>
                                <span>Sleep</span>
                                <strong><?= number_format((float)$record['sleep_hours'], 1) ?> hrs</strong>
                            </div>

                            <div>
                                <span>Water</span>
                                <strong><?= number_format((float)$record['water_liters'], 1) ?> L</strong>
                            </div>

                            <div>
                                <span>Exercise</span>
                                <strong><?= (int)$record['exercise_minutes'] ?> min</strong>
                            </div>

                        </div>

                        <?php if (!empty($record['notes'])): ?>
                            <p class="health-record-notes">
                                <?= nl2br($escape($record['notes'])) ?>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>


<style>
    /* =========================================
   LIFEOS - HEALTH PAGE
========================================= */
/* =========================================
   LIFEOS - HEALTH DARK THEME
========================================= */
/* =========================================
   LIFEOS - HEALTH DARK THEME
========================================= */
/* =========================================
   LIFEOS - HEALTH PAGE
   MATCHING GOALS DESIGN
========================================= */

.health-page {
    color: #eaf3ff;
    position: relative;
    animation: healthAppear .5s ease both;
}

@keyframes healthAppear {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* HEADER */

.health-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 28px 32px;
    margin-bottom: 22px;
    border: 1px solid #2366ae;
    border-radius: 20px;
    background:
        radial-gradient(circle at 90% 10%, #1676d955, transparent 35%),
        linear-gradient(120deg, #06172f, #103b70, #08234b);
    box-shadow: 0 12px 30px #00152e66;
}

.health-label {
    color: #55c7ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    display: inline-block;
    margin-bottom: 8px;
}

.health-header h1 {
    color: white;
    font-size: 30px;
    margin: 10px 0;
}

.health-header p {
    color: #c4d9f7;
    font-size: 13px;
    margin: 0;
}

/* BUTTONS */

.health-add-btn,
.health-empty button {
    border: 1px solid #8de8ff;
    border-radius: 10px;
    padding: 13px 22px;
    color: #06224a;
    font-weight: 800;
    font-size: 12px;
    background: linear-gradient(110deg, #38bdf8, #a5f3fc);
    box-shadow: 0 5px 0 #0874b9, 0 10px 20px #0284c755;
    cursor: pointer;
    transition: .25s ease;
}

.health-add-btn:hover,
.health-empty button:hover {
    transform: translateY(-3px);
}

/* MESSAGES */

.health-message {
    padding: 14px 17px;
    margin-bottom: 18px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}

.health-message.success {
    color: #7cf5bd;
    background: #05966920;
    border: 1px solid #10b98170;
}

.health-message.error {
    color: #ff9caa;
    background: #e11d4820;
    border: 1px solid #e11d4870;
}

/* STATISTICS */

.health-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 22px;
}

.health-stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border: 1px solid #24558e;
    border-radius: 17px;
    background: linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 7px 0 #04132b, 0 12px 25px #00000035;
    transition: .3s ease;
}

.health-stat-card:hover {
    transform: translateY(-5px);
}

.health-stat-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 15px;
    font-size: 24px;
}

.health-stat-icon.blue {
    color: #6bd5ff;
    background: linear-gradient(145deg, #0875e8, #0645a0);
}

.health-stat-icon.purple {
    color: #d3b5ff;
    background: linear-gradient(145deg, #7538d8, #3d1689);
}

.health-stat-icon.green {
    color: #65ffc4;
    background: linear-gradient(145deg, #078a70, #07503f);
}

.health-stat-icon.orange {
    color: #ffd18b;
    background: linear-gradient(145deg, #b96a16, #713b0b);
}

.health-stat-card p {
    color: #a9c5e9;
    font-size: 11px;
    margin: 0 0 5px;
}

.health-stat-card h2 {
    color: white;
    font-size: 25px;
    margin: 0;
}

.health-stat-card small {
    color: #91afd3;
    font-size: 11px;
}

/* BMI CARD */

.health-bmi-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 25px 28px;
    margin-bottom: 25px;
    border: 1px solid #2366ae;
    border-radius: 18px;
    background:
        radial-gradient(circle at 90% 10%, #1676d955, transparent 35%),
        linear-gradient(120deg, #06172f, #103b70, #08234b);
    box-shadow: 0 7px 0 #041329, 0 12px 25px #00000030;
    color: white;
}

.health-bmi-card .health-label {
    color: #55c7ff;
}

.health-bmi-card h2 {
    color: white;
    font-size: 22px;
    margin: 8px 0;
}

.bmi-description {
    color: #c4d9f7;
    font-size: 12px;
    margin: 0;
}

.bmi-description strong {
    color: #55d8ff;
}

.bmi-result {
    min-width: 115px;
    text-align: center;
    padding: 14px 20px;
    background: #071b38;
    border: 1px solid #285486;
    border-radius: 13px;
}

.bmi-result strong {
    display: block;
    font-size: 30px;
    color: #55d8ff;
}

.bmi-result span {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #a9c5e9;
}

/* FORM CARD */

.health-form-card {
    display: none;
    padding: 25px;
    margin-bottom: 22px;
    border: 1px solid #24558e;
    border-radius: 18px;
    background:
        radial-gradient(circle at 100% 0%, #174b8430, transparent 40%),
        linear-gradient(145deg, #0c2850, #061832);
    box-shadow: 0 7px 0 #041329, 0 15px 30px #00000030;
}

.health-form-card.show {
    display: block;
    animation: healthAppear .3s ease;
}

/* SECTION HEADINGS */

.health-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}

.health-section-heading h2 {
    color: white;
    font-size: 18px;
    margin: 0;
}

.health-section-heading p {
    color: #91afd3;
    font-size: 11px;
    margin-top: 6px;
}

/* CLOSE BUTTON */

.health-close-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #315d8d;
    border-radius: 9px;
    color: #d7e9ff;
    background: #12345c;
    cursor: pointer;
    transition: .25s;
}

.health-close-btn:hover {
    background: #1e4d7d;
    transform: translateY(-2px);
}

/* FORM INPUTS */

.health-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.health-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.health-input-wide {
    grid-column: 1 / -1;
}

.health-input-group label {
    color: #c8ddf7;
    font-size: 11px;
    font-weight: 600;
}

.health-input-group input,
.health-input-group textarea {
    width: 100%;
    box-sizing: border-box;
    color: white;
    background: #071b38;
    border: 1px solid #285486;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 12px;
    font-family: inherit;
    outline: none;
    transition: .25s;
}

.health-input-group input::placeholder,
.health-input-group textarea::placeholder {
    color: #718cab;
}

.health-input-group input:focus,
.health-input-group textarea:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px #0ea5e933;
}

.health-input-group input[type="date"] {
    color-scheme: dark;
}

.health-input-group textarea {
    resize: vertical;
    min-height: 90px;
}

/* FORM BUTTONS */

.health-form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
}

.health-form-actions button {
    border-radius: 10px;
    padding: 12px 22px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .25s;
}

.health-save-btn {
    color: #06224a;
    background: linear-gradient(110deg, #38bdf8, #a5f3fc);
    border: 1px solid #8de8ff;
}

.health-cancel-btn {
    color: #d7e9ff;
    background: #12345c;
    border: 1px solid #315d8d;
}

.health-form-actions button:hover {
    transform: translateY(-3px);
}

/* HISTORY */

.health-history {
    margin-top: 25px;
}

.health-record-count {
    color: #64d5ff;
    background: #0875d52b;
    border: 1px solid #247bc0;
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 11px;
}

/* RECORD LIST */

.health-record-list {
    display: grid;
    gap: 17px;
}

.health-record-card {
    padding: 22px;
    border: 1px solid #24558e;
    border-radius: 17px;
    background:
        radial-gradient(circle at 100% 0%, #174b8425, transparent 40%),
        linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 7px 0 #041329, 0 12px 25px #00000030;
    transition: .3s ease;
}

.health-record-card:hover {
    transform: translateY(-3px);
    border-color: #3686ce;
}

.health-record-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
}

.health-record-date {
    display: block;
    color: #91afd3;
    font-size: 11px;
    margin-bottom: 7px;
}

.health-record-top h3 {
    color: white;
    font-size: 22px;
    margin: 0;
}

/* RECORD DETAILS */

.health-record-details {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    padding-top: 17px;
    border-top: 1px solid #24558e;
}

.health-record-details div {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.health-record-details span {
    color: #91afd3;
    font-size: 11px;
}

.health-record-details strong {
    color: #eaf3ff;
    font-size: 13px;
}

/* NOTES */

.health-record-notes {
    margin: 16px 0 0;
    padding: 12px;
    color: #c4d9f7;
    background: #071b38;
    border: 1px solid #285486;
    border-radius: 9px;
    font-size: 12px;
    line-height: 1.7;
}

/* DELETE BUTTON */

.health-delete-btn {
    color: #ff9ca8;
    background: #e11d4825;
    border: 1px solid #e11d4855;
    padding: 9px 13px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
    transition: .25s;
}

.health-delete-btn:hover {
    color: white;
    background: #dc2626;
    transform: translateY(-2px);
}

/* EMPTY STATE */

.health-empty {
    text-align: center;
    padding: 45px 20px;
    border: 1px solid #24558e;
    border-radius: 17px;
    background: linear-gradient(145deg, #102e59, #071a38);
    box-shadow: 0 7px 0 #041329, 0 12px 25px #00000030;
}

.health-empty div {
    font-size: 40px;
    margin-bottom: 12px;
}

.health-empty h3 {
    color: white;
    font-size: 16px;
    margin: 0 0 10px;
}

.health-empty p {
    color: #91afd3;
    font-size: 12px;
    margin: 10px 0 20px;
}

/* RESPONSIVE */

@media (max-width: 1000px) {
    .health-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 750px) {
    .health-header {
        padding: 22px;
    }

    .health-header h1 {
        font-size: 24px;
    }

    .health-record-details {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 500px) {
    .health-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .health-stats {
        grid-template-columns: 1fr;
    }

    .health-bmi-card {
        align-items: flex-start;
        flex-direction: column;
    }

    .health-form-card {
        padding: 18px;
    }

    .health-form-grid {
        grid-template-columns: 1fr;
    }

    .health-input-wide {
        grid-column: auto;
    }

    .health-record-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .health-record-card {
        padding: 17px;
    }

    .health-record-top {
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    .health-page,
    .health-card,
    .health-stat-card {
        animation: none !important;
        transition: none !important;
    }
}
</style>