<?php

//page publique avis client, route : GET /avis->liste ts avis validés, pas restreint à 1 rôle, lien dans footer et page accueil/

namespace App\Controller;

use App\Repository\AvisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AvisController extends AbstractController
{
    //liste publique avis validés, triés par date décroissante (+récents en 1er)/
    #[Route('/avis', name: 'app_avis_liste')]
    public function liste(AvisRepository $avisRepo): Response
    {
        //recup que avis validés par employé/admin
        $avis = $avisRepo->findBy(
            ['valide' => true],        // valide = true signifie que l'avis a été approuvé
            ['date_avis' => 'DESC']
        );

        return $this->render('avis/liste.html.twig', [
            'avis' => $avis,
        ]);
    }
}