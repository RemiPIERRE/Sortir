let map = null;

function initializeMap() {
    const mapContainer = document.querySelector('#map');

    if (!mapContainer || typeof L === 'undefined') {
        return;
    }

    const latitude = Number(mapContainer.dataset.latitude);
    const longitude = Number(mapContainer.dataset.longitude);
    const placeName = mapContainer.dataset.lieu;

    /* Évite d'initialiser deux fois le même conteneur */
    if (map) {
        map.remove();
        map = null;
    }

    map = L.map(mapContainer).setView(
        [latitude, longitude],
        15
    );

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    L.marker([latitude, longitude])
        .addTo(map)
        .bindPopup(placeName)
        .openPopup();

    /* Recalcule la taille après l'affichage de la page */
    setTimeout(() => {
        map.invalidateSize();
    }, 100);
}

function removeMap() {
    if (map) {
        map.remove();
        map = null;
    }
}

document.addEventListener('DOMContentLoaded', initializeMap);
document.addEventListener('turbo:load', initializeMap);

/* Nettoie la carte avant que Turbo mette la page en cache */
document.addEventListener('turbo:before-cache', removeMap);
