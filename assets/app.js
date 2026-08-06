import './stimulus_bootstrap.js';
import './styles/app.css';
import './place-filter.js'
import './map.js'

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

import 'styles/tom-select.css';
import './place-filter.js'
import './map.js'
import TomSelect from 'tom-select';

function initSortieForm() {
    const villeEl = document.querySelector('#sortie_ville');
    const lieuEl = document.querySelector('#sortie_lieu');
    const nouvelleVilleEl = document.querySelector('#sortie_nouvelleVille');
    const nouveauLieuEl = document.querySelector('#sortie_nouveauLieu');
    const cpWrap = document.querySelector('#cp-nouvelle-ville');
    const nouveauLieuWrap = document.querySelector('#nouveau-lieu-fields');

    if (!villeEl || !lieuEl) {
        return;
    }

    if (villeEl.tomselect) {
        return;
    }

    const villeTs = new TomSelect(villeEl, {
        create: true,
        placeholder: 'Choisir ou créer une ville…',
    });

    const lieuTs = new TomSelect(lieuEl, {
        create: true,
        placeholder: 'Choisir ou créer un lieu…',
    });

    const chargerLieux = async (villeId) => {
        lieuTs.clear(true);
        lieuTs.clearOptions();
        if (nouveauLieuEl) nouveauLieuEl.value = '';
        if (nouveauLieuWrap) nouveauLieuWrap.classList.add('js-hidden');

        if (villeId && /^\d+$/.test(villeId)) {
            try {
                const res = await fetch('/sortie/lieux/' + encodeURIComponent(villeId));
                const lieux = await res.json();
                lieux.forEach((l) => lieuTs.addOption({value: String(l.id), text: l.nom}));
            } catch (e) {
            }
        }

        lieuTs.refreshOptions(false);
    };

    villeTs.on('change', (value) => {
        const nouvelleVille = value && !/^\d+$/.test(value);

        if (nouvelleVilleEl) {
            nouvelleVilleEl.value = nouvelleVille ? value : '';
        }
        if (cpWrap) {
            cpWrap.classList.toggle('js-hidden', !nouvelleVille);
        }
        chargerLieux(value);
    });

    lieuTs.on('change', (value) => {
        const nouveauLieu = value && !/^\d+$/.test(value);

        if (nouveauLieuEl) {
            nouveauLieuEl.value = nouveauLieu ? value : '';
        }
        if (nouveauLieuWrap) {
            nouveauLieuWrap.classList.toggle('js-hidden', !nouveauLieu);
        }
    });
}

document.addEventListener('turbo:load', initSortieForm);
document.addEventListener('DOMContentLoaded', initSortieForm);
