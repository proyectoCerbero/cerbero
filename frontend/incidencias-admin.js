document.addEventListener('DOMContentLoaded', () => {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const INCIDENCIAS_URL = new URL('../backend/api/incidencias.php', window.location.href).toString();
    const acceso = document.getElementById('adminIncidenciasAcceso');
    const modulo = document.getElementById('adminIncidenciasModulo');
    const userPanel = document.getElementById('adminIncidenciasUserPanel');
    const status = document.getElementById('adminIncidenciasStatus');
    const tablaBody = document.getElementById('adminIncidenciasTablaBody');
    const actualizarBtn = document.getElementById('actualizarAdminIncidenciasBtn');

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const isAdmin = (role) => ['admin', 'administrador', 'administrador municipal'].includes(String(role || '').trim().toLowerCase());
    const formatCode = (id) => `IN${String(Number(id) || 0).padStart(2, '0')}`;

    const fetchJson = async (url, init = {}) => {
        const options = { ...init, credentials: 'include' };
        const csrfToken = localStorage.getItem('cerberoCsrfToken');
        if (init.body) options.headers = {
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
            ...(init.headers || {})
        };
        const response = await fetch(url, options);
        const text = await response.text();
        let payload;
        try { payload = text ? JSON.parse(text) : null; }
        catch (error) { throw new Error('El servidor devolvió una respuesta inválida.'); }
        if (!response.ok || payload?.success === false) {
            throw new Error(payload?.message || `Error del servidor (${response.status}).`);
        }
        if (payload?.data?.csrf_token) localStorage.setItem('cerberoCsrfToken', payload.data.csrf_token);
        return payload;
    };

    const showStatus = (message, isError = false) => {
        status.textContent = message;
        status.style.color = isError ? '#b91c1c' : '#166534';
    };

    const photoMarkup = (photo, code) => {
        if (!photo) return '<span class="sin-foto">Sin foto</span>';
        const url = new URL(`../backend/${String(photo).replace(/^\/+/, '')}`, window.location.href).toString();
        return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener"><img class="incidencia-thumbnail" src="${escapeHtml(url)}" alt="Foto de la incidencia ${escapeHtml(code)}"></a>`;
    };

    const renderIncidencias = (items) => {
        const lista = Array.isArray(items) ? items : [];
        if (!lista.length) {
            tablaBody.innerHTML = '<tr><td colspan="6" class="tabla-vacia">No hay incidencias registradas.</td></tr>';
            return;
        }
        tablaBody.innerHTML = lista.map((item) => {
            const code = formatCode(item.id_incidencia);
            const solucionado = String(item.estado || '').toLowerCase() === 'solucionado';
            const vecino = `${item.nombre || ''} ${item.apellido || ''}`.trim() || item.ci || 'Sin identificar';
            return `<tr>
                <td><strong>${code}</strong></td>
                <td class="incidencia-vecino-cell"><strong>${escapeHtml(vecino)}</strong><small>${escapeHtml(item.email || item.ci || '')}</small></td>
                <td class="incidencia-description-cell">${escapeHtml(item.descripcion)}</td>
                <td>${escapeHtml(item.ubicacion || 'Sin ubicación')}</td>
                <td>${photoMarkup(item.foto, code)}</td>
                <td><select class="incidencia-status-select ${solucionado ? 'estado-select-solucionado' : 'estado-select-pendiente'}" data-id="${Number(item.id_incidencia)}" aria-label="Estado de ${code}">
                    <option value="a solucionar" ${solucionado ? '' : 'selected'}>A solucionar</option>
                    <option value="solucionado" ${solucionado ? 'selected' : ''}>Solucionado</option>
                </select></td>
            </tr>`;
        }).join('');
    };

    const loadIncidencias = async () => {
        tablaBody.innerHTML = '<tr><td colspan="6" class="tabla-vacia">Cargando incidencias...</td></tr>';
        try {
            const payload = await fetchJson(`${INCIDENCIAS_URL}?action=todas`);
            renderIncidencias(payload?.data?.incidencias || []);
        } catch (error) {
            tablaBody.innerHTML = `<tr><td colspan="6" class="tabla-vacia error-text">${escapeHtml(error.message)}</td></tr>`;
        }
    };

    tablaBody.addEventListener('change', async (event) => {
        const select = event.target.closest('.incidencia-status-select');
        if (!select) return;
        select.disabled = true;
        showStatus('Actualizando estado...');
        try {
            const payload = await fetchJson(`${INCIDENCIAS_URL}?action=estado`, {
                method: 'POST', body: JSON.stringify({ id_incidencia: Number(select.dataset.id), estado: select.value })
            });
            select.classList.toggle('estado-select-solucionado', select.value === 'solucionado');
            select.classList.toggle('estado-select-pendiente', select.value !== 'solucionado');
            showStatus(payload.message || 'Estado actualizado correctamente.');
        } catch (error) {
            await loadIncidencias();
            showStatus(error.message, true);
        } finally {
            if (select.isConnected) select.disabled = false;
        }
    });
    actualizarBtn.addEventListener('click', loadIncidencias);

    const verifyAccess = async () => {
        try {
            const payload = await fetchJson(AUTH_URL);
            const user = payload?.data?.user || null;
            const role = payload?.data?.role || user?.role || 'visitante';
            if (!user || !isAdmin(role)) {
                userPanel.innerHTML = '<span class="pill">Acceso restringido</span>';
                acceso.innerHTML = '<h2>Acceso exclusivo para administradores</h2><p>Necesitás iniciar sesión con una cuenta de administrador municipal.</p><div class="incidencias-access-actions"><a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a><a class="auth-btn secondary" href="index.html">Volver a Principal</a></div>';
                return;
            }
            const name = `${user.nombre || ''} ${user.apellido || ''}`.trim();
            userPanel.innerHTML = `<div class="user-details"><strong>${escapeHtml(name)}</strong><p>Administrador municipal</p></div>`;
            acceso.hidden = true;
            modulo.hidden = false;
            await loadIncidencias();
        } catch (error) {
            acceso.innerHTML = `<h2>No se pudo verificar el acceso</h2><p>${escapeHtml(error.message)}</p><div class="incidencias-access-actions"><a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a><a class="auth-btn secondary" href="index.html">Volver a Principal</a></div>`;
        }
    };
    verifyAccess();
});
