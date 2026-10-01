(function () {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const NOTIFICATIONS_URL = new URL('../backend/api/notificaciones.php', window.location.href).toString();

    const normalizeRole = (roleValue) => {
        const value = String(roleValue || '').trim().toLowerCase();
        const aliases = {
            admin: 'administrador municipal',
            administrador: 'administrador municipal',
            'administrador municipal': 'administrador municipal',
            cuadrilla: 'cuadrilla de recolección',
            'cuadrilla de recoleccion': 'cuadrilla de recolección',
            'cuadrilla de recolección': 'cuadrilla de recolección',
            'miembro de cuadrilla': 'cuadrilla de recolección',
            operario: 'operario de centro',
            'operario de centro': 'operario de centro',
            'operario de instalacion': 'operario de centro',
            'operario de instalación': 'operario de centro',
            'centro de acopio': 'operario de centro',
            'miembro de centro de acopio': 'operario de centro',
            vecino: 'vecino',
            visitante: 'visitante',
            guest: 'visitante'
        };

        return aliases[value] || 'visitante';
    };

    const staffRoles = new Set([
        'administrador municipal',
        'cuadrilla de recolección',
        'operario de centro'
    ]);

    const link = (href, label, active = false, extraClass = '') =>
        `<a href="${href}" class="${active ? 'active ' : ''}${extraClass}">${label}</a>`;

    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    };

    const cerrarSesion = async () => {
        try {
            await fetch(new URL('../backend/api/auth.php?action=logout', window.location.href).toString(), {
                method: 'POST',
                credentials: 'include',
                headers: { 'X-CSRF-Token': localStorage.getItem('cerberoCsrfToken') || '' }
            });
        } catch (error) {
            console.error('No se pudo cerrar la sesión en el servidor.', error);
        }

        localStorage.removeItem('cerberoUser');
        localStorage.removeItem('cerberoCsrfToken');
        window.location.href = 'index.html';
    };

    const mostrarAccionesVisitante = (panel) => {
        if (!panel) return;
        panel.innerHTML = `
            <div class="auth-actions">
                <a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a>
                <a class="auth-btn secondary" href="login.html?mode=register">Registrarse</a>
            </div>
        `;
    };

    const prepararMarcaLateral = () => {
        const marca = document.querySelector('.sidebar-brand');
        if (!marca || marca.querySelector('.sidebar-brand-logo')) return;
        const logo = document.createElement('img');
        logo.className = 'sidebar-brand-logo';
        logo.src = 'img/Logo SiGeRu Cerbero.png';
        logo.alt = 'Logo de Cerbero';
        logo.width = 48;
        logo.height = 48;
        marca.appendChild(logo);
    };

    const completarPanelUsuario = (panel, sesionActiva) => {
        if (!panel) return;
        if (!sesionActiva) {
            const acciones = panel.querySelector(':scope > .auth-actions');
            const panelVisitanteCompleto = acciones
                && acciones.querySelector('a[href*="mode=login"]')
                && acciones.querySelector('a[href*="mode=register"]')
                && !acciones.querySelector('.logout-btn');
            if (!panelVisitanteCompleto) {
                mostrarAccionesVisitante(panel);
            }
            return;
        }
        panel.querySelectorAll('a[href*="mode=login"], a[href*="mode=register"]').forEach((link) => link.remove());
        const authActions = panel.querySelector('.auth-actions');
        if (authActions && !authActions.children.length) authActions.remove();
        if (panel.querySelector('.logout-btn')) return;

        const acciones = document.createElement('div');
        acciones.className = 'auth-actions';
        acciones.innerHTML = '<button class="ghost-btn logout-btn" type="button" data-sidebar-logout>Cerrar sesión</button>';
        panel.appendChild(acciones);
    };

    const prepararCuentaLateral = (sesionActiva = false) => {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        let footer = sidebar.querySelector('.sidebar-account');
        if (!footer) {
            footer = sidebar.querySelector('.empresa') || document.createElement('div');
            footer.className = 'sidebar-account';
            footer.replaceChildren();
            if (!footer.isConnected) sidebar.appendChild(footer);
        }

        const panel = document.querySelector('.contenido .user-panel') || footer.querySelector('.user-panel');
        if (!panel) return;

        if (panel.parentElement !== footer) footer.replaceChildren(panel);
        footer.dataset.sessionActive = sesionActiva ? 'true' : 'false';
        completarPanelUsuario(panel, sesionActiva);

        if (!panel.dataset.sidebarObserved) {
            panel.dataset.sidebarObserved = 'true';
            new MutationObserver(() => {
                completarPanelUsuario(panel, footer.dataset.sessionActive === 'true');
            }).observe(panel, { childList: true, subtree: true });
        }

        if (!footer.dataset.logoutBound) {
            footer.dataset.logoutBound = 'true';
            footer.addEventListener('click', (event) => {
                const boton = event.target.closest('[data-sidebar-logout]');
                if (!boton) return;
                boton.disabled = true;
                boton.textContent = 'Cerrando...';
                cerrarSesion();
            });
        }
    };

    const prepararNavegacionSuperior = () => {
        const nav = document.getElementById('sidebarNav');
        const contenido = document.querySelector('.contenido');
        if (!nav || !contenido) return;

        let barra = contenido.querySelector('.top-navigation-shell');
        if (!barra) {
            barra = document.createElement('div');
            barra.className = 'top-navigation-shell';
            contenido.prepend(barra);
        }

        if (nav.parentElement !== barra) barra.appendChild(nav);
    };

    const prepararNotificaciones = async (sesionActiva = false) => {
        const contenido = document.querySelector('.contenido');
        if (!contenido) return;

        let panel = contenido.querySelector('.notification-shell');
        if (!panel) {
            panel = document.createElement('div');
            panel.className = 'notification-shell';
            panel.innerHTML = `
                <button class="notification-bell" type="button" aria-label="Abrir notificaciones" aria-expanded="false">
                    <span class="notification-bell-icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="img"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"></path><path d="M10 21h4"></path></svg></span><b class="notification-count" hidden>0</b>
                </button>
                <section class="notification-popover" hidden aria-label="Notificaciones">
                    <div class="notification-heading"><strong>Notificaciones</strong><button type="button" class="notification-read-btn">Marcar leídas</button></div>
                    <div class="notification-list"><p class="notification-empty">Cargando...</p></div>
                </section>
            `;
            contenido.appendChild(panel);
        }

        const bell = panel.querySelector('.notification-bell');
        const popover = panel.querySelector('.notification-popover');
        const list = panel.querySelector('.notification-list');
        const count = panel.querySelector('.notification-count');
        const readButton = panel.querySelector('.notification-read-btn');
        panel.dataset.sessionActive = sesionActiva ? 'true' : 'false';

        if (!panel.dataset.bound) {
            panel.dataset.bound = 'true';
            bell.addEventListener('click', async () => {
                const isOpen = !popover.hidden;
                popover.hidden = isOpen;
                bell.setAttribute('aria-expanded', String(!isOpen));
                if (!isOpen && panel.dataset.sessionActive === 'true' && Number(count.textContent || 0) > 0) {
                    try {
                        const response = await fetch(NOTIFICATIONS_URL, {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'X-CSRF-Token': localStorage.getItem('cerberoCsrfToken') || '' }
                        });
                        const payload = await response.json();
                        if (payload?.success) {
                            count.hidden = true;
                            count.textContent = '0';
                            list.querySelectorAll('.notification-item.unread').forEach((item) => item.classList.remove('unread'));
                        }
                    } catch (error) {
                        console.error('No se pudieron marcar las notificaciones.', error);
                    }
                }
            });

            readButton.addEventListener('click', async () => {
                if (panel.dataset.sessionActive !== 'true') return;
                try {
                    const response = await fetch(NOTIFICATIONS_URL, {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'X-CSRF-Token': localStorage.getItem('cerberoCsrfToken') || '' }
                    });
                    const payload = await response.json();
                    if (payload?.success) {
                        count.hidden = true;
                        count.textContent = '0';
                        readButton.hidden = true;
                        list.querySelectorAll('.notification-item.unread').forEach((item) => item.classList.remove('unread'));
                    }
                } catch (error) {
                    console.error('No se pudieron marcar las notificaciones.', error);
                }
            });
        }

        try {
            const response = await fetch(NOTIFICATIONS_URL, { credentials: 'include' });
            const payload = await response.json();
            const data = payload?.data || {};
            const notifications = Array.isArray(data.notificaciones) ? data.notificaciones : [];
            const unread = Math.max(0, Number(data.no_leidas || 0));
            count.textContent = unread > 99 ? '99+' : String(unread);
            count.hidden = unread === 0;
            readButton.hidden = !sesionActiva || unread === 0;
            list.innerHTML = notifications.length
                ? notifications.map((item) => `
                    <article class="notification-item ${String(item.estado || '').toLowerCase() !== 'leída' ? 'unread' : ''}">
                        <strong>${escapeHtml(item.titulo)}</strong>
                        <p>${escapeHtml(item.mensaje)}</p>
                        ${item.fecha_hora ? `<small>${escapeHtml(item.fecha_hora)}</small>` : ''}
                    </article>`).join('')
                : '<p class="notification-empty">No tenés notificaciones por el momento.</p>';
        } catch (error) {
            count.hidden = true;
            readButton.hidden = true;
            list.innerHTML = '<p class="notification-empty">No se pudieron cargar las notificaciones.</p>';
            console.error('No se pudieron cargar las notificaciones.', error);
        }
    };

    const renderNavigation = (roleValue) => {
        const nav = document.getElementById('sidebarNav');
        if (!nav) return;

        const role = normalizeRole(roleValue);
        const currentPage = nav.dataset.currentPage || 'principal';

        if (!staffRoles.has(role)) {
            nav.innerHTML = [
                link('index.html', 'Principal', currentPage === 'principal'),
                link('incidencias.html', 'Incidencias', currentPage === 'incidencias'),
                link('ayuda.html', 'Ayuda', currentPage === 'ayuda')
            ].join('');
            return;
        }

        nav.innerHTML = [
            link('index.html', 'Principal', currentPage === 'principal'),
            link('usuarios.html', 'Usuarios', currentPage === 'usuarios'),
            ['administrador municipal', 'cuadrilla de recolección', 'operario de centro'].includes(role) ? link('incidencias-admin.html', 'Incidencias', currentPage === 'incidencias-admin') : '',
            role === 'administrador municipal' ? link('rutas.html', 'Rutas', currentPage === 'rutas') : '',
            role === 'administrador municipal' ? link('generador-rutas.html', 'Generador de rutas', currentPage === 'generador-rutas') : '',
            role === 'administrador municipal' || role === 'cuadrilla de recolección' ? link('recolecciones.html', 'Recolecciones', currentPage === 'recolecciones') : '',
            role === 'administrador municipal' ? link('reportes.html', 'Reportes', currentPage === 'reportes') : '',
            link('cuadrillas.html', 'Cuadrillas', currentPage === 'cuadrillas'),
            link('camiones.html', 'Camiones', currentPage === 'camiones'),
            link('contenedores.html', 'Contenedores', currentPage === 'contenedores'),
            link('centros-acopio.html', 'Centros de Acopio', currentPage === 'instalaciones'),
            link('maquinaria.html', 'Maquinaria', currentPage === 'maquinaria'),
            role === 'administrador municipal' ? '' : link('ayuda.html', 'Ayuda', currentPage === 'ayuda')
        ].filter(Boolean).join('');
    };

    const loadNavigation = async () => {
        prepararMarcaLateral();
        prepararCuentaLateral(false);
        prepararNavegacionSuperior();
        renderNavigation('visitante');
        prepararNotificaciones(false);

        try {
            const response = await fetch(AUTH_URL, { credentials: 'include' });
            const payload = await response.json();
            if (payload?.data?.csrf_token) localStorage.setItem('cerberoCsrfToken', payload.data.csrf_token);
            const user = payload?.data?.user || null;
            prepararCuentaLateral(Boolean(user));
            renderNavigation(payload?.data?.role || user?.role || 'visitante');
            prepararNotificaciones(Boolean(user));
        } catch (error) {
            console.error('No se pudo cargar el menú según la sesión.', error);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadNavigation);
    } else {
        loadNavigation();
    }

    window.addEventListener('hashchange', loadNavigation);
})();
