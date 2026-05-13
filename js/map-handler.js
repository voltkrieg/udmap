
let map2d, map3d;
let markers2D = {};
let markers3D = {};
const centerCoords = [14.32464, 120.96016];
let routeLayers2D = []; // Updated to an array to hold multiple paths
let userMarker2D = null;
let userMarker3D = null;

const typeColors = {
    'food': '#f39c12','services': '#3498db', 'venue': '#9b59b6', 'class': '#2ecc71'
};

const typeIcons = {
    'food': 'bi-cup-hot-fill',    
    'service': 'bi-tools',        
    'venue': 'bi-star-fill',      
    'class': 'bi-book-fill'       
};

function createPopupContent(loc) {
    let title = loc.LONGNAME ? loc.LONGNAME : loc.NAME;
    let bldgType = loc.BLDGTYPE || 'Building';
    return `
        <div style="font-family: 'Nunito', sans-serif; min-width: 180px;">
            <h6 class="mb-1 fw-bold text-success">${title}</h6>
            <p class="mb-2 text-muted small" style="text-transform: capitalize;"><i class="bi ${loc.ICON || 'bi-geo-alt'} me-1"></i>${bldgType}</p>
            <div class="d-flex gap-2 border-top pt-2">
                <button class="btn btn-sm btn-outline-primary w-50" onclick="getRoute(${loc.LATITUDE}, ${loc.LONGITUDE}, 'foot')"><i class="bi bi-person-walking"></i> Walk</button>
                <button class="btn btn-sm btn-outline-success w-50" onclick="getRoute(${loc.LATITUDE}, ${loc.LONGITUDE}, 'driving')"><i class="bi bi-car-front"></i> Drive</button>
            </div>
        </div>
    `;
}

async function initMapSystem(filterType = '') {
    const response = await fetch(`api/locations.php?type=${encodeURIComponent(filterType)}`);
    const locations = await response.json();

    const container2D = document.getElementById('mapContainer2D');
    if (container2D) {
        map2d = L.map('mapContainer2D').setView(centerCoords, 18);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map2d);
    }

    const container3D = document.getElementById('mapContainer3D');
    if (container3D) {
        map3d = new maplibregl.Map({
            container: 'mapContainer3D',
            style: 'https://tiles.openfreemap.org/styles/liberty',
            center: [centerCoords[1], centerCoords[0]],
            zoom: 17, pitch: 60, bearing: -20, antialias: true
        });

        map3d.on('load', () => {
            const layers = map3d.getStyle().layers;
            let labelLayerId;
            for (let i = 0; i < layers.length; i++) {
                if (layers[i].type === 'symbol' && layers[i].layout['text-field']) { labelLayerId = layers[i].id; break; }
            }
            map3d.addLayer({
                'id': 'add-3d-buildings',
                'source': 'openmaptiles',
                'source-layer': 'building',
                'type': 'fill-extrusion',
                'minzoom': 15,
                'paint': {
                    'fill-extrusion-color': '#ccc',
                    'fill-extrusion-height': ['get', 'render_height'],
                    'fill-extrusion-base': ['get', 'render_min_height'],
                    'fill-extrusion-opacity': 0.7
                }
            }, labelLayerId);
        });
    }

    locations.forEach(loc => {
        const type = loc.BLDGTYPE ? loc.BLDGTYPE.toLowerCase().trim() : '';
        console.log("Building Name:", loc.NAME, "| Type string is:", type); 
        const pinColor = typeColors[type] || '#555555';
        const pinIcon = loc.ICON || typeIcons[type] || 'bi-geo-alt-fill';
        const key = loc.NAME.toLowerCase();
        const iconHtml = `<div style="background:${pinColor}; width:28px; height:28px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; color:white; font-size:14px; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer; transition: transform 0.2s;"><i class="bi ${pinIcon}"></i></div>`;



        if (map2d) {
            markers2D[key] = L.marker([loc.LATITUDE, loc.LONGITUDE], {
                icon: L.divIcon({ html: iconHtml, className: '', iconSize: [28, 28], iconAnchor: [14, 14] })
            }).addTo(map2d).bindPopup(createPopupContent(loc));
        }

        if (map3d) {
            const el = document.createElement('div');
            el.innerHTML = iconHtml;
            el.addEventListener('mouseenter', () => el.style.transform = 'scale(1.2)');
            el.addEventListener('mouseleave', () => el.style.transform = 'scale(1)');
            markers3D[key] = new maplibregl.Marker(el)
                .setLngLat([loc.LONGITUDE, loc.LATITUDE])
                .setPopup(new maplibregl.Popup({ offset: 25 }).setHTML(createPopupContent(loc)))
                .addTo(map3d);
        }
    });
}
async function logRouteSearchToDB(destLat, destLng, profile) {
    try {
        await fetch('api/log_route.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                lat: destLat, 
                lng: destLng, 
                profile: profile 
            })
        });
    } catch (err) {
        console.error("Failed to log route search:", err);
    }
}

