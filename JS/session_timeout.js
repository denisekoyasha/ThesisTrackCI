// Session timeout helper
// Shows a modal one minute before expected expiry and allows staying signed in via keep-alive ping
(function(){
    const DEFAULT_TIMEOUT_SECONDS = 1800; // should match server default
    const WARNING_SECONDS = 60; // show modal 60s before expiry
    const TTL_ENDPOINT = '../ajax/session_ttl.php';
    const KEEPALIVE_ENDPOINT = '../ajax/keep_alive.php';

    let lastActivity = Date.now();
    let timeoutSeconds = DEFAULT_TIMEOUT_SECONDS * 1000;
    let warningTimer = null;
    let pingInProgress = false;

    async function scheduleWarning(){
        clearTimeout(warningTimer);
        // Try to get accurate TTL from the server
        try {
            const r = await fetch(TTL_ENDPOINT, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (r.ok) {
                const j = await r.json();
                if (j && j.remaining_seconds != null) {
                    timeoutSeconds = (j.remaining_seconds + 0) * 1000; // remaining millis
                    // If server reports zero remaining seconds, force logout
                    if (j.remaining_seconds <= 0) {
                        // Redirect to logout which will clear any client state and show login
                        window.location.href = '../logout.php';
                        return;
                    }
                } else {
                    timeoutSeconds = DEFAULT_TIMEOUT_SECONDS * 1000;
                }
            } else {
                timeoutSeconds = DEFAULT_TIMEOUT_SECONDS * 1000;
            }
        } catch (e) {
            timeoutSeconds = DEFAULT_TIMEOUT_SECONDS * 1000; // fallback
        }

        const elapsed = Date.now() - lastActivity;
        const remaining = timeoutSeconds - elapsed;
        const warnAt = remaining - (WARNING_SECONDS * 1000);
        if (warnAt <= 0) {
            showWarningModal();
        } else {
            warningTimer = setTimeout(showWarningModal, warnAt);
        }
    }

    function showWarningModal(){
        if (document.getElementById('session-warning-modal')) return;
        const modal = document.createElement('div');
        modal.id = 'session-warning-modal';
        modal.innerHTML = `\n            <div class="st-modal-overlay"></div>\n            <div class="st-modal">\n                <h3>Session expiring soon</h3>\n                <p>Your session will expire in <span id="st-countdown">60</span> seconds due to inactivity.</p>\n                <div class="st-actions">\n                    <button id="st-keepalive" class="btn">Stay signed in</button>\n                    <button id="st-logout" class="btn btn-danger">Logout</button>\n                </div>\n            </div>\n        `;
        document.body.appendChild(modal);

        let count = WARNING_SECONDS;
        const countdownEl = document.getElementById('st-countdown');
        const interval = setInterval(async () => {
            count--;
            if (countdownEl) countdownEl.textContent = String(count);
            if (count <= 0) {
                clearInterval(interval);
                // When countdown reaches zero, hit TTL endpoint to confirm expiry and then force logout if needed
                try {
                    const r = await fetch(TTL_ENDPOINT, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
                    if (r.ok) {
                        const j = await r.json();
                        if (j && j.remaining_seconds != null && j.remaining_seconds <= 0) {
                            window.location.href = '../logout.php';
                            return;
                        }
                    }
                } catch (e) {
                    // If TTL check fails, still force a logout to avoid lingering session mismatch
                }
                // fallback: redirect to logout
                window.location.href = '../logout.php';
            }
        }, 1000);

        document.getElementById('st-keepalive').addEventListener('click', function(){
            keepAlive().then(success => {
                if (success) {
                    // remove modal and reschedule
                    closeModal();
                    lastActivity = Date.now();
                    scheduleWarning();
                }
            });
        });

        document.getElementById('st-logout').addEventListener('click', function(){
            window.location.href = '../logout.php';
        });
    }

    function closeModal(){
        const modal = document.getElementById('session-warning-modal');
        if (modal) modal.remove();
    }

    async function keepAlive(){
        if (pingInProgress) return false;
        pingInProgress = true;
        try {
            const res = await fetch(KEEPALIVE_ENDPOINT, {method: 'GET', credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
            pingInProgress = false;
            if (res.status === 401) {
                // Session expired server-side
                window.location.href = '../logout.php';
                return false;
            }
            if (res.ok) return true;
            return false;
        } catch (e){
            pingInProgress = false;
            return false;
        }
    }

    // Activity detection
    ['click','mousemove','keydown','scroll','touchstart'].forEach(ev => {
        window.addEventListener(ev, () => {
            lastActivity = Date.now();
            // optimistic keep-alive: schedule a light ping if we've been active
            // but avoid too many pings - the warning modal allows explicit keep-alive
            scheduleWarning();
        }, {passive: true});
    });

    // Initial schedule
    scheduleWarning();

    // Expose for debugging
    window.SessionTimeoutHelper = {
        scheduleWarning, showWarningModal, closeModal
    };
})();
