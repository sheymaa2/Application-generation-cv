// script.js : petites interactions de la page (le site marche aussi sans JavaScript)

// ---------- 1) Ajouter / retirer des lignes dans les listes du CV ----------
// (même principe que la Partie 1 : on copie un <template>)
// Boutons "+ Ajouter..." : data-modele = id du <template>, data-liste = id de la liste
document.querySelectorAll('[data-modele]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
        var modele = document.getElementById(bouton.dataset.modele);
        var liste  = document.getElementById(bouton.dataset.liste);
        liste.appendChild(modele.content.cloneNode(true));
        // On place le curseur dans le premier champ de la nouvelle ligne
        liste.lastElementChild.querySelector('input').focus();
    });
});

// Boutons ✕ : un seul écouteur sur la page, qui marche aussi pour les lignes ajoutées après.
// closest() : le clic peut tomber sur l'icône à l'intérieur du bouton.
document.addEventListener('click', function (evenement) {
    var bouton = evenement.target.closest('.btn-retirer');
    if (!bouton) {
        return;
    }
    var ligne = bouton.closest('.ligne-liste');
    var liste = ligne.parentElement;

    if (liste.querySelectorAll('.ligne-liste').length > 1) {
        ligne.remove();
    } else {
        // Dernière ligne : on vide ses champs au lieu de la supprimer (au moins une ligne par rubrique)
        ligne.querySelectorAll('input, textarea').forEach(function (champ) {
            champ.value = '';
        });
    }
});

// ---------- 2) Aperçu de la photo choisie, avant l'envoi ----------
var champPhoto = document.getElementById('photo');

if (champPhoto) {
    champPhoto.addEventListener('change', function () {
        var fichier = champPhoto.files[0];
        if (!fichier) {
            return;
        }
        // URL temporaire qui pointe vers le fichier choisi sur l'ordinateur
        var apercu = document.getElementById('apercu-photo');
        apercu.src = URL.createObjectURL(fichier);
        apercu.hidden = false;

        var vide = document.getElementById('photo-vide');
        if (vide) {
            vide.remove();
        }
        document.getElementById('nom-photo').textContent = 'Photo choisie : ' + fichier.name;
    });
}

// ---------- 3) Inscription : le nom de l'entreprise n'apparaît que pour une entreprise ----------
var champEntreprise = document.getElementById('champ-entreprise');

if (champEntreprise) {
    var radios = document.querySelectorAll('input[name="role"]');

    function majChampEntreprise() {
        var entreprise = document.querySelector('input[name="role"][value="entreprise"]').checked;
        champEntreprise.hidden = !entreprise;
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', majChampEntreprise);
    });
    majChampEntreprise();
}
