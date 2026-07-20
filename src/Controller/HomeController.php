<?php

//gestion page d'accueil publique, variable : avis validés, horaires footer// 

namespace App\Controller;

use App\Repository\AvisRepository;
use App\Repository\HoraireRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        AvisRepository $avisRepo,
        HoraireRepository $horaireRepo
    ): Response {
        return $this->render('home/index.html.twig', [
            'avis'          => $avisRepo->findBy(['valide' => true], ['dateAvis' => 'DESC'], 3),  //avis validés
            'horaires'      => $horaireRepo->findAll(),  //horaires pr footer dynmq
        ]);
    }
}

