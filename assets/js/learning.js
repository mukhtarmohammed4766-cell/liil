document.addEventListener("submit", async function (event) {

    const form = event.target.closest("[data-learning-form]");

    if (!form) return;

    event.preventDefault();

    const submitButton = form.querySelector('button[type="submit"]');

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.dataset.originalText = submitButton.textContent;
        submitButton.textContent = "Please wait...";
    }

    try {

        const formData = new FormData(form);

        const response = await fetch("pages/learning.php", {
            method: "POST",
            body: formData
        });

        if (!response.ok) {
            throw new Error("Unable to submit learning form.");
        }

        const html = await response.text();

        const content = document.getElementById("content");

        if (!content) {
            throw new Error("Dashboard content area was not found.");
        }

        content.innerHTML = html;

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

    } catch (error) {

        alert("Something went wrong. Please try again.");
        console.error(error);

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent =
                submitButton.dataset.originalText || "Submit";
        }

    }

});