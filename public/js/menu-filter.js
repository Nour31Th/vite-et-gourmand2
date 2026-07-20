// filtres dynamiques menus, voir fonctionnement dans la documenta°//

document.addEventListener('DOMContentLoaded', function () {
    //écouteurs évènmnt s/ filtres, chaque changmnt -> maj catzlogue
    const filtreTheme       = document.getElementById('filtre-theme');
    const filtreRegime      = document.getElementById('filtre-regime');
    const filtrePrix        = document.getElementById('filtre-prix');
    const filtreNbPersonnes = document.getElementById('filtre-nb-personnes');
    const menusContainer    = document.getElementById('menus-container');
    const compteurResultats = document.getElementById('compteur-resultats');

    if (!filtreTheme || !menusContainer) return; // si éléments existent pas -> stop

    [filtreTheme, filtreRegime, filtrePrix, filtreNbPersonnes].forEach(function (filtre) {   //écouteurs évènmnt s/ filtres, change (selects) et input (inputs)
        if (filtre) {
            filtre.addEventListener('change', filtrerMenus);
            filtre.addEventListener('input', filtrerMenus);
        }
    });

    //fonction filtrage AJAX via Fetch API, envoie reuqête GET à /menu/filter et actualise DOM.
    async function filtrerMenus() {
        const params = new URLSearchParams();      //construc° paramètres URL, URLSearchParams gère autmtqmnt encodage caractères spéciaux

        if (filtreTheme && filtreTheme.value && filtreTheme.value !== 'tous') {
            params.append('theme', filtreTheme.value);
        }
        if (filtreRegime && filtreRegime.value && filtreRegime.value !== 'tous') {
            params.append('regime', filtreRegime.value);
        }
        if (filtrePrix && filtrePrix.value) {
            params.append('prix_max', filtrePrix.value);
        }
        if (filtreNbPersonnes && filtreNbPersonnes.value) {
            params.append('nb_personnes', filtreNbPersonnes.value);
        }

        menusContainer.innerHTML = '<p class="text-center py-4">Chargement...</p>';  //affiche chrgmnt pdt requête

        try {
            //envoirequête GET vers /menu/filter via api fetch(), retourne Promise qui se résout qd rpse  HTTP reçue
            const response = await fetch(`/menu/filter?${params.toString()}`);    //await attend résolu° Promise.
            
            if (!response.ok) {                                       // vérif° requête éussi (code HTTP 200)
                throw new Error(`Erreur HTTP : ${response.status}`);
            }
            
            const data = await response.json();  //convers° réponse JSON en obj js, response.json() retourne Promise

            //maj dynmq DOM, réintlsa° conteneur et génère cartes menus
            if (compteurResultats) {                                                       //maj compteur résultat
                compteurResultats.textContent = `${data.menus.length} menu(s) trouvé(s)`;
            }

            if (data.menus.length === 0) {                         //if aucun menu correspnd filtres
                menusContainer.innerHTML = `
                    <p class="no-result">
                        Aucun menu ne correspond à vos critères. 
                        <a href="/menu">Voir tous les menus</a>
                    </p>
                `;
                return;
            }

            menusContainer.innerHTML = '';   //conteneur vidé avt inser° new cards

            data.menus.forEach(function (menu) {          //créa° card html pr chaque menu retourné
                const card = document.createElement('article'); //créa° élément article pr card
                card.className = 'menu-card';

                //innerHTML construit HTML de cardOn, use template literals (backticks) pr interpola°
                card.innerHTML = `                                    
                    <img src="${menu.image_url}"
                         alt="${menu.titre}"
                         loading="lazy">
                    <div class="card-body p-3">
                        <p class="card-theme">${menu.theme ?? ''} — ${menu.regime ?? ''}</p>
                        <h3 class="card-title">${menu.titre}</h3>
                        <p class="card-prix">${parseFloat(menu.prix).toFixed(2)} € / pers.</p>
                        <p class="small">À partir de ${menu.nb_personnes_min} personnes</p>
                        <a href="/menu/${menu.id}" class="btn btn-vert btn-sm mt-2">
                            Voir ce menu
                        </a>
                    </div>
                `;

                menusContainer.appendChild(card);               //insert° card dans container
            });

        } catch (error) {
            console.error('Erreur lors du filtrage :', error);  //affiche msg erreru si pb réseau
            menusContainer.innerHTML = `
                <p class="no-result text-danger">
                    Une erreur est survenue. Veuillez réessayer.
                </p>
            `;
        }
    }

});

