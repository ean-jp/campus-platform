"use strict";

document.addEventListener("DOMContentLoaded", function () {

    // Retrieve a CSRF token for the current session.
    async function getCsrfToken() {

        const response = await fetch("/api/v1/auth/csrf", {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        const result = await response.json();

        if (!response.ok ||
            !result.success ||
            !result.data?.csrf_token) {

            throw new Error("Unable to retrieve security token.");
        }

        return result.data.csrf_token;
    }

    // Redirect unauthenticated users to the login page.
    function redirectToLogin() {

        const next = window.location.pathname +
                     window.location.search;

        window.location.href =
            "/login.html?next=" + encodeURIComponent(next);
    }

    // Display feedback to the user.
    function showMessage(message, isError = false) {

        let messageBox = document.getElementById(
            "resource-message"
        );

        if (!messageBox) {

            messageBox = document.createElement("div");

            messageBox.id = "resource-message";

            messageBox.setAttribute("role", "status");

            document.body.prepend(messageBox);
        }

        messageBox.textContent = message;

        messageBox.style.padding = "15px";
        messageBox.style.margin = "15px";
        messageBox.style.borderRadius = "6px";

        messageBox.style.backgroundColor = isError
            ? "#f8d7da"
            : "#d1e7dd";

        messageBox.style.color = isError
            ? "#842029"
            : "#0f5132";

        messageBox.scrollIntoView({
            behavior: "smooth",
            block: "nearest"
        });
    }

    // Send a protected POST request.
    async function sendRequest(url) {

        const csrfToken = await getCsrfToken();

        const response = await fetch(url, {

            method: "POST",

            credentials: "same-origin",

            headers: {
                "Accept": "application/json",
                "X-CSRF-Token": csrfToken
            }
        });

        if (response.status === 401) {

            redirectToLogin();

            return null;
        }

        const result = await response.json();

        if (!response.ok || !result.success) {

            throw new Error(
                result.error || "The request could not be completed."
            );
        }

        return result.data;
    }

    // Book an appointment.
    async function bookAppointment(button) {

        const slotId = button.dataset.slotId;

        if (!slotId) {
            return;
        }

        const confirmed = window.confirm(
            "Are you sure you want to book this appointment?"
        );

        if (!confirmed) {
            return;
        }

        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = "Booking...";

        try {

            const result = await sendRequest(
                `/api/v1/resources/slots/${slotId}/book`
            );

            if (!result) {
                return;
            }

            showMessage(
                result.message ||
                "Appointment booked successfully."
            );

            button.textContent = "Booked";

            setTimeout(function () {

                window.location.href = "/resources/bookings";

            }, 1200);

        } catch (error) {

            showMessage(error.message, true);

            button.disabled = false;
            button.textContent = originalText;
        }
    }

    // Cancel an appointment.
    async function cancelAppointment(button) {

        const bookingId = button.dataset.bookingId;

        if (!bookingId) {
            return;
        }

        const confirmed = window.confirm(
            "Are you sure you want to cancel this appointment?"
        );

        if (!confirmed) {
            return;
        }

        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = "Cancelling...";

        try {

            const result = await sendRequest(
                `/api/v1/resources/bookings/${bookingId}/cancel`
            );

            if (!result) {
                return;
            }

            showMessage(
                result.message ||
                "Appointment cancelled successfully."
            );

            button.textContent = "Cancelled";

            setTimeout(function () {

                window.location.reload();

            }, 1200);

        } catch (error) {

            showMessage(error.message, true);

            button.disabled = false;
            button.textContent = originalText;
        }
    }

    // Connect booking buttons.
    document.querySelectorAll(
        "button[data-slot-id]"
    ).forEach(function (button) {

        button.addEventListener("click", function () {

            bookAppointment(button);
        });
    });

    // Connect cancellation buttons.
    document.querySelectorAll(
        "button[data-booking-id]"
    ).forEach(function (button) {

        button.addEventListener("click", function () {

            cancelAppointment(button);
        });
    });

});