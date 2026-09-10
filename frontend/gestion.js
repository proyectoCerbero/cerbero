document.addEventListener('DOMContentLoaded', () => {
    const API_BASE = '../backend/api';
    const AUTH_URL = new URL(`${API_BASE}/auth.php`, window.location.href).toString();
    const USUARIOS_URL = new URL(`${API_BASE}/usuarios.php`, window.location.href).toString();
    const CUADRILLAS_URL = new URL(`${API_BASE}/cuadrillas.php`, window.location.href).toString();
    const CAMIONES_URL = new URL(`${API_BASE}/camiones.php`, window.location.href).toString();
    const CONTENEDORES_URL = new URL(`${API_BASE}/contenedores.php`, window.location.href).toString();
    const INSTALACIONES_URL = new URL(`${API_BASE}/instalaciones.php`, window.location.href).toString();
    const MAQUINARIA_URL = new URL(`${API_BASE}/maquinaria.php`, window.location.href).toString();
    const SECCION_ACTUAL = document.body.dataset.gestionSection || 'usuarios';

    const ROLE_OPTIONS = [
        { value: 'vecino', label: 'Vecino' },
        { value: 'cuadrilla de recolección', label: 'Miembro de cuadrilla' },
        { value: 'operario de centro', label: 'Operario de instalación' },
        { value: 'administrador municipal', label: 'Administrador municipal' }
    ];

    // ---------- SESIÓN REAL (viene del servidor, no de un selector de ejemplo) ----------
    let currentUser = null;
    let currentRole = 'visitante';
    let cuadrillasDisponibles = [];

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const isAdmin = () => currentRole === 'administrador municipal';
    const isCuadrilla = () => currentRole === 'cuadrilla de recolección';
    const isOperario = () => currentRole === 'operario de centro';

    // Qué secciones puede ver/gestionar cada rol.
    const PERMISOS = {
        'administrador municipal': { ver: ['usuarios', 'cuadrillas', 'camiones', 'contenedores', 'instalaciones', 'maquinaria'], gestionar: ['usuarios', 'cuadrillas', 'camiones', 'contenedores', 'instalaciones', 'maquinaria'] },
        'cuadrilla de recolección': { ver: ['cuadrillas', 'camiones', 'contenedores'], gestionar: ['camiones', 'contenedores'] },
        'operario de centro': { ver: ['instalaciones', 'maquinaria'], gestionar: ['instalaciones', 'maquinaria'] },
        'vecino': { ver: ['contenedores', 'instalaciones'], gestionar: [] },
        'visitante': { ver: [], gestionar: [] }
    };

    const puedeVer = (seccion) => (PERMISOS[currentRole]?.ver || []).includes(seccion);
    const puedeGestionar = (seccion) => (PERMISOS[currentRole]?.gestionar || []).includes(seccion);

    const showStatus = (el, message, isError = false) => {
        if (!el) return;
        el.textContent = message;
        el.style.color = isError ? 'crimson' : 'green';
        setTimeout(() => el.textContent = '', 4000);
    };

    const fetchJson = async (url, init = {}) => {
        const csrfToken = localStorage.getItem('cerberoCsrfToken');
        const isMutation = String(init.method || 'GET').toUpperCase() !== 'GET';
        const finalOptions = {
            ...init,
            headers: {
                'Content-Type': 'application/json',
                ...(isMutation && csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
                ...(init.headers || {})
            },
            credentials: 'include'
        };

        try {
            const response = await fetch(url, finalOptions);
            const text = await response.text();
            let data = null;

            try {
                data = text ? JSON.parse(text) : null;
            } catch (e) {
                console.error(`Respuesta no válida de ${url}:`, text);
            }

            if (!response.ok) {
                const msg = data?.message || `Error del servidor (${response.status})`;
                return data || { success: false, message: msg };
            }

            if (data?.data?.csrf_token) localStorage.setItem('cerberoCsrfToken', data.data.csrf_token);

            return data || { success: true };
        } catch (error) {
            console.error(`Error de conexión al consultar: ${url}`, error);
            return { success: false, message: 'Error de conexión con el servidor.' };
        }
    };

    const genericDelete = async (url, id, idField, statusElement) => {
        if (!confirm('¿Estás seguro de eliminar este registro?')) return;
        try {
            const result = await fetchJson(`${url}?action=delete`, {
                method: 'POST',
                body: JSON.stringify({ [idField]: id, id: id })
            });
            showStatus(statusElement, result.message || (result.success ? 'Eliminado con éxito' : 'Error al eliminar'), !result.success);
            if (result.success) await cargarDatos();
        } catch (error) {
            showStatus(statusElement, 'Error al eliminar', true);
        }
    };

    // ---------- CONTROL DE ACCESO POR SECCIÓN ----------

    const aplicarPermisosDeSeccion = () => {
        const secciones = {
            seccionUsuarios: 'usuarios',
            seccionCuadrillas: 'cuadrillas',
            seccionCamiones: 'camiones',
            seccionContenedores: 'contenedores',
            seccionInstalaciones: 'instalaciones',
            seccionMaquinaria: 'maquinaria'
        };

        Object.entries(secciones).forEach(([elId, key]) => {
            const el = document.getElementById(elId);
            if (!el) return;
            el.style.display = puedeVer(key) ? '' : 'none';
        });

        // Los formularios de alta solo se muestran a quien puede gestionar esa sección.
        document.getElementById('crearUsuarioForm')?.style.setProperty('display', puedeGestionar('usuarios') ? 'grid' : 'none');
        document.getElementById('crearCuadrillaForm')?.style.setProperty('display', puedeGestionar('cuadrillas') ? 'grid' : 'none');
        document.getElementById('crearCamionForm')?.style.setProperty('display', puedeGestionar('camiones') ? 'grid' : 'none');
        document.getElementById('crearContenedorForm')?.style.setProperty('display', puedeGestionar('contenedores') ? 'grid' : 'none');
        document.getElementById('crearInstalacionForm')?.style.setProperty('display', puedeGestionar('instalaciones') ? 'grid' : 'none');
        document.getElementById('crearMaquinariaForm')?.style.setProperty('display', puedeGestionar('maquinaria') ? 'grid' : 'none');
    };

    const renderUserPanel = () => {
        const panel = document.getElementById('gestionUserPanel');
        if (!panel) return;

        if (!currentUser) {
            panel.innerHTML = `<a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a>`;
            return;
        }

        const roleLabel = ROLE_OPTIONS.find(r => r.value === currentRole)?.label || currentRole;
        panel.innerHTML = `
            <div class="user-details">
                <strong>${currentUser.nombre || ''} ${currentUser.apellido || ''}</strong>
                <p>${roleLabel}</p>
            </div>
        `;
    };

    const sincronizarSesion = async () => {
        const result = await fetchJson(`${AUTH_URL}?action=me`);
        currentUser = result?.data?.user || null;
        currentRole = result?.data?.role || 'visitante';

        if (currentUser) {
            localStorage.setItem('cerberoUser', JSON.stringify(currentUser));
        } else {
            localStorage.removeItem('cerberoUser');
        }

        renderUserPanel();
        aplicarPermisosDeSeccion();

        const denegado = document.getElementById('gestionAccesoDenegado');
        const grid = document.getElementById('gestionGrid');

        if (currentRole === 'visitante') {
            if (denegado) {
                denegado.style.display = 'block';
                denegado.style.color = 'crimson';
                denegado.textContent = 'Necesitás iniciar sesión para acceder a la gestión del sistema.';
            }
            if (grid) grid.style.display = 'none';
            return false;
        }

        if (!puedeVer(SECCION_ACTUAL)) {
            if (denegado) {
                denegado.style.display = 'block';
                denegado.style.color = 'crimson';
                denegado.textContent = 'No tenés permisos para acceder a este apartado de Gestión.';
            }
            if (grid) grid.style.display = 'none';
            return false;
        }

        if (denegado) denegado.style.display = 'none';
        if (grid) grid.style.display = '';
        return true;
    };

    // ---------- CARGA DE DATOS (CRUD READ) ----------

    const cargarDatos = async () => {
        try {
            const esPagina = (seccion) => SECCION_ACTUAL === seccion;
            const necesitaCuadrillas = puedeVer('cuadrillas') && (esPagina('usuarios') || esPagina('cuadrillas') || esPagina('camiones'));
            const pedidos = {
                users: puedeVer('usuarios') && esPagina('usuarios') ? fetchJson(USUARIOS_URL) : Promise.resolve({ success: true, data: { usuarios: [] } }),
                cuadrillas: necesitaCuadrillas ? fetchJson(CUADRILLAS_URL) : Promise.resolve({ success: true, data: { cuadrillas: [], rutas: [] } }),
                camiones: puedeVer('camiones') && esPagina('camiones') ? fetchJson(CAMIONES_URL) : Promise.resolve({ success: true, data: { camiones: [] } }),
                contenedores: puedeVer('contenedores') && esPagina('contenedores') ? fetchJson(CONTENEDORES_URL) : Promise.resolve({ success: true, data: { contenedores: [] } }),
                instalaciones: puedeVer('instalaciones') && esPagina('instalaciones') ? fetchJson(INSTALACIONES_URL) : Promise.resolve({ success: true, data: { instalaciones: [] } }),
                maquinaria: puedeVer('maquinaria') && esPagina('maquinaria') ? fetchJson(MAQUINARIA_URL) : Promise.resolve({ success: true, data: { maquinaria: [] } })
            };

            const [users, cuadrillas, camiones, contenedores, instalaciones, maquinaria] = await Promise.all(
                Object.values(pedidos)
            );

            cuadrillasDisponibles = cuadrillas?.data?.cuadrillas || [];
            if (esPagina('usuarios')) renderUsuarios(users?.data?.usuarios || []);
            if (esPagina('cuadrillas')) renderCuadrillas(cuadrillas?.data?.cuadrillas || []);
            if (esPagina('camiones')) renderCamiones(camiones?.data?.camiones || []);
            if (esPagina('contenedores')) renderContenedores(contenedores?.data?.contenedores || []);
            if (esPagina('instalaciones')) renderInstalaciones(instalaciones?.data?.instalaciones || []);
            if (esPagina('maquinaria')) renderMaquinaria(maquinaria?.data?.maquinaria || []);

            const cuadrillaRutaSelect = document.getElementById('cuadrillaRuta');
            if (cuadrillaRutaSelect) {
                const rutaSeleccionada = cuadrillaRutaSelect.value;
                const listaRutas = cuadrillas?.data?.rutas || [];
                cuadrillaRutaSelect.innerHTML = listaRutas.map(ruta =>
                    `<option value="${ruta.id_ruta}">${escapeHtml(ruta.nombre || `Ruta ${ruta.id_ruta}`)}</option>`
                ).join('');
                if (rutaSeleccionada) cuadrillaRutaSelect.value = rutaSeleccionada;
            }

            const camionCuadrillaSelect = document.getElementById('camionCuadrilla');
            if (camionCuadrillaSelect) {
                const listaCuadrillas = cuadrillas?.data?.cuadrillas || [];
                camionCuadrillaSelect.innerHTML = '<option value="">Sin asignar</option>' +
                    listaCuadrillas.map(c => `<option value="${c.id_cuadrilla || c.id}">${c.nombre}</option>`).join('');
            }

            const usuarioCuadrillaSelect = document.getElementById('usuarioCuadrilla');
            if (usuarioCuadrillaSelect) {
                const seleccionada = usuarioCuadrillaSelect.value;
                const listaCuadrillas = cuadrillas?.data?.cuadrillas || [];
                usuarioCuadrillaSelect.innerHTML = '<option value="">Sin asignar</option>' +
                    listaCuadrillas.map(c => `<option value="${c.id_cuadrilla || c.id}">${escapeHtml(c.nombre || '')}</option>`).join('');
                if (seleccionada) usuarioCuadrillaSelect.value = seleccionada;
            }
        } catch (error) {
            console.error('Error al cargar datos generales', error);
        }
    };

    // ---------- RENDERIZADOS DE TABLAS ----------

    const renderUsuarios = (usuarios) => {
        const body = document.getElementById('usuariosTablaBody');
        if (!body) return;
        const lista = Array.isArray(usuarios) ? usuarios : [];
        body.innerHTML = lista.length ? lista.map(u => {
            const ci = u.ci || u.id;
            const rolVal = u.rol || u.role || 'vecino';
            const estadoVal = u.estado || 'activo';
            const esSesionActual = String(ci) === String(currentUser?.ci || currentUser?.id || '');
            const esCuentaBase = String(ci) === '00000000';
            const eliminacionProtegida = esSesionActual || esCuentaBase;
            const textoProtegido = esSesionActual ? 'Sesión actual' : 'Cuenta base';
            return `
                <tr data-ci="${ci}">
                    <td>${escapeHtml(ci || '-')}</td>
                    <td>
                        ${isAdmin() ? `
                            <input class="edit-nombre" style="width:80px" value="${escapeHtml(u.nombre || '')}">
                            <input class="edit-apellido" style="width:80px" value="${escapeHtml(u.apellido || '')}">
                        ` : `${escapeHtml(u.nombre || '')} ${escapeHtml(u.apellido || '')}`}
                    </td>
                    <td>${isAdmin() ? `<input class="edit-email" style="width:140px" value="${escapeHtml(u.email || '')}">` : escapeHtml(u.email || '')}</td>
                    <td>${isAdmin() ? `<select class="rol-select">${ROLE_OPTIONS.map(r => `<option value="${r.value}" ${r.value === rolVal ? 'selected' : ''}>${r.label}</option>`).join('')}</select>` : rolVal}</td>
                    <td>${isAdmin() ? `<select class="edit-cuadrilla"><option value="">Sin asignar</option>${cuadrillasDisponibles.map(c => {
                        const idCuadrilla = String(c.id_cuadrilla || c.id || '');
                        return `<option value="${escapeHtml(idCuadrilla)}" ${String(u.id_cuadrilla || '') === idCuadrilla ? 'selected' : ''}>${escapeHtml(c.nombre || '')}</option>`;
                    }).join('')}</select>` : escapeHtml(u.cuadrilla || 'Sin asignar')}</td>
                    <td><span class="badge ${estadoVal === 'activo' ? 'badge-activo' : 'badge-inactivo'}">${escapeHtml(estadoVal)}</span></td>
                    <td>
                        ${isAdmin() ? `
                            <button class="btn-accion btn-guardar-usuario">Guardar</button>
                            <button class="btn-accion peligro btn-toggle-estado" data-estado="${estadoVal === 'activo' ? 'inactivo' : 'activo'}">${estadoVal === 'activo' ? 'Desactivar' : 'Activar'}</button>
                            ${eliminacionProtegida
                                ? `<button class="btn-accion peligro" disabled title="Esta cuenta no se puede eliminar">${textoProtegido}</button>`
                                : '<button class="btn-accion peligro btn-eliminar-usuario">Eliminar</button>'}
                        ` : '-'}
                    </td>
                </tr>
            `;
        }).join('') : '<tr><td colspan="7">No hay usuarios.</td></tr>';
    };

    const renderCuadrillas = (cuadrillas) => {
        const body = document.getElementById('cuadrillasTablaBody');
        if (!body) return;
        const lista = Array.isArray(cuadrillas) ? cuadrillas : [];
        body.innerHTML = lista.length ? lista.map(c => {
            const id = c.id_cuadrilla || c.id;
            return `
            <tr>
                <td>${id}</td><td>${escapeHtml(c.nombre || '')}</td><td>${escapeHtml(c.ruta_nombre || `Ruta ${c.id_ruta || '-'}`)}</td><td>${escapeHtml(c.turno || '')}</td><td>${escapeHtml(c.estado || 'activa')}</td>
                <td>${puedeGestionar('cuadrillas') ? `
                    <button class="btn-accion" onclick='editarCuadrilla(${JSON.stringify(c)})'>Editar</button>
                    <button class="btn-accion peligro" onclick="genericDelete('${CUADRILLAS_URL}', ${id}, 'id_cuadrilla', document.getElementById('cuadrillasStatus'))">Eliminar</button>
                ` : '-'}</td>
            </tr>
        `;
        }).join('') : '<tr><td colspan="6">No hay cuadrillas.</td></tr>';
    };

    const renderCamiones = (camiones) => {
        const body = document.getElementById('camionesTablaBody');
        if (!body) return;
        const lista = Array.isArray(camiones) ? camiones : [];
        body.innerHTML = lista.length ? lista.map(c => {
            const id = c.id_camion || c.id;
            return `
            <tr>
                <td>${escapeHtml(c.matricula || '')}</td><td>${escapeHtml(`${c.marca || ''} ${c.modelo || ''}`.trim())}</td><td>${c.anio || ''}</td><td>${c.capacidad || ''}</td><td>${escapeHtml(c.estado || 'Operativo')}</td><td>${escapeHtml(c.cuadrilla_nombre || 'Sin asignar')}</td><td>${escapeHtml(c.ruta_nombre || '-')}</td><td>${escapeHtml(c.cuadrilla_turno || '-')}</td>
                <td>${puedeGestionar('camiones') ? `
                    <button class="btn-accion" onclick='editarCamion(${JSON.stringify(c)})'>Editar</button>
                    ${isAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${CAMIONES_URL}', ${id}, 'id_camion', document.getElementById('camionesStatus'))">Eliminar</button>` : ''}
                ` : '-'}</td>
            </tr>
        `;
        }).join('') : '<tr><td colspan="9">No hay camiones.</td></tr>';
    };

    const renderContenedores = (contenedores) => {
        const body = document.getElementById('contenedoresTablaBody');
        if (!body) return;
        const lista = Array.isArray(contenedores) ? contenedores : [];
        body.innerHTML = lista.length ? lista.map(c => {
            const id = c.id_contenedor || c.id;
            const ubicacion = [c.barrio, c.calle, c.numero].filter(Boolean).join(' · ') || `Ubic. ${c.id_ubicacion || '-'}`;
            const estado = String(c.estado || 'en buen estado');
            return `
            <tr>
                <td>${id}</td><td>${Number(c.capacidad || 0).toFixed(0)} L</td><td><strong>${Number(c.nivel_llenado || 0).toFixed(0)} %</strong></td><td>${escapeHtml(estado)}</td><td>${escapeHtml(ubicacion)}</td>
                <td>${puedeGestionar('contenedores') ? `
                    <button class="btn-accion" onclick='editarContenedor(${JSON.stringify(c)})'>Editar</button>
                    <button class="btn-accion btn-limpiar-contenedor" onclick="limpiarContenedor(${Number(id)}, ${Number(c.nivel_llenado || 0)})">Registrar limpieza</button>
                    <button class="btn-accion" onclick="verHistorialContenedor(${Number(id)})">Historial</button>
                    ${isAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${CONTENEDORES_URL}', ${id}, 'id_contenedor', document.getElementById('contenedoresStatus'))">Eliminar</button>` : ''}
                ` : '-'}</td>
            </tr>
        `;
        }).join('') : '<tr><td colspan="6">No hay contenedores.</td></tr>';
    };

    window.limpiarContenedor = async (id, nivelActual) => {
        const respuesta = prompt(`¿Con qué porcentaje de llenado encontraron el contenedor #${id}?`, String(Math.round(Number(nivelActual) || 0)));
        if (respuesta === null) return;
        const observado = Number(respuesta);
        if (!Number.isFinite(observado) || observado < 0 || observado > 100) {
            showStatus(document.getElementById('contenedoresStatus'), 'Ingresá un porcentaje entre 0 y 100.', true);
            return;
        }
        const result = await fetchJson(`${CONTENEDORES_URL}?action=limpiar`, {
            method: 'POST',
            body: JSON.stringify({ id_contenedor: id, nivel_llenado_antes: observado })
        });
        showStatus(document.getElementById('contenedoresStatus'), result.message || 'Limpieza registrada.', !result.success);
        if (result.success) await cargarDatos();
    };

    window.verHistorialContenedor = async (id) => {
        const modal = document.getElementById('historialContenedorModal');
        const title = document.getElementById('historialContenedorTitulo');
        const body = document.getElementById('historialContenedorBody');
        if (!modal || !title || !body) return;
        title.textContent = `Historial de llenado · Contenedor #${id}`;
        body.innerHTML = '<tr><td colspan="5" class="tabla-vacia">Cargando historial...</td></tr>';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        const result = await fetchJson(`${CONTENEDORES_URL}?action=historial&id_contenedor=${encodeURIComponent(id)}`);
        if (!result.success) {
            body.innerHTML = `<tr><td colspan="5" class="tabla-vacia error-text">${escapeHtml(result.message || 'No se pudo cargar el historial.')}</td></tr>`;
            return;
        }
        const historial = Array.isArray(result?.data?.historial) ? result.data.historial : [];
        body.innerHTML = historial.length ? historial.map((registro) => {
            const antes = registro.porcentaje_antes == null ? '—' : `${Number(registro.porcentaje_antes).toFixed(0)} %`;
            const despues = `${Number(registro.porcentaje_despues || 0).toFixed(0)} %`;
            const usuario = registro.usuario_nombre || registro.ci_usuario || 'Sistema';
            return `<tr><td>${escapeHtml(registro.fecha_hora || '-')}</td><td>${escapeHtml(antes)}</td><td>${escapeHtml(despues)}</td><td>${escapeHtml(registro.accion || '-')}</td><td>${escapeHtml(usuario)}</td></tr>`;
        }).join('') : '<tr><td colspan="5" class="tabla-vacia">Todavía no hay registros de llenado.</td></tr>';
    };

    const renderInstalaciones = (instalaciones) => {
        const body = document.getElementById('instalacionesTablaBody');
        if (!body) return;
        const lista = Array.isArray(instalaciones) ? instalaciones : [];
        body.innerHTML = lista.length ? lista.map(i => {
            const id = i.id_instalacion || i.id;
            return `
            <tr>
                <td>${i.nombre || ''}</td><td>${i.calle || ''} ${i.numero || ''}</td><td>${i.horario || ''}</td><td>${i.capacidad || 0} T</td><td>${i.estado || 'activo'}</td>
                <td>${puedeGestionar('instalaciones') ? `
                    <button class="btn-accion" onclick='editarInstalacion(${JSON.stringify(i)})'>Editar</button>
                    ${isAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${INSTALACIONES_URL}', ${id}, 'id_instalacion', document.getElementById('instalacionesStatus'))">Eliminar</button>` : ''}
                ` : '-'}</td>
            </tr>
        `;
        }).join('') : '<tr><td colspan="6">No hay instalaciones.</td></tr>';
    };

    const renderMaquinaria = (maquinaria) => {
        const body = document.getElementById('maquinariaTablaBody');
        if (!body) return;
        const lista = Array.isArray(maquinaria) ? maquinaria : [];
        body.innerHTML = lista.length ? lista.map(m => {
            const id = m.id_maquinaria || m.id;
            return `
            <tr>
                <td>${id}</td><td>${m.nombre || ''}</td><td>${m.tipo || ''}</td><td>${m.id_instalacion || '-'}</td><td>${m.estado || 'operativa'}</td>
                <td>${puedeGestionar('maquinaria') ? `
                    <button class="btn-accion" onclick='editarMaquinaria(${JSON.stringify(m)})'>Editar</button>
                    ${isAdmin() ? `<button class="btn-accion peligro" onclick="genericDelete('${MAQUINARIA_URL}', ${id}, 'id_maquinaria', document.getElementById('maquinariaStatus'))">Eliminar</button>` : ''}
                ` : '-'}</td>
            </tr>
        `;
        }).join('') : '<tr><td colspan="6">No hay maquinaria registrada.</td></tr>';
    };

    // ---------- EDICIÓN: cargar datos existentes en el formulario de alta ----------

    window.editarCuadrilla = (c) => {
        document.getElementById('cuadrillaEditId').value = c.id_cuadrilla || c.id;
        document.getElementById('cuadrillaNombre').value = c.nombre || '';
        document.getElementById('cuadrillaRuta').value = c.id_ruta || '';
        document.getElementById('cuadrillaTurno').value = c.turno || '00:00-08:00';
        document.getElementById('cuadrillaEstado').value = c.estado || 'activa';
        document.getElementById('crearCuadrillaBtn').textContent = 'Guardar cambios';
        document.getElementById('cancelarCuadrillaBtn').style.display = 'inline-block';
    };

    window.editarCamion = (c) => {
        document.getElementById('camionEditId').value = c.id_camion || c.id;
        document.getElementById('camionMatricula').value = c.matricula || '';
        document.getElementById('camionMarca').value = c.marca || '';
        document.getElementById('camionModelo').value = c.modelo || '';
        document.getElementById('camionAnio').value = c.anio || '';
        document.getElementById('camionKilometraje').value = c.kilometraje || '';
        document.getElementById('camionCapacidad').value = c.capacidad || '';
        document.getElementById('camionEstado').value = c.estado || 'Operativo';
        document.getElementById('camionCuadrilla').value = c.id_cuadrilla || '';
        document.getElementById('crearCamionBtn').textContent = 'Guardar cambios';
        document.getElementById('cancelarCamionBtn').style.display = 'inline-block';
    };

    window.editarContenedor = (c) => {
        document.getElementById('contenedorEditId').value = c.id_contenedor || c.id;
        document.getElementById('contCapacidad').value = c.capacidad || '';
        document.getElementById('contNivel').value = c.nivel_llenado ?? 0;
        document.getElementById('contUbicacion').value = c.id_ubicacion || '';
        document.getElementById('contTipoResiduo').value = c.id_tipo_residuo || '';
        document.getElementById('contEstado').value = c.estado || 'en buen estado';
        document.getElementById('crearContenedorBtn').textContent = 'Guardar cambios';
        document.getElementById('cancelarContenedorBtn').style.display = 'inline-block';
    };

    window.editarInstalacion = (i) => {
        document.getElementById('instalacionEditId').value = i.id_instalacion || i.id;
        document.getElementById('instNombre').value = i.nombre || '';
        document.getElementById('instCalle').value = i.calle || '';
        document.getElementById('instNumero').value = i.numero || '';
        document.getElementById('instTelefono').value = i.telefono || '';
        document.getElementById('instHorario').value = i.horario || '';
        document.getElementById('instCapacidad').value = i.capacidad || '';
        document.getElementById('instEstado').value = i.estado || 'activo';
        document.getElementById('crearInstalacionBtn').textContent = 'Guardar cambios';
        document.getElementById('cancelarInstalacionBtn').style.display = 'inline-block';
    };

    window.editarMaquinaria = (m) => {
        document.getElementById('maquinariaEditId').value = m.id_maquinaria || m.id;
        document.getElementById('maqNombre').value = m.nombre || '';
        document.getElementById('maqTipo').value = m.tipo || '';
        document.getElementById('maqInstalacion').value = m.id_instalacion || '';
        document.getElementById('maqEstado').value = m.estado || 'operativa';
        document.getElementById('crearMaquinariaBtn').textContent = 'Guardar cambios';
        document.getElementById('cancelarMaquinariaBtn').style.display = 'inline-block';
    };

    const resetFormularioEdicion = (formId, editIdField, btnId, cancelBtnId, textoOriginal) => {
        document.getElementById(formId)?.reset();
        document.getElementById(editIdField).value = '';
        document.getElementById(btnId).textContent = textoOriginal;
        document.getElementById(cancelBtnId).style.display = 'none';
    };

    document.getElementById('cancelarCuadrillaBtn')?.addEventListener('click', () => resetFormularioEdicion('crearCuadrillaForm', 'cuadrillaEditId', 'crearCuadrillaBtn', 'cancelarCuadrillaBtn', 'Crear cuadrilla'));
    document.getElementById('cancelarCamionBtn')?.addEventListener('click', () => resetFormularioEdicion('crearCamionForm', 'camionEditId', 'crearCamionBtn', 'cancelarCamionBtn', 'Registrar camión'));
    document.getElementById('cancelarContenedorBtn')?.addEventListener('click', () => resetFormularioEdicion('crearContenedorForm', 'contenedorEditId', 'crearContenedorBtn', 'cancelarContenedorBtn', 'Registrar contenedor'));
    document.getElementById('cancelarInstalacionBtn')?.addEventListener('click', () => resetFormularioEdicion('crearInstalacionForm', 'instalacionEditId', 'crearInstalacionBtn', 'cancelarInstalacionBtn', 'Registrar Centro'));
    document.getElementById('cancelarMaquinariaBtn')?.addEventListener('click', () => resetFormularioEdicion('crearMaquinariaForm', 'maquinariaEditId', 'crearMaquinariaBtn', 'cancelarMaquinariaBtn', 'Registrar Maquinaria'));

    const cerrarHistorial = () => {
        const modal = document.getElementById('historialContenedorModal');
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };
    document.getElementById('cerrarHistorialContenedorBtn')?.addEventListener('click', cerrarHistorial);
    document.getElementById('historialContenedorModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'historialContenedorModal') cerrarHistorial();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') cerrarHistorial();
    });

    // ---------- EVENTOS TABLA USUARIOS ----------

    document.getElementById('usuariosTablaBody')?.addEventListener('click', async (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        const ci = tr.dataset.ci;

        if (e.target.classList.contains('btn-guardar-usuario')) {
            const nombre = tr.querySelector('.edit-nombre')?.value;
            const apellido = tr.querySelector('.edit-apellido')?.value;
            const email = tr.querySelector('.edit-email')?.value;
            const rol = tr.querySelector('.rol-select')?.value;
            const idCuadrilla = tr.querySelector('.edit-cuadrilla')?.value || '';

            const res = await fetchJson(`${USUARIOS_URL}?action=update`, {
                method: 'POST',
                body: JSON.stringify({ ci, nombre, apellido, email, role: rol, id_cuadrilla: rol === 'cuadrilla de recolección' ? idCuadrilla : '' })
            });
            showStatus(document.getElementById('usuariosStatus'), res.message || 'Usuario actualizado', !res.success);
            if (res.success) await cargarDatos();
        }

        if (e.target.classList.contains('btn-toggle-estado')) {
            const nuevoEstado = e.target.dataset.estado;
            const res = await fetchJson(`${USUARIOS_URL}?action=estado`, {
                method: 'POST',
                body: JSON.stringify({ ci, estado: nuevoEstado })
            });
            showStatus(document.getElementById('usuariosStatus'), res.message || 'Estado actualizado', !res.success);
            if (res.success) await cargarDatos();
        }

        if (e.target.classList.contains('btn-eliminar-usuario')) {
            if (!confirm('¿Eliminar este usuario?')) return;
            const res = await fetchJson(`${USUARIOS_URL}?action=delete`, {
                method: 'POST',
                body: JSON.stringify({ ci })
            });
            showStatus(document.getElementById('usuariosStatus'), res.message || 'Usuario eliminado', !res.success);
            if (res.success) await cargarDatos();
        }
    });

    // ---------- EVENTOS FORMULARIOS (CRUD CREATE & UPDATE) ----------

    const actualizarCampoCuadrillaUsuario = () => {
        const rol = document.getElementById('usuarioRol')?.value;
        const select = document.getElementById('usuarioCuadrilla');
        if (!select) return;
        select.disabled = rol !== 'cuadrilla de recolección';
        if (select.disabled) select.value = '';
    };

    document.getElementById('usuarioRol')?.addEventListener('change', actualizarCampoCuadrillaUsuario);
    actualizarCampoCuadrillaUsuario();

    document.getElementById('crearUsuarioForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        const payload = {
            ci: document.getElementById('usuarioCi').value.trim(),
            nombre: document.getElementById('usuarioNombre').value.trim(),
            apellido: document.getElementById('usuarioApellido').value.trim(),
            email: document.getElementById('usuarioEmail').value.trim(),
            password: document.getElementById('usuarioPassword').value,
            role: document.getElementById('usuarioRol').value,
            id_cuadrilla: document.getElementById('usuarioCuadrilla').value,
            estado: document.getElementById('usuarioEstado').value
        };
        const res = await fetchJson(`${USUARIOS_URL}?action=create`, {
            method: 'POST',
            body: JSON.stringify(payload)
        });
        showStatus(document.getElementById('usuariosStatus'), res.message || 'Usuario registrado', !res.success);
        if (res.success) {
            form.reset();
            actualizarCampoCuadrillaUsuario();
            await cargarDatos();
        }
    });

    document.getElementById('crearCuadrillaForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('cuadrillaEditId').value;
        const payload = {
            nombre: document.getElementById('cuadrillaNombre').value,
            id_ruta: document.getElementById('cuadrillaRuta').value,
            turno: document.getElementById('cuadrillaTurno').value,
            estado: document.getElementById('cuadrillaEstado').value
        };
        if (editId) payload.id_cuadrilla = editId;

        try {
            const res = await fetchJson(editId ? `${CUADRILLAS_URL}?action=update` : CUADRILLAS_URL, { method: 'POST', body: JSON.stringify(payload) });
            showStatus(document.getElementById('cuadrillasStatus'), res.message || (editId ? 'Cuadrilla actualizada' : 'Cuadrilla registrada'), !res.success);
            if (res.success !== false) {
                resetFormularioEdicion('crearCuadrillaForm', 'cuadrillaEditId', 'crearCuadrillaBtn', 'cancelarCuadrillaBtn', 'Crear cuadrilla');
                cargarDatos();
            }
        } catch (error) { showStatus(document.getElementById('cuadrillasStatus'), 'Error al guardar cuadrilla', true); }
    });

    document.getElementById('crearCamionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('camionEditId').value;
        const payload = {
            matricula: document.getElementById('camionMatricula').value,
            marca: document.getElementById('camionMarca').value,
            modelo: document.getElementById('camionModelo').value,
            anio: document.getElementById('camionAnio').value,
            kilometraje: document.getElementById('camionKilometraje').value,
            capacidad: document.getElementById('camionCapacidad').value,
            estado: document.getElementById('camionEstado').value,
            id_cuadrilla: document.getElementById('camionCuadrilla').value
        };
        if (editId) payload.id_camion = editId;

        try {
            const res = await fetchJson(editId ? `${CAMIONES_URL}?action=update` : CAMIONES_URL, { method: 'POST', body: JSON.stringify(payload) });
            showStatus(document.getElementById('camionesStatus'), res.message || (editId ? 'Camión actualizado' : 'Camión registrado'), !res.success);
            if (res.success !== false) {
                resetFormularioEdicion('crearCamionForm', 'camionEditId', 'crearCamionBtn', 'cancelarCamionBtn', 'Registrar camión');
                cargarDatos();
            }
        } catch (error) { showStatus(document.getElementById('camionesStatus'), 'Error al guardar camión', true); }
    });

    document.getElementById('crearContenedorForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('contenedorEditId').value;
        const payload = {
            capacidad: document.getElementById('contCapacidad').value,
            nivel_llenado: document.getElementById('contNivel').value || 0,
            id_ubicacion: document.getElementById('contUbicacion').value,
            id_tipo_residuo: document.getElementById('contTipoResiduo').value,
            estado: document.getElementById('contEstado').value
        };
        if (editId) payload.id_contenedor = editId;

        try {
            const res = await fetchJson(editId ? `${CONTENEDORES_URL}?action=update` : CONTENEDORES_URL, { method: 'POST', body: JSON.stringify(payload) });
            showStatus(document.getElementById('contenedoresStatus'), res.message || (editId ? 'Contenedor actualizado' : 'Contenedor registrado'), !res.success);
            if (res.success !== false) {
                resetFormularioEdicion('crearContenedorForm', 'contenedorEditId', 'crearContenedorBtn', 'cancelarContenedorBtn', 'Registrar contenedor');
                cargarDatos();
            }
        } catch (error) { showStatus(document.getElementById('contenedoresStatus'), 'Error al guardar contenedor', true); }
    });

    document.getElementById('crearInstalacionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('instalacionEditId').value;
        const payload = {
            nombre: document.getElementById('instNombre').value,
            calle: document.getElementById('instCalle').value,
            numero: document.getElementById('instNumero').value,
            telefono: document.getElementById('instTelefono').value,
            horario: document.getElementById('instHorario').value,
            capacidad: document.getElementById('instCapacidad').value || 0,
            estado: document.getElementById('instEstado').value
        };
        if (editId) payload.id_instalacion = editId;

        try {
            const res = await fetchJson(editId ? `${INSTALACIONES_URL}?action=update` : INSTALACIONES_URL, { method: 'POST', body: JSON.stringify(payload) });
            showStatus(document.getElementById('instalacionesStatus'), res.message || (editId ? 'Centro actualizado' : 'Instalación registrada'), !res.success);
            if (res.success !== false) {
                resetFormularioEdicion('crearInstalacionForm', 'instalacionEditId', 'crearInstalacionBtn', 'cancelarInstalacionBtn', 'Registrar Centro');
                cargarDatos();
            }
        } catch (error) { showStatus(document.getElementById('instalacionesStatus'), 'Error al guardar centro', true); }
    });

    document.getElementById('crearMaquinariaForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('maquinariaEditId').value;
        const payload = {
            nombre: document.getElementById('maqNombre').value,
            tipo: document.getElementById('maqTipo').value,
            id_instalacion: document.getElementById('maqInstalacion').value,
            estado: document.getElementById('maqEstado').value
        };
        if (editId) payload.id_maquinaria = editId;

        try {
            const res = await fetchJson(editId ? `${MAQUINARIA_URL}?action=update` : MAQUINARIA_URL, { method: 'POST', body: JSON.stringify(payload) });
            showStatus(document.getElementById('maquinariaStatus'), res.message || (editId ? 'Maquinaria actualizada' : 'Maquinaria registrada'), !res.success);
            if (res.success !== false) {
                resetFormularioEdicion('crearMaquinariaForm', 'maquinariaEditId', 'crearMaquinariaBtn', 'cancelarMaquinariaBtn', 'Registrar Maquinaria');
                cargarDatos();
            }
        } catch (error) { showStatus(document.getElementById('maquinariaStatus'), 'Error al guardar maquinaria', true); }
    });

    window.genericDelete = genericDelete;

    // ---------- ARRANQUE ----------
    (async () => {
        const autorizado = await sincronizarSesion();
        if (autorizado) {
            await cargarDatos();
        }
    })();
});
