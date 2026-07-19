// code js pr toute l'appli//

document.addEventListener ('DOMContentLoaded', function () {  // DOMContentLoaded --> attend que tt hmtl chargé pr executer js //

    //ferme automatiqmnt msg flash après 4sec//
    const alerts = document.querySelectorAll ('.alert');
    alerts.forEach(function (alert){
        setTimeout(function(){
            alert.style.opacity='0';                            //debut animat° disparit°//
            alert.style.transition='opacity 0.5s ease';        // msg transparent pr animation de 0.5s//
            setTimeout (function (){                                    //suppr à fin animat°//
                alert.remove();
            }, 500);                                                   //durée animation//
        }, 4000);                                                      //-->affichage 4sec//
    });


// ajoute classe active au lien de nav°, souligne lien actif en vert cf apps.css//
const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(function (link) {
        if (link.getAttribute('href') === currentPath) {            //comapre href du lien avec URL courante
            link.classList.add('active');
        }
    });
});
