(function () {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();

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

    const renderNavigation = (roleValue) => {
        const nav = document.getElementById('sidebarNav');
        if (!nav) return;

        const role = normalizeRole(roleValue);
        const currentPage = nav.dataset.currentPage || 'principal';

        if (!staffRoles.has(role)) {
            nav.innerHTML = [
                link('index.html', 'Principal', currentPage === 'principal'),
                link('incidencias.html', 'Incidencias', currentPage === 'incidencias')
            ].join('');
            return;
        }

        const paginasGestion = new Set([
            'usuarios', 'cuadrillas', 'camiones', 'contenedores', 'instalaciones', 'maquinaria',
            'incidencias-admin', 'rutas', 'generador-rutas'
        ]);
        const gestionActiva = paginasGestion.has(currentPage);
        nav.innerHTML = `
            ${link('index.html', 'Principal', currentPage === 'principal')}
            <details class="sidebar-menu-group" ${gestionActiva ? 'open' : ''}>
                <summary class="${gestionActiva ? 'active' : ''}">Gestión</summary>
                <div class="sidebar-submenu">
                    <div class="sidebar-submenu-item">${link('usuarios.html', 'Usuarios', currentPage === 'usuarios')}</div>
                    ${role === 'administrador municipal' ? `<div class="sidebar-submenu-item">${link('incidencias-admin.html', 'Incidencias', currentPage === 'incidencias-admin')}</div>` : ''}
                    ${role === 'administrador municipal' ? `<div class="sidebar-submenu-item">${link('rutas.html', 'Rutas', currentPage === 'rutas')}</div>` : ''}
                    ${role === 'administrador municipal' ? `<div class="sidebar-submenu-item">${link('generador-rutas.html', 'Generador de rutas', currentPage === 'generador-rutas')}</div>` : ''}
                    <div class="sidebar-submenu-item">${link('cuadrillas.html', 'Cuadrillas', currentPage === 'cuadrillas')}</div>
                    <div class="sidebar-submenu-item">${link('camiones.html', 'Camiones', currentPage === 'camiones')}</div>
                    <div class="sidebar-submenu-item">${link('contenedores.html', 'Contenedores', currentPage === 'contenedores')}</div>
                    <div class="sidebar-submenu-item">${link('centros-acopio.html', 'Centros de Acopio', currentPage === 'instalaciones')}</div>
                    <div class="sidebar-submenu-item">${link('maquinaria.html', 'Maquinaria', currentPage === 'maquinaria')}</div>
                </div>
            </details>
        `;
    };

    const loadNavigation = async () => {
        renderNavigation('visitante');

        try {
            const response = await fetch(AUTH_URL, { credentials: 'include' });
            const payload = await response.json();
            if (payload?.data?.csrf_token) localStorage.setItem('cerberoCsrfToken', payload.data.csrf_token);
            renderNavigation(payload?.data?.role || payload?.data?.user?.role || 'visitante');
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
