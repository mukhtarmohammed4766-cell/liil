document.addEventListener("DOMContentLoaded", () => {
    const wrapper = document.querySelector(".notification-wrapper");
    const button = document.getElementById("notificationButton");
    const dropdown = document.getElementById("notificationDropdown");
    const countElement = document.getElementById("notificationCount");
    const list = document.getElementById("notificationList");
    const markAllButton = document.getElementById("markAllNotificationsRead");

    if (!wrapper || !button || !dropdown || !list) return;

    const apiUrl = "api/notifications.php";

    const isArabic = () => document.documentElement.lang === "ar";

    const text = (ar, en) => isArabic() ? ar : en;

    if (wrapper.dataset.notificationsEnabled !== "1") {
        wrapper.hidden = true;
        return;
    }

    function escapeHTML(value) {
        const element = document.createElement("div");
        element.textContent = value ?? "";
        return element.innerHTML;
    }

    async function request(action = null, id = null) {
        const options = {
            method: action ? "POST" : "GET",
            headers: {}
        };

        if (action) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify({ action, id });
        }

        const response = await fetch(apiUrl, options);

        if (!response.ok) {
            throw new Error("Notification request failed");
        }

        return await response.json();
    }

    function updateCount(number) {
        countElement.textContent = number > 99 ? "99+" : number;
        countElement.hidden = number <= 0;
    }

    function renderNotifications(data) {
        updateCount(data.unread);

        if (!data.notifications.length) {
            list.innerHTML = `
                <div class="notification-empty">
                    ${text("لا توجد إشعارات حالياً", "No notifications yet")}
                </div>
            `;
            return;
        }

        list.innerHTML = data.notifications.map(item => {
            const id = Number(item.id);
            const title = escapeHTML(item.title);
            const message = escapeHTML(item.message);
            const date = escapeHTML(item.created_at);
            const link = item.link ? escapeHTML(item.link) : "";
            const unreadClass = item.is_read ? "" : " unread";

            return `
                <div class="notification-item${unreadClass}"
                     data-id="${id}">

                    <div class="notification-item-content">
                        <strong>${title}</strong>
                        <p>${message}</p>
                        <small>${date}</small>
                    </div>

                    <div class="notification-item-actions">
                        ${!item.is_read ? `
                            <button type="button"
                                    class="notification-read"
                                    data-action="read"
                                    data-id="${id}"
                                    title="${text("تعليم كمقروء", "Mark as read")}">
                                ✓
                            </button>
                        ` : ""}

                        <button type="button"
                                class="notification-delete"
                                data-action="delete"
                                data-id="${id}"
                                title="${text("حذف", "Delete")}">
                            ×
                        </button>
                    </div>

                </div>
            `;
        }).join("");

        list.querySelectorAll(".notification-item").forEach(item => {
            const original = data.notifications.find(
                notification => Number(notification.id) === Number(item.dataset.id)
            );

            if (original?.link) {
                item.style.cursor = "pointer";

                item.addEventListener("click", event => {
                    if (event.target.closest("button")) return;

                    window.location.href = original.link;
                });
            }
        });
    }

    async function loadNotifications() {
        try {
            const data = await request();

            if (!data.enabled) {
                wrapper.hidden = true;
                return;
            }

            renderNotifications(data);
        } catch (error) {
            console.error("Notifications:", error);
        }
    }

    button.addEventListener("click", async () => {
        const isOpen = !dropdown.hidden;

        dropdown.hidden = isOpen;
        button.setAttribute("aria-expanded", String(!isOpen));

        if (!isOpen) {
            await loadNotifications();
        }
    });

    document.addEventListener("click", event => {
        if (!wrapper.contains(event.target)) {
            dropdown.hidden = true;
            button.setAttribute("aria-expanded", "false");
        }
    });

    list.addEventListener("click", async event => {
        const actionButton = event.target.closest("[data-action]");

        if (!actionButton) return;

        const action = actionButton.dataset.action;
        const id = Number(actionButton.dataset.id);

        try {
            await request(action, id);
            await loadNotifications();
        } catch (error) {
            console.error(error);
            alert(text("تعذّر تحديث الإشعار", "Could not update notification"));
        }
    });

    markAllButton.addEventListener("click", async () => {
        try {
            await request("read_all");
            await loadNotifications();
        } catch (error) {
            console.error(error);
        }
    });

    loadNotifications();

    // Refresh unread count periodically.
    setInterval(loadNotifications, 30000);
});