window.getRoute = function(destLat, destLng, profile) {
    if (!navigator.geolocation) return alert("Geolocation is not supported by this browser.");
    
    logRouteSearchToDB(destLat, destLng, profile);

    navigator.geolocation.getCurrentPosition(
        pos => fetchAndDrawRoute(pos.coords.longitude, pos.coords.latitude, destLng, destLat, profile),
        err => alert("Unable to retrieve your location.")
    );
};
function fetchAndDrawRoute(startLng, startLat, endLng, endLat, profile) {
    const url = `https://router.project-osrm.org/route/v1/${profile}/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson&radiuses=unlimited;unlimited&alternatives=true`;

    fetch(url)
    .then(res => res.json())
    .then(data => {
        if(data.routes && data.routes.length > 0) {
            if(map2d) drawRoutes2D(data.routes, startLat, startLng);
            if(map3d) drawRoutes3D(data.routes, startLat, startLng);
            
            if(map2d) map2d.closePopup();
            document.querySelectorAll('.maplibregl-popup').forEach(p => p.remove());

            const bestRoute = data.routes[0];
            const durationMins = Math.ceil(bestRoute.duration / 60);
            const distanceKm = (bestRoute.distance / 1000).toFixed(2);
            showRouteInfo(profile, durationMins, distanceKm, data.routes.length);
        } else {
            drawDirectFallback(startLng, startLat, endLng, endLat, profile);
        }
    }).catch(err => {
        console.error("Routing API error:", err);
        drawDirectFallback(startLng, startLat, endLng, endLat, profile);
    });
}

function drawDirectFallback(startLng, startLat, endLng, endLat, profile) {
    const mockRoute = { geometry: { coordinates: [[startLng, startLat], [endLng, endLat]] } };
    if(map2d) drawRoutes2D([mockRoute], startLat, startLng);
    if(map3d) drawRoutes3D([mockRoute], startLat, startLng);
    
    if(map2d) map2d.closePopup();
    document.querySelectorAll('.maplibregl-popup').forEach(p => p.remove());
    showRouteInfo(profile, 0, 0, 1); // Indicate direct line fallback
}

function drawRoutes2D(routes, userLat, userLng) {
    routeLayers2D.forEach(layer => map2d.removeLayer(layer));
    routeLayers2D = [];
    if (userMarker2D) map2d.removeLayer(userMarker2D);

    for (let i = routes.length - 1; i >= 0; i--) {
        const coords = routes[i].geometry.coordinates;
        const latlngs = coords.map(c => [c[1], c[0]]);
        const isMain = i === 0;
        
        const polyline = L.polyline(latlngs, {
            color: isMain ? '#0d6efd' : '#6c757d',
            weight: isMain ? 6 : 4,
            opacity: isMain ? 0.8 : 0.6,
            dashArray: isMain ? null : '5, 10'
        }).addTo(map2d);
        
        routeLayers2D.push(polyline);
    }

    userMarker2D = L.marker([userLat, userLng], { icon: L.divIcon({ html: '<div style="background:#dc3545; width:16px; height:16px; border-radius:50%; border:2px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>', className: '' }) }).addTo(map2d).bindPopup("Your Location");
    
    if (routeLayers2D.length > 0) {
        map2d.fitBounds(routeLayers2D[routeLayers2D.length - 1].getBounds(), { padding: [50, 50] }); // Fit to main route
    }
}

