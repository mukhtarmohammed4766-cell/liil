document.addEventListener("submit", async function (event) {
    const form = event.target.closest("[data-settings-form]");

    if (!form) return;

    event.preventDefault();

    const saveButton = form.querySelector('[type="submit"]');
    const originalText = saveButton ? saveButton.textContent : "";

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = "Saving...";
    }

    const formData = new FormData(form);

    try {
        const response = await fetch("pages/settings.php", {
            method: "POST",
            body: formData
        });

        const result = await response.text();

        if (!response.ok) {
            console.error(result);
            alert("Could not save settings.");
            return;
        }

        const language = formData.get("language") || "English";
        const theme = formData.get("theme") || "dark";

        // Apply language only after successful save.
        if (window.applyLifeOSLanguage) {
            window.applyLifeOSLanguage(language);
        } else {
            const isArabic = language === "Arabic";
            document.documentElement.lang = isArabic ? "ar" : "en";
            document.documentElement.dir = isArabic ? "rtl" : "ltr";
        }

        // Apply theme.
        if (window.applyLifeOSTheme) {
            window.applyLifeOSTheme(theme);
        }

        // Apply compact layout.
        document.body.classList.toggle(
            "compact-layout",
            formData.has("compact_mode")
        );

        // Translate the whole page again.
        if (window.applyLifeOSTranslations) {
            window.applyLifeOSTranslations(document);
        }

        if (window.updateCurrentDate) {
            window.updateCurrentDate();
        }

        alert(
            language === "Arabic"
                ? "تم حفظ الإعدادات بنجاح!"
                : "Settings saved successfully!"
        );

    } catch (error) {
        console.error(error);
        alert("Something went wrong while saving settings.");
    } finally {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = originalText;
        }
    }
});