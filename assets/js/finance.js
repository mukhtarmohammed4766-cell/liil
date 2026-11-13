document.addEventListener("submit", async function (event) {
    const form = event.target.closest("[data-finance-form]");

    if (!form) return;

    event.preventDefault();

    const button = form.querySelector('button[type="submit"]');

    if (button) {
        button.disabled = true;
    }

    try {
        const formData = new FormData(form);

        const response = await fetch("pages/finance.php", {
            method: "POST",
            body: formData
        });

        if (!response.ok) {
            throw new Error("Could not save the transaction.");
        }

        const html = await response.text();
        document.getElementById("content").innerHTML = html;

    } catch (error) {
        alert(error.message);
        if (button) button.disabled = false;
    }
});