function drawRoutes3D(routes, userLat, userLng) {
    if (userMarker3D) userMarker3D.remove();
    const userEl = document.createElement('div');
    userEl.style.cssText = 'background:#dc3545; width:16px; height:16px; border-radius:50%; border:2px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);';
    userMarker3D = new maplibregl.Marker(userEl).setLngLat([userLng, userLat]).addTo(map3d);

    const features = routes.map((route, i) => {
        const isMain = i === 0;
        return {
            type: 'Feature',
            properties: { color: isMain ? '#0d6efd' : '#6c757d', width: isMain ? 6 : 4 },
            geometry: { type: 'LineString', coordinates: route.geometry.coordinates }
        };
    });

    if (map3d.getSource('routes')) {
        map3d.getSource('routes').setData({ type: 'FeatureCollection', features });
    } else {
        map3d.addSource('routes', { type: 'geojson', data: { type: 'FeatureCollection', features } });
        map3d.addLayer({
            id: 'routes-layer',
            type: 'line',
            source: 'routes',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: {
                'line-color': ['get', 'color'],
                'line-width': ['get', 'width'],
                'line-opacity': 0.8
            }
        });
    }

    const mainCoords = routes[0].geometry.coordinates;
    const bounds = mainCoords.reduce((b, c) => b.extend(c), new maplibregl.LngLatBounds(mainCoords[0], mainCoords[0]));
    map3d.fitBounds(bounds, { padding: 50 });
}

function showRouteInfo(profile, mins, km, pathsCount) {
    let infoBox = document.getElementById('udmap-route-info');
    if (!infoBox) {
        infoBox = document.createElement('div');
        infoBox.id = 'udmap-route-info';
        document.body.appendChild(infoBox);
    }
    
    let mode = profile === 'foot' ? 'Walking' : 'Driving';
    let icon = profile === 'foot' ? 'bi-person-walking' : 'bi-car-front';
    
    let statsHtml = mins > 0 
        ? `<div class="mb-1"><i class="bi bi-clock me-1"></i><strong>Time:</strong> ~${mins} min${mins !== 1 ? 's' : ''}</div>
           <div class="mb-1"><i class="bi bi-signpost-split me-1"></i><strong>Distance:</strong> ${km} km</div>
           <div class="mb-1 small text-muted mt-2"><i class="bi bi-geo-alt me-1"></i>Found ${pathsCount} possible path(s)</div>`
        : `<div class="mb-1 text-warning small">Direct line fallback. Unable to find distinct path times.</div>`;

    infoBox.innerHTML = `
        <div style="background: rgba(2, 36, 21, 0.95); border: 1px solid var(--border-glow, #b6ff92); padding: 15px; border-radius: 12px; color: white; box-shadow: 0 4px 20px rgba(0,0,0,0.8); backdrop-filter: blur(5px); font-family: 'Nunito', sans-serif; min-width: 220px;">
            <h6 style="color: var(--star-gold, #ffda6c); margin-bottom: 12px; font-family: 'Cinzel', serif; border-bottom: 1px dashed rgba(255,218,108,0.3); padding-bottom: 8px;">
                <i class="bi ${icon} me-2"></i> Route Summary
            </h6>
            <div style="font-size: 0.95rem;">
                <div class="mb-2"><strong style="color: var(--firefly-glow, #b6ff92);">${mode}</strong></div>
                ${statsHtml}
            </div>
            <button onclick="document.getElementById('udmap-route-info').style.display='none'" class="btn btn-sm w-100 mt-2" style="background: rgba(182, 255, 146, 0.1); border: 1px solid var(--firefly-glow, #b6ff92); color: var(--firefly-glow, #b6ff92); border-radius: 6px;">Dismiss</button>
        </div>
    `;
    
    infoBox.style.display = 'block';
    infoBox.style.position = 'fixed';
    infoBox.style.bottom = '30px';
    infoBox.style.right = '30px';
    infoBox.style.zIndex = '1050'; 
}

