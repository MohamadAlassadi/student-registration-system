(() => {
    const apiHost = window.location.hostname || 'localhost';
    const projectBaseUrl = new URL(
        `http://${apiHost}:8081/student-registration-system/`
    );
    const authenticationApiUrl = new URL('api/User_Authentication/', projectBaseUrl);

    window.StudentApp = {
        projectBaseUrl,
        authenticationApiUrl
    };

    async function loadSession() {
        const sessionUrl = new URL('session.php', authenticationApiUrl);
        const response = await fetch(sessionUrl, { credentials: 'include' });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || `Session check failed (HTTP ${response.status}).`);
        }

        return result;
    }

    function showSessionError(error) {
        document.documentElement.classList.remove('auth-pending');
        const message = document.getElementById('page-message');

        if (message) {
            message.textContent = `Could not verify your login: ${error.message}`;
            message.classList.add('alert', 'alert-danger');
        }
    }

    function configureHome(session) {
        document.querySelectorAll('[data-authenticated-only]').forEach((element) => {
            element.hidden = !session.authenticated;
        });
        document.querySelectorAll('[data-guest-only]').forEach((element) => {
            element.hidden = session.authenticated;
        });

        const accountStatus = document.getElementById('account-status');
        if (accountStatus && session.authenticated) {
            accountStatus.textContent = `Signed in as ${session.user.username}`;
        }

        const logoutButton = document.getElementById('logout-button');
        if (logoutButton && session.authenticated) {
            logoutButton.hidden = false;
            logoutButton.addEventListener('click', async () => {
                logoutButton.disabled = true;
                try {
                    const logoutUrl = new URL('logout.php', authenticationApiUrl);
                    const response = await fetch(logoutUrl, {
                        method: 'POST',
                        credentials: 'include'
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || `Logout failed (HTTP ${response.status}).`);
                    }
                    window.location.assign(new URL('pages/index.html', projectBaseUrl));
                } catch (error) {
                    logoutButton.disabled = false;
                    const accountMessage = document.getElementById('account-message');
                    if (accountMessage) {
                        accountMessage.textContent = error.message;
                        accountMessage.classList.add('alert', 'alert-danger');
                    }
                }
            });
        }
    }

    async function initialize() {
        try {
            const session = await loadSession();
            configureHome(session);

            if (
                document.body.dataset.requiresAuth === 'true'
                && !session.authenticated
            ) {
                const loginUrl = new URL('pages/login.html?required=1', projectBaseUrl);
                window.location.replace(loginUrl);
                return;
            }

            document.documentElement.classList.remove('auth-pending');
        } catch (error) {
            showSessionError(error);
        }
    }

    initialize();
})();
