document.addEventListener('DOMContentLoaded', () => {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const INCIDENCIAS_URL = new URL('../backend/api/incidencias.php', window.location.href).toString();
    const acceso = document.getElementById('adminIncidenciasAcceso');
    const modulo = document.getElementById('adminIncidenciasModulo');
    const userPanel = document.getElementById('adminIncidenciasUserPanel');
    const status = document.getElementById('adminIncidenciasStatus');
    const tablaBody = document.getElementById('adminIncidenciasTablaBody');
    const actualizarBtn = document.getElementById('actualizarAdminIncidenciasBtn');
    const fotoModal = document.getElementById('incidenciaFotoModal');
    const fotoAmpliada = document.getElementById('incidenciaFotoAmpliada');
    const fotoModalTitulo = document.getElementById('incidenciaFotoModalTitulo');
    const cerrarFotoBtn = document.getElementById('cerrarIncidenciaFotoBtn');
    const mapaElement = document.getElementById('mapaIncidenciasAdmin');
    const mapaAviso = document.getElementById('mapaIncidenciasAviso');
    const cancelarUbicacionBtn = document.getElementById('cancelarUbicacionIncidencia');
    let fotoTriggerActivo = null;
    let mapa = null;
    let capaMarcadores = null;
    let currentRole = 'visitante';
    let incidenciaPorUbicar = null;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const isAdmin = (role) => ['admin', 'administrador', 'administrador municipal'].includes(String(role || '').trim().toLowerCase());
    const isStaff = (role) => ['admin', 'administrador', 'administrador municipal', 'cuadrilla', 'cuadrilla de recolección', 'operario', 'operario de centro'].includes(String(role || '').trim().toLowerCase());
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
        return `<button class="incidencia-photo-trigger" type="button" data-photo-url="${escapeHtml(url)}" data-photo-code="${escapeHtml(code)}" aria-label="Ampliar foto de ${escapeHtml(code)}"><img class="incidencia-thumbnail" src="${escapeHtml(url)}" alt="Foto de la incidencia ${escapeHtml(code)}"></button>`;
    };

    const abrirFotoModal = (trigger) => {
        const url = trigger.dataset.photoUrl;
        const code = trigger.dataset.photoCode || '';
        if (!url) return;
        fotoTriggerActivo = trigger;
        fotoAmpliada.src = url;
        fotoAmpliada.alt = `Foto ampliada de la incidencia ${code}`;
        fotoModalTitulo.textContent = `Foto de la incidencia ${code}`;
        fotoModal.hidden = false;
        fotoModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        cerrarFotoBtn.focus();
    };

    const cerrarFotoModal = () => {
        if (fotoModal.hidden) return;
        fotoModal.hidden = true;
        fotoModal.setAttribute('aria-hidden', 'true');
        fotoAmpliada.removeAttribute('src');
        document.body.classList.remove('modal-open');
        fotoTriggerActivo?.focus();
        fotoTriggerActivo = null;
    };

    const renderIncidencias = (items) => {
        const lista = Array.isArray(items) ? items : [];
        renderMapa(lista);
        if (!lista.length) {
            tablaBody.innerHTML = '<tr><td colspan="7" class="tabla-vacia">No hay incidencias registradas.</td></tr>';
            return;
        }
        tablaBody.innerHTML = lista.map((item) => {
            const code = formatCode(item.id_incidencia);
            const solucionado = String(item.estado || '').toLowerCase() === 'solucionado';
            const vecino = `${item.nombre || ''} ${item.apellido || ''}`.trim() || item.ci || 'Sin identificar';
            const lat = item.latitud === null || item.latitud === '' ? NaN : Number(item.latitud);
            const lng = item.longitud === null || item.longitud === '' ? NaN : Number(item.longitud);
            const sinPunto = !Number.isFinite(lat) || !Number.isFinite(lng)
                || lat < -35.1 || lat > -34.6 || lng < -56.5 || lng > -55.8;
            return `<tr>
                <td><strong>${code}</strong></td>
                <td>${isAdmin(currentRole) ? `<button class="btn-accion peligro incidencia-delete-btn" type="button" data-id="${Number(item.id_incidencia)}" data-code="${escapeHtml(code)}" aria-label="Eliminar ${escapeHtml(code)}">Eliminar</button>` : '<span class="sin-foto">Solo administrador</span>'}</td>
                <td class="incidencia-vecino-cell"><strong>${escapeHtml(vecino)}</strong><small>${escapeHtml(item.email || item.ci || '')}</small></td>
                <td class="incidencia-description-cell">${escapeHtml(item.descripcion)}</td>
                <td>${escapeHtml(item.ubicacion || 'Sin ubicación')}${sinPunto && isAdmin(currentRole)
                    ? `<button type="button" class="incidencia-secondary-btn incidencia-ubicar-btn" data-id="${Number(item.id_incidencia)}" data-code="${escapeHtml(code)}">Ubicar en mapa</button>` : ''}</td>
                <td>${photoMarkup(item.foto, code)}</td>
                <td><select class="incidencia-status-select ${solucionado ? 'estado-select-solucionado' : 'estado-select-pendiente'}" data-id="${Number(item.id_incidencia)}" aria-label="Estado de ${code}">
                    <option value="a solucionar" ${solucionado ? '' : 'selected'}>A solucionar</option>
                    <option value="solucionado" ${solucionado ? 'selected' : ''}>Solucionado</option>
                </select></td>
            </tr>`;
        }).join('');
    };

    const renderMapa = (incidencias) => {
        if (!mapaElement) return;
        if (typeof L === 'undefined') {
            mapaAviso.textContent = 'No se pudo cargar el mapa. Comprobá tu conexión a Internet y recargá la página.';
            return;
        }
        try {
            if (!mapa) {
                mapa = L.map(mapaElement, { scrollWheelZoom: true }).setView([-34.9011, -56.1645], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(mapa);
                capaMarcadores = L.layerGroup().addTo(mapa);
                mapa.on('click', async event => {
                    if (!incidenciaPorUbicar || !isAdmin(currentRole)) return;
                    const {lat, lng} = event.latlng;
                    if (lat < -35.1 || lat > -34.6 || lng < -56.5 || lng > -55.8) {
                        mapaAviso.textContent = 'Elegí un punto dentro de Montevideo.';
                        return;
                    }
                    const id = incidenciaPorUbicar;
                    incidenciaPorUbicar = null;
                    cancelarUbicacionBtn.hidden = true;
                    mapaElement.classList.remove('modo-ubicacion');
                    mapaAviso.textContent = 'Guardando ubicación...';
                    try {
                        await fetchJson(`${INCIDENCIAS_URL}?action=ubicacion`, {
                            method: 'POST', body: JSON.stringify({id_incidencia: id, latitud: lat, longitud: lng})
                        });
                        await loadIncidencias();
                        showStatus(`Ubicación de ${formatCode(id)} guardada correctamente.`);
                    } catch (error) {
                        showStatus(error.message, true);
                        mapaAviso.textContent = error.message;
                    }
                });
            }
            capaMarcadores.clearLayers();
            const puntos = [];
            incidencias.forEach((item) => {
                const lat = item.latitud === null || item.latitud === '' ? NaN : Number(item.latitud);
                const lng = item.longitud === null || item.longitud === '' ? NaN : Number(item.longitud);
                if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -35.1 || lat > -34.6 || lng < -56.5 || lng > -55.8) return;
                const solved = String(item.estado || '').toLowerCase() === 'solucionado';
                const color = solved ? '#15803d' : '#dc2626';
                L.circleMarker([lat, lng], { radius:8, color, weight:2, fillColor:color, fillOpacity:.9 }).addTo(capaMarcadores)
                    .bindTooltip(`<strong>${formatCode(item.id_incidencia)}</strong><br>${escapeHtml(item.ubicacion || 'Ubicación registrada')}<br>${solved ? 'Solucionado' : 'A solucionar'}`);
                puntos.push([lat,lng]);
            });
            if (puntos.length) mapa.fitBounds(puntos, {padding:[30,30],maxZoom:15});
            else mapa.setView([-34.9011,-56.1645],12);
            const sinCoordenadas = incidencias.length - puntos.length;
            mapaAviso.textContent = sinCoordenadas > 0
                ? `${sinCoordenadas} incidencia(s) antigua(s) sin coordenadas. El administrador puede usar “Ubicar en mapa” desde el listado.`
                : incidencias.length === 0 ? 'Todavía no hay incidencias para mostrar en el mapa.' : '';
            requestAnimationFrame(() => mapa?.invalidateSize());
        } catch (error) {
            mapaAviso.textContent = 'No se pudo dibujar el mapa de incidencias. Recargá esta página.';
            console.error('Error al dibujar el mapa de incidencias.', error);
        }
    };

    const loadIncidencias = async () => {
        tablaBody.innerHTML = '<tr><td colspan="7" class="tabla-vacia">Cargando incidencias...</td></tr>';
        renderMapa([]);
        try {
            const payload = await fetchJson(`${INCIDENCIAS_URL}?action=todas`);
            renderIncidencias(payload?.data?.incidencias || []);
        } catch (error) {
            tablaBody.innerHTML = `<tr><td colspan="7" class="tabla-vacia error-text">${escapeHtml(error.message)}</td></tr>`;
            mapaAviso.textContent = `No se pudieron cargar las incidencias: ${error.message}`;
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
    tablaBody.addEventListener('click', (event) => {
        const locateButton = event.target.closest('.incidencia-ubicar-btn');
        if (locateButton) {
            incidenciaPorUbicar = Number(locateButton.dataset.id);
            cancelarUbicacionBtn.hidden = false;
            mapaElement.classList.add('modo-ubicacion');
            mapaAviso.textContent = `Hacé clic en el mapa para ubicar ${locateButton.dataset.code}.`;
            mapaElement.scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }
        const deleteButton = event.target.closest('.incidencia-delete-btn');
        if (deleteButton) {
            const id = Number(deleteButton.dataset.id);
            const code = deleteButton.dataset.code || 'esta incidencia';
            if (!Number.isInteger(id) || id <= 0 || !confirm(`¿Eliminar definitivamente ${code}? Esta acción no se puede deshacer.`)) return;
            deleteButton.disabled = true;
            showStatus(`Eliminando ${code}...`);
            fetchJson(`${INCIDENCIAS_URL}?action=eliminar`, {
                method: 'POST', body: JSON.stringify({ id_incidencia: id })
            }).then((payload) => {
                return loadIncidencias().then(() => showStatus(payload.message || 'Incidencia eliminada correctamente.'));
            }).catch((error) => {
                showStatus(error.message, true);
                if (deleteButton.isConnected) deleteButton.disabled = false;
            });
            return;
        }
        const trigger = event.target.closest('.incidencia-photo-trigger');
        if (trigger) abrirFotoModal(trigger);
    });
    cancelarUbicacionBtn.addEventListener('click', () => {
        incidenciaPorUbicar = null;
        cancelarUbicacionBtn.hidden = true;
        mapaElement.classList.remove('modo-ubicacion');
        mapaAviso.textContent = 'Ubicación cancelada.';
    });
    cerrarFotoBtn.addEventListener('click', cerrarFotoModal);
    fotoModal.addEventListener('click', (event) => {
        if (event.target === fotoModal) cerrarFotoModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !fotoModal.hidden) cerrarFotoModal();
    });
    actualizarBtn.addEventListener('click', loadIncidencias);

    const verifyAccess = async () => {
        try {
            const payload = await fetchJson(AUTH_URL);
            const user = payload?.data?.user || null;
            const role = payload?.data?.role || user?.role || 'visitante';
            if (!user || !isStaff(role)) {
                userPanel.innerHTML = '<span class="pill">Acceso restringido</span>';
                acceso.innerHTML = '<h2>Acceso exclusivo para personal autorizado</h2><p>Necesitás iniciar sesión con una cuenta del área de gestión o recolección.</p><div class="incidencias-access-actions"><a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a><a class="auth-btn secondary" href="index.html">Volver a Principal</a></div>';
                return;
            }
            currentRole = role;
            const name = `${user.nombre || ''} ${user.apellido || ''}`.trim();
            userPanel.innerHTML = `<div class="user-details"><strong>${escapeHtml(name)}</strong><p>${escapeHtml(role)}</p></div>`;
            acceso.hidden = true;
            modulo.hidden = false;
            await loadIncidencias();
        } catch (error) {
            acceso.innerHTML = `<h2>No se pudo verificar el acceso</h2><p>${escapeHtml(error.message)}</p><div class="incidencias-access-actions"><a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a><a class="auth-btn secondary" href="index.html">Volver a Principal</a></div>`;
        }
    };
    verifyAccess();
});
