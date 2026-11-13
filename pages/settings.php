<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/db.php';

$userId = (int)($_SESSION['user_id'] ?? 1);

function settingsEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare(
    "INSERT IGNORE INTO user_settings (userid) VALUES (?)"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->close();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $theme = $_POST['theme'] ?? 'dark';
    $language = $_POST['language'] ?? 'English';
    $weekStart = $_POST['week_start'] ?? 'Monday';

    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $compactMode = isset($_POST['compact_mode']) ? 1 : 0;

    if (!in_array($theme, ['dark', 'light'], true) ||
        !in_array($language, ['English', 'Arabic'], true) ||
        !in_array($weekStart, ['Monday', 'Sunday'], true)) {

        $message = 'Invalid settings selected.';
        $messageType = 'error';

    } else {

        $stmt = $conn->prepare(
            "UPDATE user_settings
             SET theme = ?,
                 language = ?,
                 week_start = ?,
                 notifications = ?,
                 compact_mode = ?
             WHERE userid = ?"
        );

        $stmt->bind_param(
            "sssiii",
            $theme,
            $language,
            $weekStart,
            $notifications,
            $compactMode,
            $userId
        );

        if ($stmt->execute()) {
            $message = 'Settings saved successfully!';
            $messageType = 'success';
        } else {
            $message = 'Could not save settings.';
            $messageType = 'error';
        }

        $stmt->close();
    }
}

