// script.js : ajout d'un projet / stage dans le formulaire

var bouton = document.getElementById('ajouter-projet');
var modele = document.getElementById('modele-projet');
var liste  = document.getElementById('liste-projets');

// À chaque clic, on copie le modèle à la fin de la liste
bouton.addEventListener('click', function () {
    var copie = modele.content.cloneNode(true);
    liste.appendChild(copie);
});
