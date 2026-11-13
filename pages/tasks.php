<?php
require_once __DIR__ . '/../database/db.php';

date_default_timezone_set('Africa/Mogadishu');

$user_id = (int)($_SESSION['user_id'] ?? 1);
$today = date('Y-m-d');

$language = 'English';

$settingsStmt = $conn->prepare("
    SELECT language
    FROM user_settings
    WHERE userid = ?
    LIMIT 1
");

if ($settingsStmt) {
    $settingsStmt->bind_param("i", $user_id);
    $settingsStmt->execute();
    $settings = $settingsStmt->get_result()->fetch_assoc();

    if ($settings && $settings['language'] === 'Arabic') {
        $language = 'Arabic';
    }

    $settingsStmt->close();
}

$isArabic = $language === 'Arabic';

$translations = [
    'en' => [
        'eyebrow' => 'YOUR PRODUCTIVITY',
        'title' => 'My Tasks',
        'subtitle' => 'Organize your day, complete your missions, and make progress.',
        'completed' => 'Completed',
        'total_tasks' => 'Total Tasks',
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'create_task' => '＋ Create New Task',
        'create_subtitle' => 'Add something important to your journey.',
        'task_title' => 'Task Title',
        'title_placeholder' => 'What do you want to accomplish?',
        'priority' => 'Priority',
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'due_date' => 'Due Date',
        'date_hint' => 'Select today or a future date.',
        'description' => 'Description',
        'description_placeholder' => 'Add more details about this task...',
        'add_task' => '＋ Add Task',
        'all_tasks' => 'All Tasks',
        'list_subtitle' => 'Track and manage your daily activities.',
        'tasks' => 'tasks',
        'task' => 'Task',
        'status' => 'Status',
        'action' => 'Action',
        'no_description' => 'No description',
        'no_date' => 'No date',
        'delete_title' => 'Delete task',
        'empty_title' => 'No tasks yet',
        'empty_text' => 'Create your first task and start making progress!',
        'msg_title' => 'Please enter a task title.',
        'msg_priority' => 'Invalid priority.',
        'msg_date' => 'Please enter a valid date.',
        'msg_past' => 'Past dates are not allowed!',
        'msg_added' => 'Task added successfully!',
        'msg_save_error' => 'Could not save task.',
        'msg_updated' => 'Task status updated!',
        'msg_deleted' => 'Task deleted successfully!',
        'msg_invalid_status' => 'Invalid task status.',
        'msg_invalid_task' => 'Invalid task.',
        'confirm_delete' => 'Are you sure you want to delete this task?',
        'saving' => 'Saving...',
        'save_error' => 'Error saving task.',
        'update_error' => 'Could not update task.',
        'delete_error' => 'Could not delete task.'
    ],
    'ar' => [
        'eyebrow' => 'إنتاجيتك',
        'title' => 'مهامي',
        'subtitle' => 'نظّم يومك، أنجز مهامك، وواصل التقدّم.',
        'completed' => 'مكتملة',
        'total_tasks' => 'إجمالي المهام',
        'pending' => 'قيد الانتظار',
        'in_progress' => 'قيد التنفيذ',
        'create_task' => '＋ إنشاء مهمة جديدة',
        'create_subtitle' => 'أضف شيئاً مهماً إلى رحلتك.',
        'task_title' => 'عنوان المهمة',
        'title_placeholder' => 'ماذا تريد أن تنجز؟',
        'priority' => 'الأولوية',
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
        'due_date' => 'تاريخ الاستحقاق',
        'date_hint' => 'اختر اليوم أو تاريخاً في المستقبل.',
        'description' => 'الوصف',
        'description_placeholder' => 'أضف تفاصيل أكثر عن هذه المهمة...',
        'add_task' => '＋ إضافة مهمة',
        'all_tasks' => 'كل المهام',
        'list_subtitle' => 'تابع أنشطتك اليومية وأدرها.',
        'tasks' => 'مهام',
        'task' => 'المهمة',
        'status' => 'الحالة',
        'action' => 'الإجراء',
        'no_description' => 'لا يوجد وصف',
        'no_date' => 'لا يوجد تاريخ',
        'delete_title' => 'حذف المهمة',
        'empty_title' => 'لا توجد مهام بعد',
        'empty_text' => 'أنشئ مهمتك الأولى وابدأ بتحقيق التقدّم!',
        'msg_title' => 'يرجى إدخال عنوان المهمة.',
        'msg_priority' => 'الأولوية غير صالحة.',
        'msg_date' => 'يرجى إدخال تاريخ صحيح.',
        'msg_past' => 'لا يمكن اختيار تاريخ سابق!',
        'msg_added' => 'تمت إضافة المهمة بنجاح!',
        'msg_save_error' => 'تعذّر حفظ المهمة.',
        'msg_updated' => 'تم تحديث حالة المهمة!',
        'msg_deleted' => 'تم حذف المهمة بنجاح!',
        'msg_invalid_status' => 'حالة المهمة غير صالحة.',
        'msg_invalid_task' => 'المهمة غير صالحة.',
        'confirm_delete' => 'هل أنت متأكد أنك تريد حذف هذه المهمة؟',
        'saving' => 'جارٍ الحفظ...',
        'save_error' => 'حدث خطأ أثناء حفظ المهمة.',
        'update_error' => 'تعذّر تحديث المهمة.',
        'delete_error' => 'تعذّر حذف المهمة.'
    ]
];

$t = $translations[$isArabic ? 'ar' : 'en'];

function task_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // ADD TASK
    if ($action === 'add') {

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'Medium';
        $due_date = trim($_POST['due_date'] ?? '');

        if ($title === '') {

            $message = $t['msg_title'];
            $messageType = 'error';

        } elseif (!in_array($priority, ['Low', 'Medium', 'High'], true)) {

            $message = $t['msg_priority'];
            $messageType = 'error';

        } else {

            $validDate = true;

            if ($due_date !== '') {

                $dateObject = DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $due_date
                );

                $dateErrors = DateTimeImmutable::getLastErrors();

                $validFormat = (
                    $dateObject !== false &&
                    $dateObject->format('Y-m-d') === $due_date &&
                    (
                        $dateErrors === false ||
                        (
                            $dateErrors['warning_count'] === 0 &&
                            $dateErrors['error_count'] === 0
                        )
                    )
                );

                if (!$validFormat) {

                    $validDate = false;
                    $message = $t['msg_date'];
                    $messageType = 'error';

                } elseif ($due_date < $today) {

                    $validDate = false;
                    $message = $t['msg_past'];
                    $messageType = 'error';
                }
            }

            if ($validDate) {

                $stmt = $conn->prepare("
                    INSERT INTO tasks
                    (user_id, title, description, priority, status, due_date)
                    VALUES (?, ?, ?, ?, 'Pending', NULLIF(?, ''))
                ");

                $stmt->bind_param(
                    "issss",
                    $user_id,
                    $title,
                    $description,
                    $priority,
                    $due_date
                );

                if ($stmt->execute()) {
                    $message = $t['msg_added'];
                    $messageType = 'success';
                } else {
                    $message = $t['msg_save_error'];
                    $messageType = 'error';
                }

                $stmt->close();
            }
        }
    }

    // UPDATE STATUS
    elseif ($action === 'update_status') {

        $task_id = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (
            $task_id > 0 &&
            in_array(
                $status,
                ['Pending', 'In Progress', 'Completed'],
                true
            )
        ) {

            $stmt = $conn->prepare("
                UPDATE tasks
                SET status = ?
                WHERE id = ? AND user_id = ?
            ");

            $stmt->bind_param(
                "sii",
                $status,
                $task_id,
                $user_id
            );

            $stmt->execute();
            $stmt->close();

            $message = $t['msg_updated'];
            $messageType = 'success';

        } else {

            $message = $t['msg_invalid_status'];
            $messageType = 'error';
        }
    }

    // DELETE TASK
    elseif ($action === 'delete') {

        $task_id = (int)($_POST['task_id'] ?? 0);

        if ($task_id > 0) {

            $stmt = $conn->prepare("
                DELETE FROM tasks
                WHERE id = ? AND user_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $task_id,
                $user_id
            );

            $stmt->execute();
            $stmt->close();

            $message = $t['msg_deleted'];
            $messageType = 'success';

        } else {

            $message = $t['msg_invalid_task'];
            $messageType = 'error';
        }
    }
}

// TASK STATISTICS

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Completed'), 0) AS completed,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'In Progress'), 0) AS in_progress
    FROM tasks
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$stats = $stmt->get_result()->fetch_assoc();

$stmt->close();

$total = (int)$stats['total'];
$completed = (int)$stats['completed'];
$pending = (int)$stats['pending'];
$inProgress = (int)$stats['in_progress'];

$percentage = $total > 0
    ? round(($completed / $total) * 100)
    : 0;

// GET TASKS

$stmt = $conn->prepare("
    SELECT *
    FROM tasks
    WHERE user_id = ?
    ORDER BY
        CASE status
            WHEN 'In Progress' THEN 1
            WHEN 'Pending' THEN 2
            WHEN 'Completed' THEN 3
        END,
        id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$tasks = $stmt->get_result();
?>

<section
    class="tasks-page <?php echo $isArabic ? 'tasks-rtl' : ''; ?>"
    id="tasksPage"
    dir="<?php echo $isArabic ? 'rtl' : 'ltr'; ?>"
>

    <div class="tasks-heading">

        <div>
            <span class="tasks-label"><?php echo $t['eyebrow']; ?></span>

            <h2><?php echo $t['title']; ?> <span>✦</span></h2>

            <p><?php echo $t['subtitle']; ?></p>
        </div>

        <div class="task-progress-badge">
            <strong><?php echo $percentage; ?>%</strong>
            <small><?php echo $t['completed']; ?></small>
        </div>

    </div>

    <?php if ($message !== ''): ?>
        <div class="task-message <?php echo task_e($messageType); ?>">
            <?php echo task_e($message); ?>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->

    <section class="task-stats">

        <div class="task-stat-card">
            <div class="task-stat-icon task-blue">☑</div>
            <div>
                <span><?php echo $t['total_tasks']; ?></span>
                <h3><?php echo $total; ?></h3>
            </div>
        </div>

        <div class="task-stat-card">
            <div class="task-stat-icon task-green">✓</div>
            <div>
                <span><?php echo $t['completed']; ?></span>
                <h3><?php echo $completed; ?></h3>
            </div>
        </div>

        <div class="task-stat-card">
            <div class="task-stat-icon task-orange">◷</div>
            <div>
                <span><?php echo $t['pending']; ?></span>
                <h3><?php echo $pending; ?></h3>
            </div>
        </div>

        <div class="task-stat-card">
            <div class="task-stat-icon task-purple">↗️</div>
            <div>
                <span><?php echo $t['in_progress']; ?></span>
                <h3><?php echo $inProgress; ?></h3>
            </div>
        </div>

    </section>

    <!-- ADD TASK FORM -->

    <section class="task-form-panel">

        <div class="task-section-heading">
            <div>
                <h3><?php echo $t['create_task']; ?></h3>
                <p><?php echo $t['create_subtitle']; ?></p>
            </div>
        </div>

        <form
            class="task-form"
            onsubmit="return submitTaskForm(this)"
        >

            <input type="hidden" name="action" value="add">

            <div class="task-form-grid">

                <div class="task-field task-field-wide">
                    <label><?php echo $t['task_title']; ?></label>

                    <input
                        type="text"
                        name="title"
                        placeholder="<?php echo task_e($t['title_placeholder']); ?>"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="task-field">
                    <label><?php echo $t['priority']; ?></label>

                    <select name="priority" required>
                        <option value="Low"><?php echo $t['low']; ?></option>
                        <option value="Medium" selected><?php echo $t['medium']; ?></option>
                        <option value="High"><?php echo $t['high']; ?></option>
                    </select>
                </div>

                <div class="task-field">
                    <label><?php echo $t['due_date']; ?></label>

                    <input
                        type="date"
                        name="due_date"
                        min="<?php echo task_e($today); ?>"
                        id="taskDueDate"
                    >

                    <small class="date-hint">
                        <?php echo $t['date_hint']; ?>
                    </small>
                </div>

                <div class="task-field task-field-wide">
                    <label><?php echo $t['description']; ?></label>

                    <textarea
                        name="description"
                        placeholder="<?php echo task_e($t['description_placeholder']); ?>"
                        rows="3"
                    ></textarea>
                </div>

            </div>

            <button type="submit" class="task-add-btn">
                <?php echo $t['add_task']; ?>
            </button>

        </form>

    </section>

    <!-- ALL TASKS -->

    <section class="task-list-panel">

        <div class="task-section-heading">

            <div>
                <h3><?php echo $t['all_tasks']; ?></h3>
                <p><?php echo $t['list_subtitle']; ?></p>
            </div>

            <span class="task-total-badge">
                <?php echo $total; ?> <?php echo $t['tasks']; ?>
            </span>

        </div>

        <div class="task-table-wrapper">

            <table class="task-table">

                <thead>
                    <tr>
                        <th><?php echo $t['task']; ?></th>
                        <th><?php echo $t['priority']; ?></th>
                        <th><?php echo $t['status']; ?></th>
                        <th><?php echo $t['due_date']; ?></th>
                        <th><?php echo $t['action']; ?></th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($tasks->num_rows > 0): ?>

                    <?php while ($task = $tasks->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <div class="task-name">
                                    <strong><?php echo task_e($task['title']); ?></strong>

                                    <small>
                                        <?php
                                        echo task_e(
                                            $task['description']
                                            ?: $t['no_description']
                                        );
                                        ?>
                                    </small>
                                </div>
                            </td>

                            <td>
                                <span class="task-priority <?php echo strtolower(task_e($task['priority'])); ?>">
                                    <?php
                                    $priorityKey = strtolower($task['priority']);
                                    echo $t[$priorityKey] ?? task_e($task['priority']);
                                    ?>
                                </span>
                            </td>

                            <td>

                                <select
                                    class="task-status-select <?php
                                        echo strtolower(
                                            str_replace(' ', '-', task_e($task['status']))
                                        );
                                    ?>"
                                    onchange="updateTaskStatus(this)"
                                    data-task-id="<?php echo (int)$task['id']; ?>"
                                >

                                    <option
                                        value="Pending"
                                        <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>
                                    >
                                        <?php echo $t['pending']; ?>
                                    </option>

                                    <option
                                        value="In Progress"
                                        <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>
                                    >
                                        <?php echo $t['in_progress']; ?>
                                    </option>

                                    <option
                                        value="Completed"
                                        <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>
                                    >
                                        <?php echo $t['completed']; ?>
                                    </option>

                                </select>

                            </td>

                            <td>
                                <?php if (!empty($task['due_date'])): ?>
                                    <?php
                                    echo task_e(
                                        date(
                                            'M d, Y',
                                            strtotime($task['due_date'])
                                        )
                                    );
                                    ?>
                                <?php else: ?>
                                    <span class="no-date"><?php echo $t['no_date']; ?></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="task-delete-btn"
                                    onclick="deleteTask(<?php echo (int)$task['id']; ?>)"
                                    title="<?php echo task_e($t['delete_title']); ?>"
                                >
                                    🗑
                                </button>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="task-empty">
                            <div>🚀</div>
                            <strong><?php echo $t['empty_title']; ?></strong>
                            <p><?php echo $t['empty_text']; ?></p>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</section>

<style>

.tasks-page {
    color: #eaf3ff;
    position: relative;
    animation: tasksAppear .5s ease both;
}

@keyframes tasksAppear {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* HEADING */

.tasks-heading {
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

.tasks-label {
    color: #55c7ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
}

.tasks-heading h2 {
    color: white;
    font-size: 30px;
    margin: 10px 0;
}

.tasks-heading h2 span {
    color: #39c8ff;
}

.tasks-heading p {
    color: #c4d9f7;
    font-size: 13px;
}

.task-progress-badge {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: linear-gradient(145deg, #164c91, #071b3c);
    border: 2px solid #38bdf8;
    box-shadow: 0 0 22px #168bff66, inset 0 0 15px #ffffff15;
}

.task-progress-badge strong {
    color: #55d8ff;
    font-size: 22px;
}

.task-progress-badge small {
    color: #d5eaff;
    font-size: 9px;
}

/* STATISTICS */

.task-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 22px;
}

.task-stat-card {
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

.task-stat-card:hover {
    transform: translateY(-5px);
}

.task-stat-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 15px;
    font-size: 24px;
}

.task-blue {
    color: #6bd5ff;
    background: linear-gradient(145deg, #0875e8, #0645a0);
}

.task-green {
    color: #65ffc4;
    background: linear-gradient(145deg, #078a70, #07503f);
}

.task-orange {
    color: #ffd08a;
    background: linear-gradient(145deg, #d97706, #874000);
}

.task-purple {
    color: #d3b5ff;
    background: linear-gradient(145deg, #7538d8, #3d1689);
}

.task-stat-card span {
    color: #a9c5e9;
    font-size: 11px;
}

.task-stat-card h3 {
    color: white;
    font-size: 27px;
    margin-top: 5px;
}

/* PANELS */

.task-form-panel,
.task-list-panel {
    padding: 25px;
    margin-bottom: 22px;
    border: 1px solid #24558e;
    border-radius: 18px;
    background:
        radial-gradient(circle at 100% 0%, #174b8430, transparent 40%),
        linear-gradient(145deg, #0c2850, #061832);
    box-shadow: 0 7px 0 #041329, 0 15px 30px #00000030;
}

.task-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}

.task-section-heading h3 {
    color: white;
    font-size: 17px;
}

.task-section-heading p {
    color: #91afd3;
    font-size: 11px;
    margin-top: 6px;
}

.task-total-badge {
    color: #64d5ff;
    background: #0875d52b;
    border: 1px solid #247bc0;
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 11px;
}

/* FORM */

.task-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.task-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.task-field-wide {
    grid-column: 1 / -1;
}

.task-field label {
    color: #c8ddf7;
    font-size: 11px;
    font-weight: 600;
}

.task-form input,
.task-form select,
.task-form textarea {
    width: 100%;
    box-sizing: border-box;
    color: white;
    background: #071b38;
    border: 1px solid #285486;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 12px;
    outline: none;
    transition: .25s;
}

.task-form input::placeholder,
.task-form textarea::placeholder {
    color: #718cab;
}

.task-form input:focus,
.task-form select:focus,
.task-form textarea:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px #0ea5e933;
}

.task-form input[type="date"] {
    color-scheme: dark;
}

.task-form select option {
    background: #071b38;
    color: white;
}

.date-hint {
    color: #82b9e9;
    font-size: 10px;
}

.task-add-btn {
    margin-top: 20px;
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

.task-add-btn:hover {
    transform: translateY(-3px);
}

.task-add-btn:disabled {
    opacity: .6;
    cursor: wait;
}

/* TABLE */

.task-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.task-table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

.task-table th {
    color: #8fb7e5;
    font-size: 10px;
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #24466f;
}

.task-table td {
    color: #e3efff;
    font-size: 11px;
    padding: 15px 12px;
    border-bottom: 1px solid #1a365b;
}

.task-table tbody tr {
    transition: .25s ease;
}

.task-table tbody tr:hover {
    background: #17457755;
}

.task-name strong {
    display: block;
    color: white;
    font-size: 12px;
}

.task-name small {
    display: block;
    color: #8ca9cc;
    margin-top: 5px;
    max-width: 240px;
    overflow-wrap: anywhere;
}

/* PRIORITY */

.task-priority {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 800;
}

.task-priority.high {
    color: #ff9caa;
    background: #e11d4830;
    border: 1px solid #e11d4870;
}

.task-priority.medium {
    color: #ffd18b;
    background: #d977062b;
    border: 1px solid #d9770670;
}

.task-priority.low {
    color: #7cf5bd;
    background: #0596692b;
    border: 1px solid #05966970;
}

/* STATUS */

.task-status-select {
    width: auto;
    min-width: 120px;
    max-width: 145px;
    padding: 7px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    outline: none;
    cursor: pointer;
    color: white;
    background: #12345c;
    border: 1px solid #315d8d;
}

.task-status-select.pending {
    color: #ffd18b;
    background: #8a4b102b;
    border-color: #d9770670;
}

.task-status-select.in-progress {
    color: #83caff;
    background: #0755a52b;
    border-color: #2586df70;
}

.task-status-select.completed {
    color: #7cf5bd;
    background: #0478572b;
    border-color: #10b98170;
}

.task-status-select option {
    color: white;
    background: #071b38;
}

/* DELETE */

.task-delete-btn {
    width: 34px;
    height: 34px;
    color: #ff9ca8;
    background: #e11d4825;
    border: 1px solid #e11d4855;
    border-radius: 9px;
    cursor: pointer;
    transition: .25s;
}

.task-delete-btn:hover {
    color: white;
    background: #dc2626;
    transform: translateY(-3px);
}

/* EMPTY */

.task-empty {
    text-align: center;
    padding: 45px 15px !important;
}

.task-empty div {
    font-size: 35px;
    margin-bottom: 10px;
}

.task-empty strong {
    display: block;
    color: white;
    font-size: 14px;
}

.task-empty p {
    color: #91afd3;
    margin-top: 7px;
    font-size: 11px;
}

.no-date {
    color: #819ab8;
}

/* MESSAGES */

.task-message {
    padding: 14px 17px;
    margin-bottom: 18px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    animation: tasksAppear .3s ease;
}

.task-message.success {
    color: #7cf5bd;
    background: #05966920;
    border: 1px solid #10b98170;
}

.task-message.error {
    color: #ff9caa;
    background: #e11d4820;
    border: 1px solid #e11d4870;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .task-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 750px) {
    .tasks-heading {
        padding: 22px;
    }

    .tasks-heading h2 {
        font-size: 23px;
    }

    .task-form-panel,
    .task-list-panel {
        padding: 18px;
    }
}

@media (max-width: 500px) {
    .task-stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .task-stat-card {
        padding: 13px;
        gap: 9px;
    }

    .task-stat-icon {
        width: 38px;
        height: 38px;
        font-size: 19px;
    }

    .task-stat-card h3 {
        font-size: 22px;
    }

    .task-form-grid {
        grid-template-columns: 1fr;
    }

    .task-field-wide {
        grid-column: auto;
    }

    .task-progress-badge {
        width: 68px;
        height: 68px;
    }

    .task-progress-badge strong {
        font-size: 18px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .tasks-page,
    .task-stat-card {
        animation: none !important;
        transition: none !important;
    }
}

/* ARABIC RTL SUPPORT */

.tasks-rtl {
    direction: rtl;
    text-align: right;
}

.tasks-rtl .tasks-heading,
.tasks-rtl .task-stat-card,
.tasks-rtl .task-section-heading {
    direction: rtl;
}

.tasks-rtl .tasks-label {
    letter-spacing: 0;
}

.tasks-rtl .task-table th,
.tasks-rtl .task-table td {
    text-align: right;
}

.tasks-rtl .task-table {
    direction: rtl;
}

.tasks-rtl .task-name small {
    text-align: right;
}

.tasks-rtl .task-form input,
.tasks-rtl .task-form select,
.tasks-rtl .task-form textarea {
    text-align: right;
}

.tasks-rtl .task-form input[type="date"] {
    direction: ltr;
    text-align: right;
}

.tasks-rtl .task-message {
    text-align: right;
}

.tasks-rtl .task-stat-card {
    text-align: right;
}

.tasks-rtl .task-status-select {
    direction: rtl;
}

.tasks-rtl .task-total-badge {
    direction: rtl;
}

</style>

<?php
$stmt->close();
?>