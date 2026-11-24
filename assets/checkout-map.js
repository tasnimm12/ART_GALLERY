import 'leaflet/dist/leaflet.css';
import * as L from 'leaflet';

const NOMINATIM_URL = 'https://nominatim.openstreetmap.org';
const Leaflet = L.default || L;

const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[character]));

const initCheckoutMap = () => {
    const mapElement = document.getElementById('checkout-map');
    if (!mapElement || mapElement.dataset.initialized === 'true') {
        return;
    }

    const form = mapElement.closest('form');
    if (!form) {
        return;
    }

    const fields = {
        address: form.querySelector('[name$="[address]"]'),
        postalCode: form.querySelector('[name$="[postalCode]"]'),
        city: form.querySelector('[name$="[city]"]'),
        country: form.querySelector('[name$="[country]"]'),
        latitude: form.querySelector('[name$="[latitude]"]'),
        longitude: form.querySelector('[name$="[longitude]"]'),
    };
    const messageElement = document.getElementById('checkout-map-message');
    const searchButton = document.getElementById('checkout-map-search');
    const positionButton = document.getElementById('checkout-map-geolocation');
    const defaultPosition = [46.603354, 1.888334];

    if (!messageElement || !searchButton || !positionButton
        || Object.values(fields).some((field) => !field)) {
        return;
    }

    const showMessage = (message, isError = true) => {
        messageElement.textContent = message;
        messageElement.classList.toggle('checkout-map-message--error', isError);
    };

    const updateCoordinates = (latitude, longitude) => {
        fields.latitude.value = Number(latitude).toFixed(7);
        fields.longitude.value = Number(longitude).toFixed(7);
    };

    const addressFromResult = (result) => {
        const address = result.address || {};
        const street = [address.road || address.pedestrian || address.footway || address.path, address.house_number]
            .filter(Boolean)
            .join(' ');
        const city = address.city
            || address.town
            || address.village
            || address.municipality
            || address.city_district
            || address.suburb
            || address.locality
            || address.county
            || '';

        return {
            address: street
                || address.hamlet
                || address.neighbourhood
                || address.suburb
                || result.name
                || result.display_name
                || '',
            postalCode: address.postcode || '',
            city,
            country: address.country || '',
        };
    };

    const fillAddress = (result) => {
        const values = addressFromResult(result);
        Object.keys(values).forEach((field) => {
            fields[field].value = values[field];
            fields[field].dispatchEvent(new Event('input', { bubbles: true }));
            fields[field].dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    const requestNominatim = async (endpoint, parameters) => {
        const url = new URL(`${NOMINATIM_URL}/${endpoint}`);
        Object.entries({ ...parameters, format: 'jsonv2', addressdetails: '1' })
            .forEach(([key, value]) => url.searchParams.set(key, value));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error('nominatim-request-failed');
        }
        return response.json();
    };

    const setMarker = async (latitude, longitude, result = null) => {
        const position = [Number(latitude), Number(longitude)];
        updateCoordinates(latitude, longitude);
        map.setView(position, 16);
        marker.setLatLng(position);
        if (result) {
            fillAddress(result);
        }
        const label = result?.display_name || 'Adresse sélectionnée';
        marker.bindPopup(`<strong>Adresse sélectionnée</strong><br>${escapeHtml(label)}`).openPopup();
    };

    const reverseGeocode = async (latitude, longitude) => {
        await setMarker(latitude, longitude);
        const result = await requestNominatim('reverse', { lat: latitude, lon: longitude });
        if (!result || !result.address) {
            throw new Error('address-not-found');
        }
        await setMarker(latitude, longitude, result);
        showMessage('');
    };

    const initialLatitude = Number.parseFloat(fields.latitude.value);
    const initialLongitude = Number.parseFloat(fields.longitude.value);
    const initialPosition = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude)
        ? [initialLatitude, initialLongitude]
        : defaultPosition;
    let map;
    let marker;
    try {
        map = Leaflet.map(mapElement, { scrollWheelZoom: false }).setView(initialPosition, Number.isFinite(initialLatitude) ? 15 : 5);
        Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);
        marker = Leaflet.marker(initialPosition).addTo(map);
    } catch (error) {
        console.error('Impossible d’initialiser la carte de livraison.', error);
        showMessage('Impossible d’afficher la carte. Vous pouvez saisir votre adresse manuellement.');
        return;
    }
    mapElement.dataset.initialized = 'true';
    if (Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude)) {
        marker.bindPopup('<strong>Adresse sélectionnée</strong>').openPopup();
    }

    map.on('click', async (event) => {
        try {
            await reverseGeocode(event.latlng.lat, event.latlng.lng);
        } catch {
            showMessage('Impossible de localiser cette adresse. Vérifiez les informations saisies.');
        }
    });

    const searchAddress = async () => {
        const query = [fields.address.value, fields.postalCode.value, fields.city.value, fields.country.value]
            .map((value) => value.trim()).filter(Boolean).join(', ');
        if (!query) {
            showMessage('Impossible de localiser cette adresse. Vérifiez les informations saisies.');
            return;
        }
        searchButton.disabled = true;
        try {
            const results = await requestNominatim('search', { q: query, limit: '1' });
            if (!results.length) {
                showMessage('Adresse introuvable. Veuillez sélectionner directement un emplacement sur la carte.');
                return;
            }
            await setMarker(results[0].lat, results[0].lon, results[0]);
            showMessage('');
        } catch {
            showMessage('Impossible de localiser cette adresse. Vérifiez les informations saisies.');
        } finally {
            searchButton.disabled = false;
        }
    };

    searchButton.addEventListener('click', searchAddress);

    [fields.address, fields.postalCode, fields.city, fields.country].forEach((field) => {
        field.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchAddress();
            }
        });
    });

    positionButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            showMessage('Autorisation de localisation refusée. Vous pouvez sélectionner votre adresse directement sur la carte.');
            return;
        }
        positionButton.disabled = true;
        navigator.geolocation.getCurrentPosition(async ({ coords }) => {
            try {
                await reverseGeocode(coords.latitude, coords.longitude);
            } catch {
                showMessage('Votre position a été détectée, mais l’adresse n’a pas pu être récupérée. Vérifiez ou complétez les champs.');
            } finally {
                positionButton.disabled = false;
            }
        }, () => {
            showMessage('Autorisation de localisation refusée. Vous pouvez sélectionner votre adresse directement sur la carte.');
            positionButton.disabled = false;
        }, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 15000,
        });
    });

    map.whenReady(() => setTimeout(() => map.invalidateSize(), 50));
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckoutMap, { once: true });
} else {
    initCheckoutMap();
}
document.addEventListener('turbo:load', initCheckoutMap);
