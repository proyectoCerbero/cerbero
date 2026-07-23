document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const loginSubmitBtn = document.getElementById('loginSubmitBtn');
    const registerSubmitBtn = document.getElementById('registerSubmitBtn');
    let loginSubmitting = false;
    let registerSubmitting = false;

    const saveSession = (user) => {
        localStorage.setItem('cerberoUser', JSON.stringify({
            ...user,
            role: user.role || 'vecino'
        }));
    };

    const API_BASE_URL = new URL('../backend/api/auth.php', window.location.href).toString();

    const fetchJson = async (url, init) => {
        console.debug('auth fetch', url, init);
        const response = await fetch(url, init);
        const text = await response.text();

        let payload = null;
        try {
            payload = text ? JSON.parse(text) : null;
        } catch (error) {
            console.error('auth JSON parse error', text);
            throw new Error(`Respuesta no válida del servidor (${response.status}): ${text}`);
        }

        if (!response.ok) {
            console.error('auth response error', response.status, payload);
            throw new Error(payload?.message || `Servidor respondió ${response.status}`);
        }

        return payload;
    };

    const submitLogin = async (attempt = 1) => {
        if (!loginForm || loginSubmitting) return;

        const emailInput = document.getElementById('loginEmail');
        const passwordInput = document.getElementById('loginPassword');
        const payload = {
            email: emailInput?.value.trim() || '',
            password: passwordInput?.value || ''
        };

        const statusBox = document.getElementById('loginStatus');

        if (!payload.email || !payload.password) {
            if (attempt <= 4) {
                statusBox.textContent = 'Esperando que el navegador complete tus datos...';
                statusBox.style.color = '#666';
                setTimeout(() => submitLogin(attempt + 1), 400);
                return;
            }

            statusBox.textContent = 'Completá correo y contraseña.';
            statusBox.style.color = 'crimson';
            return;
        }

        loginSubmitting = true;
        statusBox.textContent = 'Verificando...';
        statusBox.style.color = '#666';

        try {
            const result = await fetchJson(`${API_BASE_URL}?action=login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            statusBox.textContent = result.message || 'Ocurrió un error.';
            statusBox.style.color = result.success ? 'green' : 'crimson';

            if (result.success) {
                const user = result.data?.user || {};
                saveSession(user);
                window.location.href = 'index.html';
            }
        } catch (error) {
            statusBox.textContent = error.message || 'No se pudo conectar con el servidor.';
            statusBox.style.color = 'crimson';
        } finally {
            loginSubmitting = false;
        }
    };

    const submitRegister = async (retry = false) => {
        if (!registerForm || registerSubmitting) return;

        const payload = {
            nombre: document.getElementById('registerNombre').value.trim(),
            apellido: document.getElementById('registerApellido').value.trim(),
            cedula: document.getElementById('registerCedula').value.trim(),
            email: document.getElementById('registerEmail').value.trim(),
            password: document.getElementById('registerPassword').value
        };

        const statusBox = document.getElementById('registerStatus');

        if ((!payload.nombre || !payload.apellido || !payload.cedula || !payload.email || !payload.password) && !retry) {
            statusBox.textContent = 'Esperando que el navegador complete tus datos...';
            statusBox.style.color = '#666';
            setTimeout(() => submitRegister(true), 250);
            return;
        }

        if (!payload.nombre || !payload.apellido || !payload.cedula || !payload.email || !payload.password) {
            statusBox.textContent = 'Completá todos los campos.';
            statusBox.style.color = 'crimson';
            return;
        }

        registerSubmitting = true;
        statusBox.textContent = 'Registrando...';
        statusBox.style.color = '#666';

        try {
            const result = await fetchJson(`${API_BASE_URL}?action=register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            statusBox.textContent = result.message || 'Ocurrió un error.';
            statusBox.style.color = result.success ? 'green' : 'crimson';

            if (result.success) {
                const user = result.data?.user || {};
                saveSession(user);
                window.location.href = 'index.html';
            }
        } catch (error) {
            statusBox.textContent = error.message || 'No se pudo conectar con el servidor.';
            statusBox.style.color = 'crimson';
        } finally {
            registerSubmitting = false;
        }
    };

    if (loginForm) {
        loginForm.addEventListener('submit', (event) => {
            event.preventDefault();
            event.stopPropagation();
            submitLogin();
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', (event) => {
            event.preventDefault();
            event.stopPropagation();
            submitRegister();
        });
    }
});
