document.addEventListener('DOMContentLoaded', () => {
    const AUTH_URL = new URL('../backend/api/auth.php?action=me', window.location.href).toString();
    const DASHBOARD_URL = new URL('../backend/api/dashboard.php', window.location.href).toString();
    const OSRM_URL = 'https://router.project-osrm.org/trip/v1/driving';
    const acceso = document.getElementById('rutasAcceso');
    const modulo = document.getElementById('rutasModulo');
    const grid = document.getElementById('rutasGrid');
    const status = document.getElementById('rutasStatus');
    const userPanel = document.getElementById('rutasUserPanel');
    const actualizarBtn = document.getElementById('actualizarRutasBtn');
    const mapas = [];

    const RUTAS = [
        { nombre: 'Ruta 1', color: '#2563eb' },
        { nombre: 'Ruta 2', color: '#dc2626' },
        { nombre: 'Ruta 3', color: '#7c3aed' },
        { nombre: 'Ruta 4', color: '#ea580c' },
        { nombre: 'Ruta 5', color: '#15803d' }
    ];

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const isAdmin = (role) => ['admin', 'administrador', 'administrador municipal']
        .includes(String(role || '').trim().toLowerCase());

    const fetchJson = async (url) => {
        const response = await fetch(url, { credentials: 'include' });
        const text = await response.text();
        let payload;
        try {
            payload = text ? JSON.parse(text) : null;
        } catch (error) {
            throw new Error('El servidor devolvió una respuesta inválida.');
        }
        if (!response.ok || payload?.success === false) {
            throw new Error(payload?.message || `Error del servidor (${response.status}).`);
        }
        return payload;
    };

    const dividirSinRepetidos = (contenedores) => {
        const unicos = [];
        const ids = new Set();
        contenedores.forEach((item) => {
            const id = Number(item.id);
            if (!id || ids.has(id) || item.latitud == null || item.longitud == null) return;
            ids.add(id);
            unicos.push(item);
        });

        if (unicos.length < 50) {
            throw new Error(`Se necesitan 50 contenedores georreferenciados y actualmente hay ${unicos.length}.`);
        }

        // Se agrupan de oeste a este. Cada corte toma diez elementos nuevos,
        // por lo que los cinco recorridos usan exactamente 50 IDs diferentes.
        const ordenados = unicos.slice(0, 50).sort((a, b) => Number(a.longitud) - Number(b.longitud));
        const grupos = RUTAS.map((_, indice) => ordenados.slice(indice * 10, indice * 10 + 10));
        const usados = grupos.flat().map((item) => Number(item.id));
        if (usados.length !== 50 || new Set(usados).size !== 50) {
            throw new Error('Se detectó un contenedor repetido al generar las rutas.');
        }
        return grupos;
    };

    const solicitarRutaVial = async (puntos, centro) => {
        const entradas = [centro, ...puntos];
        const coordenadas = entradas
            .map(punto => `${Number(punto.longitud)},${Number(punto.latitud)}`)
            .join(';');
        const parametros = new URLSearchParams({
            roundtrip: 'true', source: 'first', steps: 'false', geometries: 'geojson', overview: 'full'
        });
        let response;
        try {
            response = await fetch(`${OSRM_URL}/${coordenadas}?${parametros.toString()}`);
        } catch (error) {
            throw new Error('No se pudo conectar con el servicio de rutas viales.');
        }
        const payload = await response.json().catch(() => null);
        if (!response.ok || payload?.code !== 'Ok' || !payload?.trips?.[0]) {
            throw new Error(payload?.message || 'No se pudo calcular el recorrido por calles.');
        }
        const viaje = payload.trips[0];
        const geometria = viaje?.geometry?.coordinates;
        if (!Array.isArray(geometria) || geometria.length < 2 || !Array.isArray(payload.waypoints)) {
            throw new Error('El servicio vial devolvió una ruta incompleta.');
        }
        const orden = puntos.map((punto, indice) => ({
            punto,
            posicion: Number(payload.waypoints[indice + 1]?.waypoint_index ?? indice + 1)
        })).sort((a, b) => a.posicion - b.posicion).map(item => item.punto);
        return {
            puntos: orden,
            coordenadas: geometria.map(coordenada => [Number(coordenada[1]), Number(coordenada[0])]),
            distancia: Number(viaje.distance) / 1000,
            duracionMinutos: Number(viaje.duration) / 60
        };
    };

    const limpiarMapas = () => {
        while (mapas.length) mapas.pop().remove();
        grid.innerHTML = '';
    };

    const crearMapa = async (ruta, puntos, indice, centro) => {
        const card = document.createElement('article');
        card.className = 'ruta-card';
        card.innerHTML = `
            <div class="ruta-card-header">
                <div><span class="ruta-numero" style="--ruta-color:${ruta.color}">${indice + 1}</span><h2>${ruta.nombre}</h2></div>
                <span class="ruta-cantidad" id="rutaResumen${indice + 1}">Calculando por calles...</span>
            </div>
            <div id="rutaMapa${indice + 1}" class="ruta-mapa" aria-label="Mapa de ${ruta.nombre}"></div>
        `;
        grid.appendChild(card);

        const rutaVial = await solicitarRutaVial(puntos, centro);
        document.getElementById(`rutaResumen${indice + 1}`).textContent =
            `10 puntos · ${centro.nombre} · ${rutaVial.distancia.toFixed(1)} km · ${Math.round(rutaVial.duracionMinutos)} min`;

        const mapa = L.map(`rutaMapa${indice + 1}`, { scrollWheelZoom: false });
        mapas.push(mapa);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(mapa);

        const puntoCentro = [Number(centro.latitud), Number(centro.longitud)];
        L.polyline(rutaVial.coordenadas, { color: ruta.color, weight: 4, opacity: 0.86 }).addTo(mapa);

        const nombreCentro = escapeHtml(centro.nombre);
        const detalleCentro = `<strong>Nombre: "${nombreCentro}"</strong><br><span class="centro-tooltip-secondary">Derivado a CERBERO</span>`;
        const iconoCentro = L.divIcon({
            className: 'centro-reciclaje-marker centro-ruta-marker',
            html: `<span aria-label="Centro de reciclaje">♻</span><small>${nombreCentro}</small>`,
            iconSize: [170, 48],
            iconAnchor: [20, 24]
        });
        L.marker(puntoCentro, { icon: iconoCentro, title: centro.nombre })
            .addTo(mapa)
            .bindTooltip(detalleCentro, { direction: 'top', offset: [0, -20], opacity: 0.98 })
            .bindPopup(detalleCentro);

        rutaVial.puntos.forEach((item, posicion) => {
            const detalle = `
                <strong>Punto de recolección</strong><br>
                Punto ${posicion + 1} · Contenedor #${Number(item.id)}<br>
                ${escapeHtml(item.zona || 'Montevideo')}<br>
                Llenado: ${Math.round(Number(item.nivel_llenado || 0))} %
            `;
            L.circleMarker([Number(item.latitud), Number(item.longitud)], {
                radius: 7,
                color: ruta.color,
                weight: 3,
                fillColor: '#ffffff',
                fillOpacity: 1
            }).addTo(mapa).bindTooltip(detalle, { direction: 'top', opacity: 0.98 }).bindPopup(detalle);
        });

        mapa.fitBounds(rutaVial.coordenadas, { padding: [25, 25], maxZoom: 14 });
        return rutaVial;
    };

    const cargarRutas = async () => {
        limpiarMapas();
        status.textContent = 'Calculando las cinco rutas sobre la red de calles...';
        status.style.color = '#2D6A4F';
        try {
            const payload = await fetchJson(DASHBOARD_URL);
            const grupos = dividirSinRepetidos(payload?.data?.contenedores || []);
            const centrosPorRuta = new Map((payload?.data?.rutas_centros || []).map((centro) => [centro.ruta_nombre, centro]));
            const resultados = [];
            for (let indice = 0; indice < grupos.length; indice += 1) {
                const puntos = grupos[indice];
                const centro = centrosPorRuta.get(RUTAS[indice].nombre);
                if (!centro) throw new Error(`No hay un centro de acopio asignado a ${RUTAS[indice].nombre}.`);
                resultados.push(await crearMapa(RUTAS[indice], puntos, indice, centro));
            }
            const distanciaTotal = resultados.reduce((total, ruta) => total + ruta.distancia, 0);
            status.textContent = `5 rutas viales generadas · ${distanciaTotal.toFixed(1)} km totales · salida y regreso al mismo centro · 50 contenedores · 0 repetidos.`;
        } catch (error) {
            status.textContent = error.message;
            status.style.color = '#b91c1c';
            grid.innerHTML = '<div class="rutas-error">No se pudieron generar las rutas.</div>';
        }
    };

    actualizarBtn.addEventListener('click', cargarRutas);

    const verificarAcceso = async () => {
        try {
            const payload = await fetchJson(AUTH_URL);
            const user = payload?.data?.user || null;
            const role = payload?.data?.role || user?.role || 'visitante';
            if (!user || !isAdmin(role)) {
                userPanel.innerHTML = '<span class="pill">Acceso restringido</span>';
                acceso.innerHTML = '<h2>Acceso exclusivo para administradores</h2><p>Las rutas, cuadrillas, turnos y centros de destino forman parte de la operación interna de CERBERO.</p><div class="incidencias-access-actions"><a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a><a class="auth-btn secondary" href="index.html">Volver a Principal</a></div>';
                return;
            }

            const nombre = `${user.nombre || ''} ${user.apellido || ''}`.trim();
            userPanel.innerHTML = `<div class="user-details"><strong>${escapeHtml(nombre)}</strong><p>Administrador municipal</p></div>`;
            acceso.hidden = true;
            modulo.hidden = false;
            await cargarRutas();
        } catch (error) {
            acceso.innerHTML = `<h2>No se pudo verificar el acceso</h2><p>${escapeHtml(error.message)}</p><div class="incidencias-access-actions"><a class="auth-btn" href="login.html?mode=login">Iniciar sesión</a><a class="auth-btn secondary" href="index.html">Volver a Principal</a></div>`;
        }
    };

    verificarAcceso();
});
