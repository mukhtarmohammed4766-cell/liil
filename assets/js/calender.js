document.addEventListener("click", async function (event) {
    const button = event.target.closest("[data-calendar-month]");
    if (!button) return;

    const month = button.dataset.calendarMonth;

    try {
        const response = await fetch(
            "pages/calendar.php?month=" + encodeURIComponent(month)
        );

        const html = await response.text();

        if (!response.ok) {
            console.error("Calendar error:", html);
            alert("PHP Error: " + html.substring(0, 500));
            return;
        }

        document.getElementById("content").innerHTML = html;

    } catch (error) {
        console.error(error);
        alert("Connection error: " + error.message);
    }
});

document.addEventListener("submit", async function (event) {
    const form = event.target.closest("[data-calendar-form]");
    if (!form) return;

    event.preventDefault();

    const formData = new FormData(form);

    try {
        const response = await fetch("pages/calendar.php", {
            method: "POST",
            body: formData
        });

        const html = await response.text();

        if (!response.ok) {
            console.error("Calendar error:", html);
            alert("PHP Error: " + html.substring(0, 500));
            return;
        }

        document.getElementById("content").innerHTML = html;

    } catch (error) {
        console.error(error);
        alert("Connection error: " + error.message);
    }
});