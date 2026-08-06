const villeSelect = document.querySelector('#sortie_ville');
const lieuSelect = document.querySelector('#sortie_lieu');

if (villeSelect && lieuSelect) {
    villeSelect.addEventListener('change', function () {
        const villeId = villeSelect.value;

        lieuSelect.innerHTML = '<option value="">Choisir un lieu</option>';

        if (!villeId) {
            return;
        }

        fetch(`/sortie/lieux/${cityId}`)
            .then(response => response.json())
            .then(places => {
                places.forEach(place => {
                    const option = document.createElement('option');

                    option.value = place.id;
                    option.textContent = lieu.nom;

                    lieuSelect.appendChild(option);
                })
            })
            .catch(error => {
                console.error('Erreur lors du chargement des lieux :', error)
            });
    });
}
