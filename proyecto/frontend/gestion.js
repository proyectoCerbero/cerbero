document.addEventListener('DOMContentLoaded', () => {
    const USUARIOS_URL = new URL('../backend/api/usuarios.php', window.location.href).toString();
    const CUADRILLAS_URL = new URL('../backend/api/cuadrillas.php', window.location.href).toString();
    const CAMIONES_URL = new URL('../backend/api/camiones.php', window.location.href).toString();

    const ROLE_OPTIONS = [
        { value: 'vecino', label: 'Vecino' },
        { value: 'cuadrilla de recolección', label: 'Cuadrilla de recolección' },
        { value: 'operario de centro', label: 'Operario de centro' },
        { value: 'administrador municipal', label: 'Administrador municipal' }
    ];
    const ESTADOS_USUARIO = ['activo', 'inactivo', 'suspendido'];

    const usuariosStatus = document.getElementById('usuariosStatus');
    const usuariosBody = document.getElementById('usuariosTablaBody');
    const cuadrillasStatus = document.getElementById('cuadrillasStatus');
    const cuadrillasBody = document.getElementById('cuadrillasTablaBody');
    const crearCuadrillaForm = document.getElementById('crearCuadrillaForm');
    const crearCuadrillaBtn = document.getElementById('crearCuadrillaBtn');

    const camionesStatus = document.getElementById('camionesStatus');
    const camionesBody = document.getElementById('camionesTablaBody');
    const crearCamionForm = document.getElementById('crearCamionForm');
    const crearCamionBtn = document.getElementById('crearCamionBtn');
    const camionCuadrillaSelect = document.getElementById('camionCuadrilla');

    const currentUser = JSON.parse(localStorage.getItem('cerberoUser') || 'null');
    const viewSelector = document.getElementById('gestionViewSelector');
    const isExampleAdminView = () => viewSelector?.value === 'admin';
    const isCurrentUserAdmin = () => isExampleAdminView() || String(currentUser?.role || '').trim().toLowerCase() === 'administrador municipal' || String(currentUser?.role || '').trim().toLowerCase() === 'admin';

    const normalizeRoleValue = (roleName) => {
        const value = String(roleName || '').trim().toLowerCase();
        if (['admin', 'administrador', 'administrador municipal'].includes(value)) return 'administrador municipal';
        if (['cuadrilla', 'cuadrilla de recoleccion', 'cuadrilla de recolección'].includes(value)) return 'cuadrilla de recolección';
        if (['operario', 'operario de centro'].includes(value)) return 'operario de centro';
        if (value === 'vecino') return 'vecino';
        return value;
    };

    const showStatus = (el, message, isError = false) => {
        el.textContent = message;
        el.style.color = isError ? 'crimson' : 'green';
    };

    const fetchJson = async (url, init) => {
        const response = await fetch(url, init);
        const text = await response.text();

        let payload = null;
        try {
            payload = text ? JSON.parse(text) : null;
        } catch (error) {
            throw new Error(`Respuesta no válida del servidor (${response.status}): ${text}`);
        }

        if (!payload) {
            throw new Error(`El servidor respondió ${response.status} sin contenido.`);
        }

        return payload;
    };

    // ---------- USUARIOS ----------

    const renderUsuarios = (usuarios) => {
        if (!usuarios.length) {
            usuariosBody.innerHTML = '<tr><td colspan="6" class="tabla-vacia">No hay usuarios registrados todavía.</td></tr>';
            return;
        }

        usuariosBody.innerHTML = usuarios.map((usuario) => {
            const rolActual = normalizeRoleValue(usuario.role || 'vecino');
            const estadoActual = usuario.estado || 'activo';
            const badgeClass = estadoActual === 'activo' ? 'badge-activo' : (estadoActual === 'suspendido' ? 'badge-suspendido' : 'badge-inactivo');
            const proximoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';
            const textoBoton = estadoActual === 'activo' ? 'Desactivar' : 'Activar';
            const claseBoton = estadoActual === 'activo' ? 'btn-accion peligro' : 'btn-accion';
            const opcionesRol = ROLE_OPTIONS.map((rol) => (
                `<option value="${rol.value}" ${rol.value === rolActual ? 'selected' : ''}>${rol.label}</option>`
            )).join('');
            const puedeEditarRol = isCurrentUserAdmin();
            const puedeCambiarEstado = isCurrentUserAdmin();

            return `
                <tr data-ci="${usuario.ci}">
                    <td>${usuario.ci}</td>
                    <td>${usuario.nombre} ${usuario.apellido}</td>
                    <td>${usuario.email}</td>
                    <td>
                        ${puedeEditarRol
                            ? `<select class="rol-select">${opcionesRol}</select>`
                            : `<span class="badge">${ROLE_OPTIONS.find((rol) => rol.value === rolActual)?.label || rolActual}</span>`}
                    </td>
                    <td><span class="badge ${badgeClass}">${estadoActual}</span></td>
                    <td>
                        <div class="acciones-cell">
                            ${puedeEditarRol ? '<button type="button" class="btn-accion btn-guardar-rol">Guardar rol</button>' : ''}
                            ${puedeCambiarEstado
                                ? `<button type="button" class="${claseBoton} btn-toggle-estado" data-proximo-estado="${proximoEstado}">${textoBoton}</button>`
                                : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const cargarUsuarios = async () => {
        try {
            const result = await fetchJson(USUARIOS_URL);
            if (!result.success) throw new Error(result.message);
            renderUsuarios(result.data.usuarios || []);
        } catch (error) {
            usuariosBody.innerHTML = '<tr><td colspan="6" class="tabla-vacia">No se pudieron cargar los usuarios.</td></tr>';
            showStatus(usuariosStatus, error.message || 'Error al cargar usuarios.', true);
        }
    };

    usuariosBody.addEventListener('click', async (event) => {
        const fila = event.target.closest('tr[data-ci]');
        if (!fila) return;
        const ci = fila.dataset.ci;

        // Botón: guardar rol seleccionado
        if (event.target.classList.contains('btn-guardar-rol')) {
            if (!isCurrentUserAdmin()) {
                showStatus(usuariosStatus, 'Solo un administrador puede cambiar roles.', true);
                return;
            }

            const rolSeleccionado = fila.querySelector('.rol-select').value;
            const boton = event.target;
            boton.disabled = true;
            showStatus(usuariosStatus, 'Actualizando rol...');

            try {
                const result = await fetchJson(`${USUARIOS_URL}?action=rol`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ci, rol: rolSeleccionado, requester_role: currentUser?.role || '' })
                });

                showStatus(usuariosStatus, result.message, !result.success);
                if (result.success) await cargarUsuarios();
            } catch (error) {
                showStatus(usuariosStatus, error.message || 'No se pudo actualizar el rol.', true);
            } finally {
                boton.disabled = false;
            }
        }

        // Botón: activar/desactivar
        if (event.target.classList.contains('btn-toggle-estado')) {
            if (!isCurrentUserAdmin()) {
                showStatus(usuariosStatus, 'Solo un administrador puede activar o desactivar usuarios.', true);
                return;
            }

            const boton = event.target;
            const proximoEstado = boton.dataset.proximoEstado;
            boton.disabled = true;
            showStatus(usuariosStatus, 'Actualizando estado...');

            try {
                const result = await fetchJson(`${USUARIOS_URL}?action=estado`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ci, estado: proximoEstado })
                });

                showStatus(usuariosStatus, result.message, !result.success);
                if (result.success) await cargarUsuarios();
            } catch (error) {
                showStatus(usuariosStatus, error.message || 'No se pudo actualizar el estado.', true);
            } finally {
                boton.disabled = false;
            }
        }
    });

    // ---------- CUADRILLAS ----------

    const renderCuadrillas = (cuadrillas) => {
        if (!cuadrillas.length) {
            cuadrillasBody.innerHTML = '<tr><td colspan="4" class="tabla-vacia">No hay cuadrillas creadas todavía.</td></tr>';
        } else {
            cuadrillasBody.innerHTML = cuadrillas.map((cuadrilla) => `
                <tr>
                    <td>${cuadrilla.id_cuadrilla}</td>
                    <td>${cuadrilla.nombre}</td>
                    <td>${cuadrilla.turno ?? '-'}</td>
                    <td>${cuadrilla.estado ?? '-'}</td>
                </tr>
            `).join('');
        }

        if (crearCuadrillaForm) {
            crearCuadrillaForm.style.display = isCurrentUserAdmin() ? 'grid' : 'none';
        }
        if (crearCuadrillaBtn) {
            crearCuadrillaBtn.disabled = !isCurrentUserAdmin();
        }

        // Mantiene sincronizado el <select> de cuadrillas del formulario de camiones.
        if (camionCuadrillaSelect) {
            const seleccionActual = camionCuadrillaSelect.value;
            const opciones = cuadrillas.map((cuadrilla) => (
                `<option value="${cuadrilla.id_cuadrilla}">${cuadrilla.nombre}</option>`
            )).join('');
            camionCuadrillaSelect.innerHTML = `<option value="">Sin asignar</option>${opciones}`;
            camionCuadrillaSelect.value = seleccionActual;
        }
    };

    const cargarCuadrillas = async () => {
        try {
            const result = await fetchJson(CUADRILLAS_URL);
            if (!result.success) throw new Error(result.message);
            renderCuadrillas(result.data.cuadrillas || []);
        } catch (error) {
            cuadrillasBody.innerHTML = '<tr><td colspan="4" class="tabla-vacia">No se pudieron cargar las cuadrillas.</td></tr>';
            showStatus(cuadrillasStatus, error.message || 'Error al cargar cuadrillas.', true);
        }
    };

    crearCuadrillaForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!isCurrentUserAdmin()) {
            showStatus(cuadrillasStatus, 'Solo un administrador puede crear cuadrillas.', true);
            return;
        }

        const nombre = document.getElementById('cuadrillaNombre').value.trim();
        const turno = document.getElementById('cuadrillaTurno').value;
        const estado = document.getElementById('cuadrillaEstado').value;

        if (!nombre) {
            showStatus(cuadrillasStatus, 'El nombre de la cuadrilla es obligatorio.', true);
            return;
        }

        crearCuadrillaBtn.disabled = true;
        showStatus(cuadrillasStatus, 'Creando cuadrilla...');

        try {
            const result = await fetchJson(CUADRILLAS_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre, turno, estado })
            });

            showStatus(cuadrillasStatus, result.message, !result.success);

            if (result.success) {
                crearCuadrillaForm.reset();
                await cargarCuadrillas();
            }
        } catch (error) {
            showStatus(cuadrillasStatus, error.message || 'No se pudo crear la cuadrilla.', true);
        } finally {
            crearCuadrillaBtn.disabled = false;
        }
    });

    // ---------- CAMIONES ----------

    const renderCamiones = (camiones) => {
        if (!camiones.length) {
            camionesBody.innerHTML = '<tr><td colspan="8" class="tabla-vacia">No hay camiones registrados todavía.</td></tr>';
        } else {
            camionesBody.innerHTML = camiones.map((camion) => `
                <tr>
                    <td>${camion.id_camion}</td>
                    <td>${camion.matricula}</td>
                    <td>${camion.marca} ${camion.modelo}</td>
                    <td>${camion.anio}</td>
                    <td>${camion.kilometraje ?? 0}</td>
                    <td>${camion.capacidad ?? 0}</td>
                    <td><span class="badge ${camion.estado === 'activo' ? 'badge-activo' : (camion.estado === 'mantenimiento' ? 'badge-inactivo' : 'badge-suspendido')}">${camion.estado ?? '-'}</span></td>
                    <td>${camion.cuadrilla_nombre ?? 'Sin asignar'}</td>
                </tr>
            `).join('');
        }

        if (crearCamionForm) {
            crearCamionForm.style.display = isCurrentUserAdmin() ? 'grid' : 'none';
        }
        if (crearCamionBtn) {
            crearCamionBtn.disabled = !isCurrentUserAdmin();
        }
        if (camionCuadrillaSelect) {
            camionCuadrillaSelect.disabled = !isCurrentUserAdmin();
        }
    };

    const cargarCamiones = async () => {
        try {
            const result = await fetchJson(CAMIONES_URL);
            if (!result.success) throw new Error(result.message);
            renderCamiones(result.data.camiones || []);
        } catch (error) {
            camionesBody.innerHTML = '<tr><td colspan="8" class="tabla-vacia">No se pudieron cargar los camiones.</td></tr>';
            showStatus(camionesStatus, error.message || 'Error al cargar camiones.', true);
        }
    };

    crearCamionForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!isCurrentUserAdmin()) {
            showStatus(camionesStatus, 'Solo un administrador puede crear camiones.', true);
            return;
        }

        const matricula = document.getElementById('camionMatricula').value.trim();
        const marca = document.getElementById('camionMarca').value.trim();
        const modelo = document.getElementById('camionModelo').value.trim();
        const anio = document.getElementById('camionAnio').value;
        const kilometraje = document.getElementById('camionKilometraje').value || 0;
        const capacidad = document.getElementById('camionCapacidad').value || 0;
        const estado = document.getElementById('camionEstado').value;
        const idCuadrilla = camionCuadrillaSelect.value;

        if (!matricula || !marca || !modelo || !anio) {
            showStatus(camionesStatus, 'Matrícula, marca, modelo y año son obligatorios.', true);
            return;
        }

        crearCamionBtn.disabled = true;
        showStatus(camionesStatus, 'Creando camión...');

        try {
            const result = await fetchJson(CAMIONES_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    matricula,
                    marca,
                    modelo,
                    anio: Number(anio),
                    kilometraje: Number(kilometraje),
                    capacidad: Number(capacidad),
                    estado,
                    id_cuadrilla: idCuadrilla || null
                })
            });

            showStatus(camionesStatus, result.message, !result.success);

            if (result.success) {
                crearCamionForm.reset();
                await cargarCamiones();
            }
        } catch (error) {
            showStatus(camionesStatus, error.message || 'No se pudo crear el camión.', true);
        } finally {
            crearCamionBtn.disabled = false;
        }
    });

    if (viewSelector) {
        viewSelector.addEventListener('change', async () => {
            await cargarUsuarios();
            await cargarCuadrillas();
            await cargarCamiones();
        });
    }

    cargarUsuarios();
    cargarCuadrillas();
    cargarCamiones();
});
