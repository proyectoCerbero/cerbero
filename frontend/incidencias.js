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
    const fotoInput = document.getElementById('incidenciaFoto');
    const fotoPreview = document.getElementById('incidenciaFotoPreview');
    const tablaBody = document.getElementById('incidenciasTablaBody');

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
            <a href="${escapeHtml(photoUrl)}" target="_blank" rel="noopener" aria-label="Ver foto de ${escapeHtml(code)}">
                <img class="incidencia-thumbnail" src="${escapeHtml(photoUrl)}" alt="Foto de la incidencia ${escapeHtml(code)}">
            </a>
        `;
    };

    const renderIncidencias = (items) => {
        const incidencias = Array.isArray(items) ? items : [];
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
            await loadIncidencias();
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
        showFormStatus('');
        setFormOpen(false);
    });

    actualizarBtn.addEventListener('click', loadIncidencias);

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

    ubicacionBtn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            showFormStatus('Tu navegador no permite obtener la ubicación automáticamente.', true);
            return;
        }

        showFormStatus('Obteniendo ubicación...');
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                ubicacionInput.value = `${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)}`;
                showFormStatus('Ubicación agregada.');
            },
            () => showFormStatus('No se pudo obtener la ubicación. Podés escribirla manualmente.', true),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
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
            fotoPreview.hidden = true;
            fotoPreview.removeAttribute('src');
            await loadIncidencias();
            setTimeout(() => setFormOpen(false), 700);
        } catch (error) {
            showFormStatus(error.message, true);
        } finally {
            submitBtn.disabled = false;
        }
    });

    verifyAccess();
});
