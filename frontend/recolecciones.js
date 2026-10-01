document.addEventListener('DOMContentLoaded', () => {
    const base = new URL('../backend/api/recolecciones.php', window.location.href).toString();
    const auth = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const form = document.getElementById('recoleccionForm');
    const access = document.getElementById('recoleccionesAcceso');
    const module = document.getElementById('recoleccionesModulo');
    const status = document.getElementById('recoleccionStatus');
    const selects = {
        cuadrilla: document.getElementById('recCuadrilla'),
        camion: document.getElementById('recCamion'),
        ruta: document.getElementById('recRuta'),
        contenedores: document.getElementById('recContenedores')
    };
    const body = document.getElementById('recoleccionesBody');
    let role = 'visitante';
    let currentData = null;

    const escape = (value) => {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    };
    const request = async (url, init = {}) => {
        const response = await fetch(url, {
            ...init,
            credentials: 'include',
            headers: {
                ...(init.method && init.method !== 'GET'
                    ? {'Content-Type': 'application/json', 'X-CSRF-Token': localStorage.getItem('cerberoCsrfToken') || ''}
                    : {}),
                ...(init.headers || {})
            }
        });
        const payload = await response.json();
        if (!response.ok || payload.success === false) throw new Error(payload.message || 'No se pudo completar la operación.');
        if (payload.data?.csrf_token) localStorage.setItem('cerberoCsrfToken', payload.data.csrf_token);
        return payload;
    };
    const options = (select, rows, idKey, label, prompt = 'Seleccioná una opción') => {
        select.innerHTML = `<option value="">${prompt}</option>`
            + rows.map(row => `<option value="${Number(row[idKey])}">${escape(label(row))}</option>`).join('');
    };

    const refreshContainers = () => {
        const routeId = String(selects.ruta.value);
        const containers = routeId
            ? currentData.contenedores.filter(item => String(item.rutas || '').split(',').includes(routeId))
            : [];
        selects.contenedores.innerHTML = containers.map(item =>
            `<option value="${Number(item.id_contenedor)}">Contenedor #${Number(item.id_contenedor)} · ${Math.round(Number(item.nivel_llenado) || 0)} % · ${escape(item.estado)}</option>`
        ).join('');
    };
    const refreshRoute = () => {
        const crew = currentData?.cuadrillas.find(item => Number(item.id_cuadrilla) === Number(selects.cuadrilla.value));
        const routes = crew ? currentData.rutas.filter(item => Number(item.id_ruta) === Number(crew.id_ruta)) : [];
        options(selects.ruta, routes, 'id_ruta', item => item.nombre);
        if (routes.length === 1) selects.ruta.value = String(routes[0].id_ruta);

        const trucks = crew ? currentData.camiones.filter(item => Number(item.id_cuadrilla) === Number(crew.id_cuadrilla)) : [];
        options(selects.camion, trucks, 'id_camion', item => `${item.matricula} · ${item.estado}`);
        if (trucks.length === 1) selects.camion.value = String(trucks[0].id_camion);
        refreshContainers();
    };
    const render = (data) => {
        currentData = data;
        options(selects.cuadrilla, data.cuadrillas, 'id_cuadrilla', item => `${item.nombre} · ${item.turno}`);
        if (role === 'cuadrilla de recolección' && data.cuadrillas.length === 1) {
            selects.cuadrilla.value = String(data.cuadrillas[0].id_cuadrilla);
            selects.cuadrilla.disabled = true;
        }
        refreshRoute();
        body.innerHTML = data.recolecciones.length
            ? data.recolecciones.map(item => `<tr><td>${escape(item.fecha_hora)}</td><td>${escape(item.cuadrilla)}</td><td>${escape(item.camion)}</td><td>${escape(item.ruta || '-')}</td><td>${Number(item.contenedores)}</td><td>${Number(item.cantidad).toFixed(2)}</td></tr>`).join('')
            : '<tr><td colspan="6" class="tabla-vacia">No hay recolecciones registradas.</td></tr>';
    };
    const load = async () => {
        try {
            render((await request(base)).data);
        } catch (error) {
            body.innerHTML = `<tr><td colspan="6" class="tabla-vacia error-text">${escape(error.message)}</td></tr>`;
            status.textContent = error.message;
            status.style.color = '#b91c1c';
        }
    };

    selects.cuadrilla.addEventListener('change', refreshRoute);
    selects.ruta.addEventListener('change', refreshContainers);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        status.style.color = '#2D6A4F';
        status.textContent = 'Guardando...';
        const payload = {
            id_cuadrilla: Number(selects.cuadrilla.value),
            id_camion: Number(selects.camion.value),
            id_ruta: Number(selects.ruta.value),
            cantidad: Number(document.getElementById('recCantidad').value),
            contenedores: [...selects.contenedores.selectedOptions].map(option => Number(option.value)),
            observaciones: document.getElementById('recObservaciones').value
        };
        try {
            await request(base, {method: 'POST', body: JSON.stringify(payload)});
            form.reset();
            await load();
            status.style.color = '#166534';
            status.textContent = 'Recolección registrada correctamente.';
        } catch (error) {
            status.textContent = error.message;
            status.style.color = '#b91c1c';
        } finally {
            submit.disabled = false;
        }
    });
    document.getElementById('recActualizar').addEventListener('click', load);

    (async () => {
        try {
            const payload = await request(auth);
            role = String(payload.data?.role || '').toLowerCase();
            if (!payload.data?.user || !['administrador municipal', 'cuadrilla de recolección'].includes(role)) {
                throw new Error('Acceso exclusivo para administración y cuadrillas.');
            }
            access.hidden = true;
            module.hidden = false;
            await load();
        } catch (error) {
            access.innerHTML = `<h2>Acceso restringido</h2><p>${escape(error.message)}</p>`;
        }
    })();
});