window.focusLocation = function(placeName) {
    const key = placeName.toLowerCase();
    const tab3d = document.getElementById('view-3d-tab');
    const is3DActive = tab3d && tab3d.classList.contains('active');
    
    const found2D = Object.keys(markers2D).find(k => key.includes(k) || k.includes(key));
    const found3D = Object.keys(markers3D).find(k => key.includes(k) || k.includes(key));

    if (is3DActive && found3D && markers3D[found3D]) {
        map3d.flyTo({ center: markers3D[found3D].getLngLat(), zoom: 19 });
        markers3D[found3D].togglePopup();
    } else if (!is3DActive && found2D && markers2D[found2D]) {
        map2d.setView(markers2D[found2D].getLatLng(), 19);
        markers2D[found2D].openPopup();
    } else {
        console.warn("Location not found on map:", placeName);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    initMapSystem();
    initMiniCompass();
    initSearchBar();
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', () => {
            if (map2d) map2d.invalidateSize();
            if (map3d) map3d.resize();
        });
    });

    fetchSchedule();
    fetchEvents();
});

async function fetchSchedule() {
    const container = document.getElementById('scheduleContainer');
    if (!container) return; 

    try {
        const response = await fetch('schedload.php');
        const result = await response.json();
        
        if (result.status === 'success' && result.data.length > 0) {
            let html = '';
            let currentDay = '';
            
            result.data.forEach(item => {
                if (currentDay !== item.day) {
                    currentDay = item.day;
                    html += `<div class="day-header">${currentDay}</div>`;
                }
                
                html += `
                <div class="class-item" onclick="focusLocation('${item.subject}')">
                    <div>
                        <div class="fw-bold" style="color: var(--star-gold);">${item.fullsub}</div>
                        <div class="small mt-1">
                            <span class="fw-bold" style="color: var(--firefly-glow);">${item.subject}</span> 
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-6">${item.timestart_formatted}</div>
                        <div class="text-muted small">to ${item.timeend_formatted}</div>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-muted small text-center py-5">No active quests found.</p>';
        }
    } catch (error) {
        console.error("Error loading schedule:", error);
        container.innerHTML = '<p class="text-danger small text-center py-5">Error communicating with server.</p>';
    }
}


async function fetchEvents() {
    const container = document.getElementById('eventContainer');
    if (!container) return;

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    try {
        const response = await fetch('eventload.php');
        const result = await response.json();
        
        if (result.status === 'success' && result.data.length > 0) {
            let html = '';
            
            result.data.forEach(ev => {
                html += `
                <div class="event-item border-bottom mb-2 pb-2" onclick="focusLocation('${ev.location_name}')">
                    <div class="fw-bold small" style="color: var(--star-gold);">
                        <i class="bi bi-calendar-event me-1"></i> ${ev.date_formatted}
                    </div>
                    <div class="fw-bold fs-6 mt-1 text-white">${ev.eventname}</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="small text-muted"><i class="bi bi-geo-alt me-1"></i>${ev.location_name || 'TBA'}</span>
                        <span class="small fw-bold" style="color: var(--firefly-glow);">${ev.time_formatted}</span>
                    </div>
                    <form action="eventguide.php" method="POST" class="mt-3">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="eventtitle" value="${ev.eventname}">
                        <button type="submit" class="btn btn-quest w-100 py-1" style="font-size: 0.75rem;">View Details</button>
                    </form>
                </div>`;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-muted small text-center py-5">The realm is quiet.</p>';
        }
    } catch (error) {
        console.error("Error loading events:", error);
        container.innerHTML = '<p class="text-danger small text-center py-5">Error communicating with server.</p>';
    }
}

window.triggerRouting = function(profile) {
    const selectBox = document.getElementById('destinationSelect');
    if (!selectBox) return; 
    
    const coords = selectBox.value;

    if (!coords) {
        alert("Please select a destination first.");
        return;
    }

    const [lat, lng] = coords.split(',');

    if(typeof window.getRoute === 'function') {
        window.getRoute(parseFloat(lat), parseFloat(lng), profile);
    } else {
        alert("Routing system is not initialized.");
    }
};

function initMiniCompass() {
    const style = document.createElement('style');
    style.innerHTML = `
        #udmap-mini-compass {
            position: absolute; /* Locks it to the map container */
            top: 20px; 
            left: 70px; /* Offset to prevent covering the zoom buttons */
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 20px rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1040;
            backdrop-filter: blur(5px);
            cursor: pointer;
            transition: transform 0.2s;
        }
        #udmap-mini-compass:hover { transform: scale(1.05); }
        
        .top-indicator-mini {
            position: absolute;
            top: -6px;
            width: 10px; height: 12px;
            z-index: 110;
        }
        .top-indicator-mini::before {
            content: ''; position: absolute;
            border-left: 5px solid transparent; border-right: 5px solid transparent;
            border-top: 12px solid var(--star-red, #ff3131);
            clip-path: polygon(50% 0, 50% 100%, 0 0);
        }
        .top-indicator-mini::after {
            content: ''; position: absolute;
            border-left: 5px solid transparent; border-right: 5px solid transparent;
            border-top: 12px solid #b31d1d;
            clip-path: polygon(50% 0, 100% 0, 50% 100%);
        }

        .rose-wrapper-mini {
            position: relative;
            width: 75px; height: 75px;
            z-index: 10;
            transition: transform 0.15s linear; /* Smooth rotation */
        }

        .compass-rose-mini {
            width: 100%; height: 100%;
            background: conic-gradient(
                from 0deg,
                #d72d2d 0deg 22.5deg, #ffda6c 22.5deg 45deg,
                #035208 45deg 67.5deg, #0c2802 67.5deg 90deg,
                #ffffff 90deg 112.5deg, #bbb 112.5deg 135deg,
                #035208 135deg 157.5deg, #0c2802 157.5deg 180deg,
                #fff 180deg 202.5deg, #bbb 202.5deg 225deg,
                #035208 225deg 247.5deg, #0c2802 247.5deg 270deg,
                #fff 270deg 292.5deg, #bbb 292.5deg 315deg,
                #035208 315deg 337.5deg, #0c2802 360deg
            );
            clip-path: polygon(
                50% 0%, 54% 37%, 85% 15%, 63% 47%,
                100% 50%, 63% 53%, 85% 85%, 54% 63%,
                50% 100%, 46% 63%, 15% 85%, 37% 53%,
                0% 50%, 37% 47%, 15% 15%, 46% 37%
            );
        }

        .label-mini {
            position: absolute;
            font-weight: 900; font-size: 10px; color: white;
            text-shadow: 0 0 5px rgba(0,0,0,1); z-index: 20;
            font-family: 'Cinzel', serif;
        }
        .lbl-n-mini { top: -12px; left: 50%; transform: translateX(-50%); color: var(--star-red, #ff3131); }
        .lbl-s-mini { bottom: -12px; left: 50%; transform: translateX(-50%); }
        .lbl-e-mini { right: -12px; top: 50%; transform: translateY(-50%); }
        .lbl-w-mini { left: -12px; top: 50%; transform: translateY(-50%); }
        
        .compass-tooltip {
            position: absolute; top: 40px; left: 110px;
            background: rgba(0,0,0,0.8); color: var(--firefly-glow, #b6ff92);
            font-size: 10px; padding: 4px 8px; border-radius: 4px;
            border: 1px solid var(--border-glow, #b6ff92);
            white-space: nowrap; opacity: 0; transition: 0.3s; pointer-events: none;
        }
        #udmap-mini-compass:hover .compass-tooltip { opacity: 1; }
    `;
    document.head.appendChild(style);

    // 2. Inject the HTML
    const compassDiv = document.createElement('div');
    compassDiv.id = 'udmap-mini-compass';
    compassDiv.innerHTML = `
        <div class="compass-tooltip">Tap to calibrate</div>
        <div class="top-indicator-mini"></div>
        <div class="rose-wrapper-mini" id="mini-rose">
            <div class="compass-rose-mini"></div>
            <div class="label-mini lbl-n-mini">N</div>
            <div class="label-mini lbl-e-mini">E</div>
            <div class="label-mini lbl-s-mini">S</div>
            <div class="label-mini lbl-w-mini">W</div>
        </div>
    `;
    
    const mapArea = document.getElementById('mapTabsContent');
    
    if (mapArea) {
        mapArea.style.position = 'relative'; // Locks the absolute position to THIS box
        mapArea.appendChild(compassDiv);
    } else {
        document.body.appendChild(compassDiv); // Fallback just in case
    }

    const miniRose = document.getElementById('mini-rose');
    let sensorsActive = false;

    function updateHeading(e) {
        let heading = e.webkitCompassHeading || Math.abs(e.alpha - 360);
        if (heading !== null && heading !== undefined) {
            miniRose.style.transform = `rotate(${-heading}deg)`;
        }
    }

    const startSensors = () => {
        if (sensorsActive) return; 

        if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
            DeviceOrientationEvent.requestPermission()
                .then(permissionState => {
                    if (permissionState === 'granted') {
                        window.addEventListener('deviceorientation', updateHeading, true);
                        sensorsActive = true;
                        document.querySelector('.compass-tooltip').innerText = "Calibrated";
                    }
                })
                .catch(console.error);
        } else {
            window.addEventListener('deviceorientationabsolute', updateHeading, true) || 
            window.addEventListener('deviceorientation', updateHeading, true);
            sensorsActive = true;
            document.querySelector('.compass-tooltip').innerText = "Calibrated";
        }
    };

    compassDiv.addEventListener('click', startSensors);
}

// --- Interactive Map Search Bar ---
function initSearchBar() {
    // 1. Inject CSS for Search Bar
    const style = document.createElement('style');
    style.innerHTML = `
        .map-search-wrapper {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            width: 320px;
            display: flex;
            flex-direction: column;
        }
        .map-search-input {
            width: 100%;
            padding: 12px 20px 12px 40px;
            border-radius: 30px;
            border: 1px solid var(--border-glow, #b6ff92);
            background: rgba(2, 36, 21, 0.85);
            color: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
            outline: none;
            transition: all 0.3s ease;
        }
        .map-search-input:focus { box-shadow: 0 0 15px var(--firefly-glow, #b6ff92); }
        .map-search-input::placeholder { color: rgba(255,255,255,0.6); font-weight: normal; }
        
        .search-icon { position: absolute; left: 15px; top: 13px; color: var(--star-gold, #ffda6c); }
        
        .map-search-results {
            background: rgba(2, 36, 21, 0.95);
            border-radius: 12px;
            margin-bottom: 10px; /* Expands upwards */
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid var(--border-glow, #b6ff92);
            display: none;
            flex-direction: column;
            order: -1; /* Forces results to show ABOVE the input bar */
        }
        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: 0.2s;
        }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: rgba(182, 255, 146, 0.1); color: var(--star-gold, #ffda6c); }
    `;
    document.head.appendChild(style);

    // 2. Inject HTML
    const container = document.createElement('div');
    container.className = 'map-search-wrapper';
    container.innerHTML = `
        <div class="map-search-results" id="mapSearchResults"></div>
        <div style="position: relative;">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="map-search-input" id="mapSearchInput" placeholder="Search a location...">
        </div>
    `;

    const mapArea = document.getElementById('mapTabsContent');
    if (mapArea) {
        mapArea.style.position = 'relative';
        mapArea.appendChild(container);
    } else {
        document.body.appendChild(container);
    }

    // 3. Attach Logic
    const input = document.getElementById('mapSearchInput');
    const resultsBox = document.getElementById('mapSearchResults');
    let tempMarker2D = null;
    let tempMarker3D = null;

    // Fetch typing suggestions
    input.addEventListener('input', async (e) => {
        const query = e.target.value.trim();
        if (query.length < 2) {
            resultsBox.style.display = 'none';
            return;
        }

        try {
            const res = await fetch(`api/search.php?q=${encodeURIComponent(query)}`);
            const data = await res.json();
            
            if (data.length > 0) {
                resultsBox.innerHTML = data.map(item => `
                    <div class="search-result-item" data-id="${item.id}" data-lat="${item.lattitude}" data-lng="${item.longitude}" data-name="${item.fullname || item.abbreviation}">
                        <div class="fw-bold">${item.fullname || item.abbreviation}</div>
                        <div class="small text-muted mt-1" style="font-size: 0.70rem; text-transform: uppercase;">
                            <i class="bi bi-geo-alt me-1"></i>${item.type}
                        </div>
                    </div>
                `).join('');
                resultsBox.style.display = 'flex';
            } else {
                resultsBox.innerHTML = '<div class="search-result-item text-muted small">No hidden realms found...</div>';
                resultsBox.style.display = 'flex';
            }
        } catch (err) { console.error("Search Error:", err); }
    });

    // Handle Selection & Update DB Search Count
    resultsBox.addEventListener('click', async (e) => {
        const item = e.target.closest('.search-result-item');
        if (!item || !item.dataset.id) return;

        const id = item.dataset.id;
        const lat = parseFloat(item.dataset.lat);
        const lng = parseFloat(item.dataset.lng);
        const name = item.dataset.name;

        resultsBox.style.display = 'none';
        input.value = name;

        fetch('api/search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const tab3d = document.getElementById('view-3d-tab');
        const is3DActive = tab3d && tab3d.classList.contains('active');

        if (is3DActive && map3d) {
            if(tempMarker3D) tempMarker3D.remove();
            const el = document.createElement('div');
            el.style.cssText = 'background:var(--star-gold); width:20px; height:20px; border-radius:50%; border:3px solid #000; box-shadow: 0 0 20px var(--star-gold);';
            tempMarker3D = new maplibregl.Marker(el).setLngLat([lng, lat])
                .setPopup(new maplibregl.Popup({ offset: 25 }).setHTML(`<strong class="text-dark">${name}</strong>`))
                .addTo(map3d);
            map3d.flyTo({ center: [lng, lat], zoom: 19 });
            tempMarker3D.togglePopup();
        } else if (!is3DActive && map2d) {
            if(tempMarker2D) map2d.removeLayer(tempMarker2D);
            tempMarker2D = L.marker([lat, lng], {
                icon: L.divIcon({ html: '<div style="background:var(--star-gold); width:20px; height:20px; border-radius:50%; border:3px solid #000; box-shadow: 0 0 20px var(--star-gold);"></div>', className: '' })
            }).addTo(map2d).bindPopup(`<strong class="text-dark">${name}</strong>`).openPopup();
            map2d.setView([lat, lng], 19);
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) resultsBox.style.display = 'none';
    });
}