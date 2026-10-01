document.addEventListener('DOMContentLoaded', () => {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const INCIDENCIAS_URL = new URL('../backend/api/incidencias.php', window.location.href).toString();
    const acceso = document.getElementById('incidenciasAcceso');
    const modulo = document.getElementById('incidenciasModulo');
    const userPanel = document.getElementById('incidenciasUserPanel');
    const form = document.getElementById('incidenciaForm');
    const formStatus = document.getElementById('incidenciaFormStatus');
    const abrirBtn = document.getElementById('abrirIncidenciaBtn');
    const cancelarBtn = document.getElementById('cancelarIncidenciaBtn');
    const actualizarBtn = document.getElementById('actualizarIncidenciasBtn');
    const ubicacionBtn = document.getElementById('usarUbicacionBtn');
    const ubicacionInput = document.getElementById('incidenciaUbicacion');
    const marcarUbicacionBtn = document.getElementById('marcarUbicacionBtn');
    const mapaUbicacionElement = document.getElementById('incidenciaUbicacionMapa');
    const puntoElegido = document.getElementById('incidenciaPuntoElegido');
    const fotoInput = document.getElementById('incidenciaFoto');
    const fotoPreview = document.getElementById('incidenciaFotoPreview');
    const tablaBody = document.getElementById('incidenciasTablaBody');
    const listado = document.getElementById('incidenciasListado');
    const mapaSeccion = document.getElementById('incidenciasMapaSeccion');
    const fotoModal = document.getElementById('incidenciaFotoModal');
    const fotoAmpliada = document.getElementById('incidenciaFotoAmpliada');
    const fotoModalTitulo = document.getElementById('incidenciaFotoModalTitulo');
    const cerrarFotoBtn = document.getElementById('cerrarIncidenciaFotoBtn');
    const latitudInput = document.getElementById('incidenciaLatitud');
    const longitudInput = document.getElementById('incidenciaLongitud');
    const mapaElement = document.getElementById('mapaIncidencias');
    let fotoTriggerActivo = null;
    let mapa = null;
    let capaMarcadores = null;
    let mapaSelector = null;
    let marcadorSeleccion = null;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const isVecino = (role) => String(role || '').trim().toLowerCase() === 'vecino';

    const showFormStatus = (message, isError = false) => {
        formStatus.textContent = message;
        formStatus.style.color = isError ? '#b91c1c' : '#166534';
    };

    const setFormOpen = (open) => {
        form.hidden = !open;
        abrirBtn.setAttribute('aria-expanded', String(open));
        abrirBtn.textContent = open ? 'Cerrar formulario' : 'Registrar incidencia';
        if (open) document.getElementById('incidenciaDescripcion')?.focus();
    };

    const validPoint = (lat, lng) => Number.isFinite(lat) && Number.isFinite(lng)
        && lat >= -35.1 && lat <= -34.6 && lng >= -56.5 && lng <= -55.8;
    const choosePoint = (lat, lng) => {
        if (!validPoint(lat, lng)) {
            showFormStatus('Marcá un punto dentro de Montevideo.', true);
            return;
        }
        latitudInput.value = lat.toFixed(7);
        longitudInput.value = lng.toFixed(7);
        if (!ubicacionInput.value.trim()) ubicacionInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        puntoElegido.textContent = `Punto seleccionado: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        if (mapaSelector && typeof L !== 'undefined') {
            if (marcadorSeleccion) mapaSelector.removeLayer(marcadorSeleccion);
            marcadorSeleccion = L.marker([lat, lng]).addTo(mapaSelector);
            mapaSelector.panTo([lat, lng]);
        }
        showFormStatus('Ubicación seleccionada.');
    };

    const formatCode = (id) => `IN${String(Number(id) || 0).padStart(2, '0')}`;

    const formatStatus = (status) => {
        const solved = String(status || '').trim().toLowerCase() === 'solucionado';
        return solved
            ? '<span class="estado-incidencia estado-solucionado">Solucionado</span>'
            : '<span class="estado-incidencia estado-pendiente">A solucionar</span>';
    };

    const photoMarkup = (photo, code) => {
        if (!photo) return '<span class="sin-foto">Sin foto</span>';
        const photoUrl = new URL(`../backend/${String(photo).replace(/^\/+/, '')}`, window.location.href).toString();
        return `
            <button class="incidencia-photo-trigger" type="button" data-photo-url="${escapeHtml(photoUrl)}" data-photo-code="${escapeHtml(code)}" aria-label="Ampliar foto de ${escapeHtml(code)}">
                <img class="incidencia-thumbnail" src="${escapeHtml(photoUrl)}" alt="Foto de la incidencia ${escapeHtml(code)}">
            </button>
        `;
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
        const incidencias = Array.isArray(items) ? items : [];
        renderMapaIncidencias(incidencias);
        if (incidencias.length === 0) {
            tablaBody.innerHTML = '<tr><td colspan="5" class="tabla-vacia">Todavía no registraste incidencias.</td></tr>';
            return;
        }

        tablaBody.innerHTML = incidencias.map((incidencia) => {
            const code = formatCode(incidencia.id_incidencia);
            return `
                <tr>
                    <td><strong>${code}</strong></td>
                    <td class="incidencia-description-cell">${escapeHtml(incidencia.descripcion)}</td>
                    <td>${escapeHtml(incidencia.ubicacion || 'Sin ubicación')}</td>
                    <td>${photoMarkup(incidencia.foto, code)}</td>
                    <td>${formatStatus(incidencia.estado)}</td>
                </tr>
            `;
        }).join('');
    };

    const renderMapaIncidencias = (incidencias) => {
        if (!mapaElement || typeof L === 'undefined') return;
        if (!mapa) {
            mapa = L.map(mapaElement, { scrollWheelZoom: true }).setView([-34.9011, -56.1645], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(mapa);
            capaMarcadores = L.layerGroup().addTo(mapa);
        }
        capaMarcadores.clearLayers();
        const puntos = [];
        incidencias.forEach((incidencia) => {
            const latitud = Number(incidencia.latitud);
            const longitud = Number(incidencia.longitud);
            if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) return;
            const solucionada = String(incidencia.estado || '').toLowerCase() === 'solucionado';
            const color = solucionada ? '#15803d' : '#dc2626';
            const codigo = formatCode(incidencia.id_incidencia);
            L.circleMarker([latitud, longitud], { radius: 8, color, weight: 2, fillColor: color, fillOpacity: .9 })
                .addTo(capaMarcadores)
                .bindTooltip(`<strong>${codigo}</strong><br>${escapeHtml(incidencia.ubicacion || 'Ubicación registrada')}<br>Estado: ${solucionada ? 'Solucionado' : 'A solucionar'}`);
            puntos.push([latitud, longitud]);
        });
        if (puntos.length) mapa.fitBounds(puntos, { padding: [30, 30], maxZoom: 15 });
        else mapa.setView([-34.9011, -56.1645], 12);
        setTimeout(() => mapa?.invalidateSize(), 0);
    };

    const fetchJson = async (url, init = {}) => {
        const csrfToken = localStorage.getItem('cerberoCsrfToken');
        const isMutation = String(init.method || 'GET').toUpperCase() !== 'GET';
        const response = await fetch(url, {
            ...init,
            credentials: 'include',
            headers: {
                ...(isMutation && csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
                ...(init.headers || {})
            }
        });
        const text = await response.text();
        let payload = null;

        try {
            payload = text ? JSON.parse(text) : null;
        } catch (error) {
            throw new Error('El servidor devolvió una respuesta inválida.');
        }

        if (!response.ok || payload?.success === false) {
            throw new Error(payload?.message || `Error del servidor (${response.status}).`);
        }

        if (payload?.data?.csrf_token) localStorage.setItem('cerberoCsrfToken', payload.data.csrf_token);
        return payload;
    };

    const loadIncidencias = async () => {
        tablaBody.innerHTML = '<tr><td colspan="5" class="tabla-vacia">Cargando incidencias...</td></tr>';
        try {
            const payload = await fetchJson(INCIDENCIAS_URL);
            renderIncidencias(payload?.data?.incidencias || []);
        } catch (error) {
            tablaBody.innerHTML = `<tr><td colspan="5" class="tabla-vacia error-text">${escapeHtml(error.message)}</td></tr>`;
        }
    };

    const verifyAccess = async () => {
        try {
            const payload = await fetchJson(AUTH_URL);
            const user = payload?.data?.user || null;
            const role = payload?.data?.role || user?.role || 'visitante';

            if (!user) {
                userPanel.innerHTML = '<span class="pill">Visitante</span>';
                acceso.innerHTML = `
                    <h2>Acceso bloqueado</h2>
                    <p>Para entrar a Incidencias necesitás estar registrado y tener la sesión iniciada.</p>
                    <div class="incidencias-access-actions">
                        <a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a>
                        <a class="auth-btn secondary" href="login.html?mode=register">Registrarse</a>
                        <a class="auth-btn secondary" href="index.html">Volver a Principal</a>
                    </div>
                `;
                return;
            }

            const name = `${user.nombre || ''} ${user.apellido || ''}`.trim();
            userPanel.innerHTML = `
                <div class="user-details">
                    <strong>${escapeHtml(name)}</strong>
                    <p>${escapeHtml(role)}</p>
                </div>
            `;

            if (!isVecino(role)) {
                acceso.innerHTML = `
                    <h2>Acceso exclusivo para vecinos</h2>
                    <p>El registro personal de incidencias corresponde a las cuentas con rol Vecino.</p>
                    <div class="incidencias-access-actions">
                        <a class="auth-btn secondary" href="index.html">Volver a Principal</a>
                    </div>
                `;
                return;
            }

            acceso.hidden = true;
            modulo.hidden = false;
            // El vecino puede registrar una incidencia, pero no consultar el listado ni su ubicación.
            listado.hidden = true;
            mapaSeccion.hidden = true;
        } catch (error) {
            acceso.innerHTML = `
                <h2>No se pudo verificar tu acceso</h2>
                <p>${escapeHtml(error.message)}</p>
                <div class="incidencias-access-actions">
                    <a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a>
                    <a class="auth-btn secondary" href="index.html">Volver a Principal</a>
                </div>
            `;
        }
    };

    abrirBtn.addEventListener('click', () => setFormOpen(form.hidden));

    cancelarBtn.addEventListener('click', () => {
        form.reset();
        fotoPreview.hidden = true;
        fotoPreview.removeAttribute('src');
        puntoElegido.textContent = '';
        mapaUbicacionElement.hidden = true;
        marcarUbicacionBtn.setAttribute('aria-expanded', 'false');
        if (marcadorSeleccion && mapaSelector) mapaSelector.removeLayer(marcadorSeleccion);
        marcadorSeleccion = null;
        showFormStatus('');
        setFormOpen(false);
    });

    actualizarBtn?.addEventListener('click', loadIncidencias);

    tablaBody.addEventListener('click', (event) => {
        const trigger = event.target.closest('.incidencia-photo-trigger');
        if (trigger) abrirFotoModal(trigger);
    });

    cerrarFotoBtn.addEventListener('click', cerrarFotoModal);
    fotoModal.addEventListener('click', (event) => {
        if (event.target === fotoModal) cerrarFotoModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !fotoModal.hidden) cerrarFotoModal();
    });

    fotoInput.addEventListener('change', () => {
        const file = fotoInput.files?.[0];
        if (!file) {
            fotoPreview.hidden = true;
            fotoPreview.removeAttribute('src');
            return;
        }

        const reader = new FileReader();
        reader.addEventListener('load', () => {
            fotoPreview.src = reader.result;
            fotoPreview.hidden = false;
        });
        reader.readAsDataURL(file);
    });

    marcarUbicacionBtn.addEventListener('click', () => {
        if (typeof L === 'undefined') {
            showFormStatus('No se pudo cargar el mapa. Verificá tu conexión o usá tu ubicación.', true);
            return;
        }
        const open = mapaUbicacionElement.hidden;
        mapaUbicacionElement.hidden = !open;
        marcarUbicacionBtn.setAttribute('aria-expanded', String(open));
        if (!open) return;
        if (!mapaSelector) {
            mapaSelector = L.map(mapaUbicacionElement).setView([-34.9011, -56.1645], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap'
            }).addTo(mapaSelector);
            mapaSelector.on('click', event => choosePoint(event.latlng.lat, event.latlng.lng));
        }
        requestAnimationFrame(() => mapaSelector.invalidateSize());
    });

    ubicacionBtn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            showFormStatus('Tu navegador no permite obtener la ubicación automáticamente.', true);
            return;
        }

        showFormStatus('Obteniendo ubicación...');
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                ubicacionInput.value = `${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)}`;
                choosePoint(coords.latitude, coords.longitude);
            },
            () => showFormStatus('No se pudo obtener la ubicación. Marcá el punto en el mapa.', true),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!latitudInput.value || !longitudInput.value) {
            const pair = ubicacionInput.value.trim().match(/^(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/);
            if (pair) choosePoint(Number(pair[1]), Number(pair[2]));
        }
        if (!latitudInput.value || !longitudInput.value) {
            showFormStatus('Primero marcá el punto en el mapa o usá tu ubicación.', true);
            return;
        }
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        showFormStatus('Guardando incidencia...');

        try {
            const payload = await fetchJson(INCIDENCIAS_URL, {
                method: 'POST',
                body: new FormData(form)
            });
            showFormStatus(payload.message || 'Incidencia registrada correctamente.');
            form.reset();
            puntoElegido.textContent = '';
            mapaUbicacionElement.hidden = true;
            marcarUbicacionBtn.setAttribute('aria-expanded', 'false');
            if (marcadorSeleccion && mapaSelector) mapaSelector.removeLayer(marcadorSeleccion);
            marcadorSeleccion = null;
            fotoPreview.hidden = true;
            fotoPreview.removeAttribute('src');
            setTimeout(() => setFormOpen(false), 700);
        } catch (error) {
            showFormStatus(error.message, true);
        } finally {
            submitBtn.disabled = false;
        }
    });

    verifyAccess();
});