$stmt = $conn->prepare(
    "SELECT theme, language, week_start, notifications, compact_mode
     FROM user_settings
     WHERE userid = ?"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="settings-page">

    <div class="settings-header">
        <div>
            <span class="settings-label">PERSONALIZE YOUR SPACE</span>
            <h1>Settings</h1>
            <p>Manage your LifeOS preferences and customize your experience.</p>
        </div>

        <div class="settings-header-icon">⚙️</div>
    </div>

    <?php if ($message): ?>
        <div class="settings-message <?= $messageType ?>">
            <?= settingsEscape($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" data-settings-form>

        <section class="settings-card">
            <div class="settings-section-title">
                <span>🎨</span>
                <div>
                    <h2>Appearance</h2>
                    <p>Customize how your workspace looks.</p>
                </div>
            </div>

            <div class="settings-option">
                <div>
                    <h3>Theme</h3>
                    <p>Choose your preferred appearance.</p>
                </div>

                <select name="theme">
                    <option value="dark" <?= $settings['theme'] === 'dark' ? 'selected' : '' ?>>
                        Dark
                    </option>
                    <option value="light" <?= $settings['theme'] === 'light' ? 'selected' : '' ?>>
                        Light
                    </option>
                </select>
            </div>

            <div class="settings-option">
                <div>
                    <h3>Compact Layout</h3>
                    <p>Use a more compact layout for your pages.</p>
                </div>

                <label class="settings-switch">
                    <input type="checkbox" name="compact_mode"
                        <?= $settings['compact_mode'] ? 'checked' : '' ?>>
                    <span></span>
                </label>
            </div>
        </section>

        <section class="settings-card">
            <div class="settings-section-title">
                <span>🌍</span>
                <div>
                    <h2>Language & Region</h2>
                    <p>Set your preferred language and calendar format.</p>
                </div>
            </div>

            <div class="settings-option">
                <div>
                    <h3>Language</h3>
                    <p>Choose your application language.</p>
                </div>

                <select name="language">
                    <option value="English" <?= $settings['language'] === 'English' ? 'selected' : '' ?>>
                        English
                    </option>
                    <option value="Arabic" <?= $settings['language'] === 'Arabic' ? 'selected' : '' ?>>
                        العربية
                    </option>
                </select>
            </div>

            <div class="settings-option">
                <div>
                    <h3>Week Starts On</h3>
                    <p>Choose the first day of your calendar week.</p>
                </div>

                <select name="week_start">
                    <option value="Monday" <?= $settings['week_start'] === 'Monday' ? 'selected' : '' ?>>
                        Monday
                    </option>
                    <option value="Sunday" <?= $settings['week_start'] === 'Sunday' ? 'selected' : '' ?>>
                        Sunday
                    </option>
                </select>
            </div>
        </section>

        <section class="settings-card">
            <div class="settings-section-title">
                <span>🔔</span>
                <div>
                    <h2>Notifications</h2>
                    <p>Control your notification preferences.</p>
                </div>
            </div>

            <div class="settings-option">
                <div>
                    <h3>Enable Notifications</h3>
                    <p>Allow reminders and notification preferences.</p>
                </div>

                <label class="settings-switch">
                    <input type="checkbox" name="notifications"
                        <?= $settings['notifications'] ? 'checked' : '' ?>>
                    <span></span>
                </label>
            </div>
        </section>

        <div class="settings-actions">
            <button type="submit" class="settings-save-btn">
                Save Changes
            </button>
        </div>

    </form>

</div>

<style>.settings-page {
    color: #eaf3ff;
    padding: 8px 3px 20px;
}

.settings-header {
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

.settings-label {
    color: #55c7ff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
}

.settings-header h1 {
    color: white;
    font-size: 26px;
    margin: 7px 0;
}

.settings-header p {
    color: #c4d9f7;
    font-size: 13px;
    margin: 0;
}

.settings-header-icon {
    font-size: 32px;
    background: #ffffff12;
    padding: 13px;
    border-radius: 14px;
}

.settings-message {
    padding: 11px 15px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 13px;
}

.settings-message.success {
    background: #123e35;
    color: #7cf5bd;
}

.settings-message.error {
    background: #481f2b;
    color: #ff9caa;
}

.settings-card {
    background: linear-gradient(135deg, #102e59, #071a38);
    border: 1px solid #24558e;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 6px 18px #04132b55;
}

.settings-section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 15px;
    margin-bottom: 5px;
    border-bottom: 1px solid #285486;
}

.settings-section-title > span {
    font-size: 24px;
    background: #55c7ff18;
    padding: 10px;
    border-radius: 10px;
}

.settings-section-title h2 {
    color: white;
    font-size: 18px;
    margin: 0 0 5px;
}

.settings-section-title p {
    color: #a9c5e9;
    font-size: 12px;
    margin: 0;
}

.settings-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 15px 2px;
    border-bottom: 1px solid #28548655;
}

.settings-option:last-child {
    border-bottom: none;
}

.settings-option h3 {
    color: #eaf3ff;
    font-size: 14px;
    margin: 0 0 5px;
}

.settings-option p {
    color: #91afd3;
    font-size: 12px;
    margin: 0;
}

.settings-option select {
    min-width: 125px;
    background: #071b38;
    color: #eaf3ff;
    border: 1px solid #285486;
    border-radius: 8px;
    padding: 9px 11px;
    outline: none;
}

.settings-option select:focus {
    border-color: #38bdf8;
}

.settings-switch {
    position: relative;
    width: 46px;
    height: 25px;
    flex-shrink: 0;
}

.settings-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.settings-switch span {
    position: absolute;
    inset: 0;
    background: #294666;
    border: 1px solid #47709a;
    border-radius: 20px;
    cursor: pointer;
    transition: .25s;
}

.settings-switch span::before {
    content: "";
    position: absolute;
    width: 17px;
    height: 17px;
    left: 3px;
    top: 3px;
    background: white;
    border-radius: 50%;
    transition: .25s;
}

.settings-switch input:checked + span {
    background: #0875b9;
    border-color: #38bdf8;
}

.settings-switch input:checked + span::before {
    transform: translateX(21px);
}

.settings-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 17px;
}

.settings-save-btn {
    background: linear-gradient(90deg, #38bdf8, #a5f3fc);
    color: #06224a;
    border: 1px solid #8de8ff;
    border-radius: 9px;
    padding: 11px 23px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 12px #0874b955;
}

.settings-save-btn:hover {
    transform: translateY(-1px);
}

@media (max-width: 600px) {
    .settings-card {
        padding: 15px;
    }

    .settings-option {
        align-items: flex-start;
    }

    .settings-option select {
        min-width: 105px;
    }

    .settings-header h1 {
        font-size: 23px;
    }
}</style>