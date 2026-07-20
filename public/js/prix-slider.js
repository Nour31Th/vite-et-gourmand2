//synchronsa° slider prix, maj en temps réel affichage valeur max pdt interac° utilisateur//

document.addEventListener('DOMContentLoaded', function () {

    const slider      = document.getElementById('filtre-prix');
    const valeurPrix  = document.getElementById('valeur-prix');

    if (!slider || !valeurPrix) return;

    //maj affichage prix pr déplacement slider
    slider.addEventListener('input', function () {
        valeurPrix.textContent = slider.value;
    });

});