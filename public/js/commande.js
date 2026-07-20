/*calcul dynamique prix commande, voir fonctionnement dans documenta°
data-* attributes dans #data-menu : data-prix- >prix du menu, data-min -> nb_personnes_min du menu */

document.addEventListener('DOMContentLoaded', function () {

    const dataMenu = document.getElementById('data-menu'); /*recup° données menu (via data-attributes Twig)*/
    if (!dataMenu) return; //sécu si pas s/ page commande

    const prixMenu     = parseFloat(dataMenu.dataset.prix);
    const minPersonnes = parseInt(dataMenu.dataset.min);

    /*recup° éléments formulaire et affichage*/
    const nbInput    = document.getElementById('commande_nb_personnes');
    const villeInput = document.getElementById('commande_ville_livraison');
    const prixBase   = document.getElementById('prix-base');
    const prixLivr   = document.getElementById('frais-livraison');
    const prixTotal  = document.getElementById('prix-total');
    const badgeReduc = document.getElementById('badge-reduction');

    if (!nbInput || !villeInput) return;

    /*fonct° prcple calcul prix, call à chaque modif° du nb de personnes ou de la ville*/
    function calculerPrix() {
        const nb    = parseInt(nbInput.value) || minPersonnes;
        const ville = villeInput.value.toLowerCase().trim();

        let base = prixMenu * (nb / minPersonnes); //calcul prix de base

        const eligible = nb >= minPersonnes + 5; //réduc° 10% si nb >= min + 5
        if (eligible) {
            base = base * 0.90;
            if (badgeReduc) badgeReduc.style.display = 'inline-block';      //affichage badge reduc°
        } else {
            if (badgeReduc) badgeReduc.style.display = 'none';
        }

        //frais livraison
        const frais = (ville !== 'bordeaux' && ville !== '') ? 5.00 : 0.00;
        const total = base + frais;

        //maj DOM w/ affichage prix calculés
        if (prixBase)  prixBase.textContent  = base.toFixed(2) + ' €';
        if (prixLivr)  prixLivr.textContent  = frais > 0 ? frais.toFixed(2) + ' €' : 'Gratuit';
        if (prixTotal) prixTotal.textContent  = total.toFixed(2) + ' €';
    }

    nbInput.addEventListener('input', calculerPrix);     //écoute événements, recalcul à chaque saisie
    villeInput.addEventListener('input', calculerPrix);

    calculerPrix(); //calcul initial pdt chrgmnt de la page

});