<?php

/* affichage public + filtrage dynamique des menus.
 Routes : GET /menu (Liste et filtres), GET /menu/{id} (Détail), GET /menu/filter (Filtrage AJAX via Fetch API/JSON)
 */
namespace App\Controller;

use App\Repository\MenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MenuController extends AbstractController
{
    //liste menus w/ filtres, interfce dynmque via AJAX (menu-filter.js)
    #[Route('/menu', name: 'app_menu_index')]
    public function index(MenuRepository $menuRepo): Response
    {
        return $this->render('menu/index.html.twig', [              //thèmes & régimes distincts --> template pr remplir selects du formulaire de filtres.
            'menus'   => $menuRepo->findWithFilters(),              // all menus actifs pr affichage de base
            'themes'  => $menuRepo->findThemesDistincts(),         // listes pr remplir selects des filtres
            'regimes' => $menuRepo->findRegimesDistincts(),
        ]);
    }


    /**filtre AJAX menus, route appelée par menu-filter.js via Fetch API.
     * retourne JSON w/ menus filtrés.
     * paramètres GET -> thème, régime, prix max, nb personnes */
    #[Route('/menu/filter', name: 'app_menu_filter', methods: ['GET'])]
    public function filter(Request $request, MenuRepository $menuRepo): JsonResponse
    {
        try {
            $menus = $menuRepo->findWithFilters(
                $request->query->get('theme'),                                                             // Récupéra° paramètres filtre depuis URL
                $request->query->get('regime'),
                $request->query->get('prix_max') ? (float) $request->query->get('prix_max') : null,
                $request->query->get('nb_personnes') ? (int) $request->query->get('nb_personnes') : null
            );
             
            $data = []; 
            foreach ($menu as $menu) {
                $data[] =[
                   'id'              => $menu->getId(),
                   'titre'           => $menu->getTitre(),
                   'theme'           => $menu->getTheme(),
                   'regime'          => $menu->getRegime(),
                   'prix'            => (float) $menu->getPrix(),
                   'nb_personnes_min' => (int) $menu->getNbPersonnesMin(),
                   'image_url'       => $menu->getImages()->isEmpty()                                   // 1ère image menu pr card
                                     ? '/images/menus/' . $menu->getImages()->first()->getUrl()    // ?-> opérateur nullsafe pr éviter erreur si 0 image
                                     : '/images/default-menu.png',
                 ];
             }

             return new JsonResponse(['menus' => $data]); // JsonResponse encode automatiquement le tableau en JSON + add header Content-Type: application/json
        } catch (\Throwable $e) {
             return new JsonResponse([
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine()
             ], 500);
        }
    }

    /**
     * Détail menu = all informations du menu (photos, descript° et condit°, plats + allergènes) +prix calculé (commande.js)
     * #[MapEntity] récupère automtqmnt menu par id et renvoie error 404 si menu existe pas*/
    #[Route('/menu/{id}', name: 'app_menu_show', requirements: ['id' => '\d+'])]
    public function show(int $id, MenuRepository $menuRepo): Response
    {
        $menu = $menuRepo->find($id);                                   // Recherche du menu par son id
        if (!$menu || !$menu->isActif()) {                             // Si menu existe pas ou pas actif → error 404
            throw $this->createNotFoundException('Menu introuvable.');
        }
        return $this->render('menu/show.html.twig', [
            'menu' => $menu,
        ]);
    }
   
}