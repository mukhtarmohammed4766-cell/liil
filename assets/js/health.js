/* =========================================
   LIFEOS - HEALTH PAGE
========================================= */

function showHealthForm() {
    const form = document.getElementById('healthForm');

    if (!form) return;

    form.classList.add('show');

    form.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
}

function hideHealthForm() {
    const form = document.getElementById('healthForm');

    if (!form) return;

    form.classList.remove('show');
}

/*
 * Event delegation works with pages loaded using fetch().
 */
document.addEventListener('submit', async function (event) {

    const form = event.target;

    if (!form.matches('.health-page .health-form')) {
        return;
    }

    if (form.classList.contains('health-delete-form')) {

        const confirmed = confirm(
            'Are you sure you want to delete this health record?'
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

    submitButton.disabled = true;

    try {

        const response = await fetch('pages/health.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Request failed.');
        }

        const html = await response.text();

        const content = document.getElementById('content');

        if (content) {
            content.innerHTML = html;
        }

    } catch (error) {

        console.error('Health Error:', error);

        alert('Something went wrong. Please try again.');

        submitButton.disabled = false;
    }
});