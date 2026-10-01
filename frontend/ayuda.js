document.addEventListener('DOMContentLoaded', () => {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const CONTACTO_URL = new URL('../backend/api/contacto.php', window.location.href).toString();
    const CHATBOT_URL = new URL('../backend/api/chatbot.php', window.location.href).toString();

    const contactoTab = document.getElementById('contactoTab');
    const consultasTab = document.getElementById('consultasTab');
    const contactoPanel = document.getElementById('contactoPanel');
    const consultasPanel = document.getElementById('consultasPanel');
    const contactoForm = document.getElementById('contactoForm');
    const contactoStatus = document.getElementById('contactoStatus');
    const contactoEnviarBtn = document.getElementById('contactoEnviarBtn');
    const chatForm = document.getElementById('chatForm');
    const chatPregunta = document.getElementById('chatPregunta');
    const chatEnviarBtn = document.getElementById('chatEnviarBtn');
    const chatMessages = document.getElementById('chatMessages');
    const userPanel = document.getElementById('ayudaUserPanel');

    const fetchJson = async (url, init = {}) => {
        const response = await fetch(url, { credentials: 'include', ...init });
        const text = await response.text();
        let payload;

        try {
            payload = text ? JSON.parse(text) : {};
        } catch (error) {
            throw new Error('El servidor devolvió una respuesta no válida.');
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message || 'No se pudo completar la solicitud.');
        }

        return payload;
    };

    const mostrarPanel = (tipo) => {
        const mostrarContacto = tipo === 'contacto';
        contactoPanel.hidden = !mostrarContacto;
        consultasPanel.hidden = mostrarContacto;
        contactoTab.classList.toggle('active', mostrarContacto);
        consultasTab.classList.toggle('active', !mostrarContacto);
        contactoTab.setAttribute('aria-selected', String(mostrarContacto));
        consultasTab.setAttribute('aria-selected', String(!mostrarContacto));
        (mostrarContacto ? document.getElementById('contactoEmail') : chatPregunta)?.focus();
    };

    contactoTab.addEventListener('click', () => mostrarPanel('contacto'));
    consultasTab.addEventListener('click', () => mostrarPanel('consultas'));

    contactoForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
            email: document.getElementById('contactoEmail').value.trim(),
            consulta: document.getElementById('contactoConsulta').value.trim(),
            website: document.getElementById('contactoWebsite').value
        };

        contactoEnviarBtn.disabled = true;
        contactoEnviarBtn.textContent = 'Enviando...';
        contactoStatus.className = 'ayuda-status';
        contactoStatus.textContent = 'Enviando tu consulta...';

        try {
            const result = await fetchJson(CONTACTO_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            contactoStatus.textContent = result.message || 'Tu consulta fue enviada correctamente.';
            contactoForm.reset();
        } catch (error) {
            contactoStatus.className = 'ayuda-status error';
            contactoStatus.textContent = error.message;
        } finally {
            contactoEnviarBtn.disabled = false;
            contactoEnviarBtn.textContent = 'Enviar';
        }
    });

    const agregarMensaje = (texto, autor) => {
        const mensaje = document.createElement('div');
        mensaje.className = `chat-message ${autor}`;
        mensaje.textContent = texto;
        chatMessages.appendChild(mensaje);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return mensaje;
    };

    chatForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const pregunta = chatPregunta.value.trim();
        if (!pregunta) return;

        agregarMensaje(pregunta, 'user');
        chatPregunta.value = '';
        chatEnviarBtn.disabled = true;
        chatEnviarBtn.textContent = 'Pensando...';
        const espera = agregarMensaje('Estoy revisando tu consulta...', 'bot');

        try {
            const result = await fetchJson(CHATBOT_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pregunta })
            });
            espera.textContent = result.data?.respuesta || result.message || 'No pude generar una respuesta.';
        } catch (error) {
            espera.textContent = error.message;
        } finally {
            chatEnviarBtn.disabled = false;
            chatEnviarBtn.textContent = 'Enviar';
            chatPregunta.focus();
        }
    });

    const cargarUsuario = async () => {
        try {
            const result = await fetchJson(AUTH_URL);
            const user = result.data?.user;
            if (!user) return;

            const iniciales = `${String(user.nombre || '').charAt(0)}${String(user.apellido || '').charAt(0)}`.toUpperCase();
            userPanel.innerHTML = `
                <div class="avatar"></div>
                <div class="user-details"><strong></strong><p></p></div>
            `;
            userPanel.querySelector('.avatar').textContent = iniciales || '?';
            userPanel.querySelector('.user-details strong').textContent = `${user.nombre || ''} ${user.apellido || ''}`.trim();
            userPanel.querySelector('.user-details p').textContent = user.role || 'Usuario';
        } catch (error) {
            console.error('No se pudo cargar la sesión en Ayuda.', error);
        }
    };

    cargarUsuario();
});
