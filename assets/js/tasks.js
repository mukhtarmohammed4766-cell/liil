window.submitTaskForm = async function(form) {
    const formData = new FormData(form);
    const button = form.querySelector('.task-add-btn');
    const language = document.documentElement.lang === 'ar' ? 'ar' : 'en';

    button.disabled = true;
    button.textContent = language === 'ar' ? 'جارٍ الحفظ...' : 'Saving...';

    try {
        const response = await fetch('pages/tasks.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(await response.text());
        }

        const html = await response.text();
        document.getElementById('content').innerHTML = html;

        if (window.applyLifeOSTranslations) {
            window.applyLifeOSTranslations(document.getElementById('content'));
        }

    } catch (error) {
        console.error(error);
        alert(
            language === 'ar'
                ? 'حدث خطأ أثناء حفظ المهمة: ' + error.message
                : 'Error saving task: ' + error.message
        );

        button.disabled = false;
        button.textContent = language === 'ar' ? '＋ إضافة مهمة' : '＋ Add Task';
    }

    return false;
};


window.updateTaskStatus = async function(select) {
    const language = document.documentElement.lang === 'ar' ? 'ar' : 'en';

    const data = new FormData();
    data.append('action', 'update_status');
    data.append('task_id', select.dataset.taskId);
    data.append('status', select.value);

    try {
        const response = await fetch('pages/tasks.php', {
            method: 'POST',
            body: data
        });

        if (!response.ok) {
            throw new Error('Update failed');
        }

        const html = await response.text();
        document.getElementById('content').innerHTML = html;

        if (window.applyLifeOSTranslations) {
            window.applyLifeOSTranslations(document.getElementById('content'));
        }

    } catch (error) {
        console.error(error);
        alert(
            language === 'ar'
                ? 'تعذّر تحديث حالة المهمة.'
                : 'Could not update task.'
        );
    }
};


window.deleteTask = async function(id) {
    const language = document.documentElement.lang === 'ar' ? 'ar' : 'en';

    const confirmation = language === 'ar'
        ? 'هل أنت متأكد أنك تريد حذف هذه المهمة؟'
        : 'Are you sure you want to delete this task?';

    if (!confirm(confirmation)) {
        return;
    }

    const data = new FormData();
    data.append('action', 'delete');
    data.append('task_id', id);

    try {
        const response = await fetch('pages/tasks.php', {
            method: 'POST',
            body: data
        });

        if (!response.ok) {
            throw new Error('Delete failed');
        }

        const html = await response.text();
        document.getElementById('content').innerHTML = html;

        if (window.applyLifeOSTranslations) {
            window.applyLifeOSTranslations(document.getElementById('content'));
        }

    } catch (error) {
        console.error(error);
        alert(
            language === 'ar'
                ? 'تعذّر حذف المهمة.'
                : 'Could not delete task.'
        );
    }
};