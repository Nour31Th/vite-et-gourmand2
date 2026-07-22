<?php

//gère espace perso client connecté//

namespace App\Controller;

use App\Entity\Avis;
use App\Repository\CommandeRepository;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

//routes que pr user conncté : GET  /user/dashboard->tableau de bord client, GET  /user/commande/{id}/annuler->confirma° annula°, POST /user/commande/{id}/annuler-> annule commande, GET/POST /user/avis/{commandeId}->formulaire avis//
#[IsGranted('ROLE_USER')]
#[Route('/user')]
class UserController extends AbstractController
{
    //dashborad client w/ ttes commandes de l'utilisateur connecté, triées par date décroissante//
    #[Route('/dashboard', name: 'app_user_dashboard')]
    public function dashboard(CommandeRepository $commandeRepo): Response
    {
        //recup° commandes utilisateur connecté, @var \App\Entity\User $user//
        $user = $this->getUser();

        $commandes = $commandeRepo->findBy(
            ['utilisateur' => $user],
            ['dateCommande' => 'DESC'] //+récentes en premier
        );

        return $this->render('user/dashboard.html.twig', [
            'commandes' => $commandes,
            'user'      => $user,
        ]);
    }

    //annula° commande, seulemnt celle w/ statut 'en_attente'+client annule que ses propres commandes (vérification propriété)//
    #[Route('/commande/{id}/annuler', name: 'app_user_annuler_commande', methods: ['POST'])]
    public function annulerCommande(
        int $id,
        Request $request,
        CommandeRepository $commandeRepo,
        EntityManagerInterface $em
    ): Response {

        $commande = $commandeRepo->find($id);

        //verfi°existence commande
        if (!$commande) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        //verif°propriété->client annule que ses propres commandes, @var \App\Entity\User $user//
        $user = $this->getUser();
        if ($commande->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        //verif°CSRF
        if (!$this->isCsrfTokenValid('annuler_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        //que commandes en attente pr annula°
        if ($commande->getStatut() !== 'en_attente') {
            $this->addFlash('danger', 'Cette commande ne peut plus être annulée.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        //annula°+restaura° stock
        $commande->setStatut('annulee');
        $menu = $commande->getMenu();
        if ($menu->getStock() < 9999) {
            $menu->setStock($menu->getStock() + 1);
        }

        $em->flush();

        $this->addFlash('success', 'Votre commande a bien été annulée.');
        return $this->redirectToRoute('app_user_dashboard');
    }

    //modif°commande, que celle en_attente, menu peut pas changer, que infos logistiques// 
    #[Route('/commande/{id}/modifier', name: 'app_user_modifier_commande', methods: ['GET', 'POST'])]
    public function modifierCommande(
        int $id,
        Request $request,
        CommandeRepository $commandeRepo,
        EntityManagerInterface $em
    ): Response {
        
        $commande = $commandeRepo->find($id);

        //verif existence et propriété, @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$commande || $commande->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        //que modif commandes en_attente
        if ($commande->getStatut() !== 'en_attente') {
            $this->addFlash('danger', 'Cette commande ne peut plus être modifiée.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        if ($request->isMethod('POST')) {

        //verif° csrf
        if (!$this->isCsrfTokenValid('modifier_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_user_modifier_commande', ['id' => $id]);
        }

        $nbPersonnes = (int) $request->request->get('nb_personnes');
        $menu        = $commande->getMenu();

        if ($nbPersonnes < $menu->getNbPersonnesMin()) {
            $this->addFlash('danger', 'Le nombre minimum est ' . $menu->getNbPersonnesMin() . ' personnes.');
            return $this->redirectToRoute('app_user_modifier_commande', ['id' => $id]);
        }

        //recalcul du prix après modif
        $prixBase = $menu->getPrix() * ($nbPersonnes / $menu->getNbPersonnesMin());
        if ($nbPersonnes >= $menu->getNbPersonnesMin() + 5) {
            $prixBase = $prixBase * 0.90;
        }

        $ville         = trim($request->request->get('ville_livraison'));
        $prixLivraison = strtolower($ville) !== 'bordeaux' ? 5.00 : 0.00;
        $prixTotal     = $prixBase + $prixLivraison;

        //maj champs modifiables
        $commande->setNbPersonnes($nbPersonnes);
        $commande->setAdresseLivraison(trim($request->request->get('adresse_livraison')));
        $commande->setVilleLivraison($ville);
        $commande->setDatePrestation(new \DateTimeImmutable($request->request->get('date_prestation')));
        $commande->setHeureLivraison(new \DateTimeImmutable($request->request->get('heure_livraison')));
        $commande->setPrixLivraison($prixLivraison);
        $commande->setPrixTotal($prixTotal);

        $em->flush();

        $this->addFlash('success', 'Votre commande a bien été modifiée.');
        return $this->redirectToRoute('app_user_dashboard');
        }
        
        return $this->render('user/modifier_commande.html.twig', [
        'commande' => $commande,
    ]);
    
    }

    //avis client,1/commande terminée, validation employé avant publication//
    #[Route('/avis/{commandeId}', name: 'app_user_avis', methods: ['GET', 'POST'])]
    public function deposerAvis(
        int $commandeId,
        Request $request,
        CommandeRepository $commandeRepo,
        AvisRepository $avisRepo,
        EntityManagerInterface $em
    ): Response {

        $commande = $commandeRepo->find($commandeId);

        //verif° existence+propriété, @var \App\Entity\User $user//
        $user = $this->getUser();
        if (!$commande || $commande->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        //que commandes terminées pr avis
        if ($commande->getStatut() !== 'terminee') {
            $this->addFlash('danger', 'Vous ne pouvez laisser un avis que pour une commande terminée.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        //verif avis existe pas déjà pr la commande
        if ($avisRepo->findOneBy(['commande' => $commande])) {
            $this->addFlash('info', 'Vous avez déjà laissé un avis pour cette commande.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        if ($request->isMethod('POST')) {

            //vérif° CSRF
            if (!$this->isCsrfTokenValid('avis_' . $commandeId, $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_user_avis', ['commandeId' => $commandeId]);
            }

            $note        = (int) $request->request->get('note');
            $commentaire = trim($request->request->get('commentaire'));

            //valid°note
            if ($note < 1 || $note > 5) {
                $this->addFlash('danger', 'La note doit être comprise entre 1 et 5.');
                return $this->redirectToRoute('app_user_avis', ['commandeId' => $commandeId]);
            }

            //créa°avis en attente de valida°
            $avis = new Avis();
            $avis->setUtilisateur($user);
            $avis->setCommande($commande);
            $avis->setNote($note);
            $avis->setCommentaire($commentaire);
            $avis->setValide(false); //attente valida°employé
            $avis->setdate_avis(new \DateTimeImmutable());

            $em->persist($avis);
            $em->flush();

            $this->addFlash('success', 'Votre avis a bien été envoyé. Il sera publié après validation.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        return $this->render('user/avis.html.twig', [
            'commande' => $commande,
        ]);
    }
}

