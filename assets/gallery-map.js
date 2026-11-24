import 'leaflet/dist/leaflet.css';
import * as L from 'leaflet';

const initGalleryMap = () => {
    const mapElement = document.getElementById('gallery-map');

    if (!mapElement || mapElement.dataset.initialized === 'true') {
        return;
    }

    mapElement.dataset.initialized = 'true';

    const latitude = Number.parseFloat(mapElement.dataset.latitude);
    const longitude = Number.parseFloat(mapElement.dataset.longitude);
    const address = mapElement.dataset.address || 'Tunis, Tunisia';
    const galleryName = mapElement.dataset.name || 'Abstract Art Gallery';

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        mapElement.innerHTML = `
            <div class="gallery-map__fallback">
                <strong>Location unavailable.</strong>
                <span>${address}</span>
            </div>
        `;
        return;
    }

    const map = L.map(mapElement, {
        scrollWheelZoom: false,
        zoomControl: true,
        attributionControl: true,
    }).setView([latitude, longitude], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    const customIcon = L.divIcon({
        className: 'gallery-map-marker',
        html: '<span class="gallery-map-marker__dot"></span>',
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        popupAnchor: [0, -12],
    });

    const popupMarkup = `
        <div class="gallery-map-popup">
            <strong>${galleryName}</strong>
            <span>${address}</span>
            <a href="https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=16/${latitude}/${longitude}" target="_blank" rel="noreferrer">View gallery →</a>
        </div>
    `;

    L.marker([latitude, longitude], { icon: customIcon })
        .addTo(map)
        .bindPopup(popupMarkup, {
            className: 'gallery-map-popup-wrapper',
            closeButton: true,
            maxWidth: 220,
        })
        .openPopup();

    map.fitBounds([[latitude, longitude], [latitude, longitude]], { maxZoom: 15 });
    map.whenReady(() => {
        setTimeout(() => map.invalidateSize(), 50);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGalleryMap);
} else {
    initGalleryMap();
}
