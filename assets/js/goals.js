/* =========================================
   LIFEOS - GOALS PAGE
========================================= */


/**
 * Show the Add Goal form.
 */
function showGoalForm() {

    const form = document.getElementById('goalForm');

    if (!form) return;

    form.classList.add('show');

    form.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });

}


/**
 * Hide the Add Goal form.
 */
function hideGoalForm() {

    const form = document.getElementById('goalForm');

    if (!form) return;

    form.classList.remove('show');

}


/**
 * Handle all Goals form submissions.
 * Event delegation supports content loaded with fetch().
 */
document.addEventListener('submit', async function (event) {

    const form = event.target;

    // Only handle forms inside the Goals page.
    if (!form.matches('.goals-page form')) {
        return;
    }

    // Confirm before deleting a goal.
    if (form.classList.contains('goal-delete-form')) {

        const confirmed = confirm(
            'Are you sure you want to delete this goal?'
        );

        if (!confirmed) {
            event.preventDefault();
            return;
        }

    }

    event.preventDefault();

    const submitButton = event.submitter;

    if (!submitButton) return;

    const formData = new FormData(form);

    formData.set(submitButton.name, submitButton.value);

    // Prevent repeated submissions.
    submitButton.disabled = true;

    try {

        const response = await fetch('pages/goals.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('The request could not be completed.');
        }

        const html = await response.text();

        const content = document.getElementById('content');

        if (content) {
            content.innerHTML = html;
        }

    } catch (error) {

        console.error('Goals Error:', error);

        alert('Something went wrong. Please try again.');

        submitButton.disabled = false;

    }

});