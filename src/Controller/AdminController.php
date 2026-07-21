<?php

//espace admin// 
namespace App\Controller;

use App\Entity\HistoriqueStatut;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\AvisRepository;
use App\Repository\HoraireRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*Routes :
 *GET  /admin/dashboard->liste ttes les commandes
 *POST /admin/commande/{id}/statut->change statut
 *GET  /admin/employes->liste employés
 *GET/POST /admin/employe/creer->créer compte employé
 *POST /admin/employe/{id}/activer->activer/désac employé
 *GET  /admin/avis->gérer avis
 *POST /admin/avis/{id}/valider->valider avis
 *POST /admin/avis/{id}/refuser->refuser avis
 *GET/POST /admin/horaires->modif horaires
 *GET  /admin/stats->stat MongoDB
 *que pr admins (ROLE_ADMIN)
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    /*dashboard admin, ttes les commandes w/ filtre statut*/
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(
        CommandeRepository $commandeRepo,
        Request $request
    ): Response {
        $statut    = $request->query->get('statut');
        $commandes = $statut
            ? $commandeRepo->findBy(['statut' => $statut], ['dateCommande' => 'DESC'])
            : $commandeRepo->findBy([], ['dateCommande' => 'DESC']);

        return $this->render('admin/dashboard.html.twig', [
            'commandes'     => $commandes,
            'statut_filtre' => $statut,
        ]);
    }

    /*changmnt statut commande comme employé+mail brevo*/
    #[Route('/commande/{id}/statut', name: 'app_admin_update_statut', methods: ['POST'])]
    public function updateStatut(
        int $id,
        Request $request,
        CommandeRepository $commandeRepo,
        EntityManagerInterface $em
    ): Response {

        $commande = $commandeRepo->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        //verif CSRF
        if (!$this->isCsrfTokenValid('statut_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $nouveauStatut = $request->request->get('statut');
        $commentaire   = $request->request->get('commentaire');
        $modeContact   = $request->request->get('mode_contact');

        $commande->setStatut($nouveauStatut);

        $historique = new HistoriqueStatut();
        $historique->setCommande($commande);
        $historique->setStatut($nouveauStatut);
        $historique->setDateHeure(new \DateTimeImmutable());
        $historique->setCommentaire($commentaire);
        $historique->setModeContact($modeContact);

        $em->persist($historique);
        $em->flush();

        //mail commande terminée
        if ($nouveauStatut === 'terminee') {
            $this->sendEmailTerminee($commande);
        }

        //mail retour materiel
        if ($nouveauStatut === 'retour_materiel') {
            $this->sendEmailRetourMateriel($commande);
        }

        $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirectToRoute('app_admin_dashboard');
    }

    /*liste emloyé*/
    #[Route('/employes', name: 'app_admin_employes')]
    public function employes(UserRepository $userRepo): Response
    {
        //recup all utilisateurs w/ ROLE_EMPLOYE
        $employes = array_filter(
            $userRepo->findAll(),
            fn($u) => in_array('ROLE_EMPLOYE', $u->getRoles())
        );

        return $this->render('admin/employes.html.twig', [
            'employes' => array_values($employes),
        ]);
    }

    //créa° compte employé
    #[Route('/employe/creer', name: 'app_admin_creer_employe', methods: ['GET', 'POST'])]
    public function creerEmploye(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {

        if ($request->isMethod('POST')) {

            //verif CSRF
            if (!$this->isCsrfTokenValid('creer_employe', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_admin_creer_employe');
            }

            $employe = new User();
            $employe->setNom(trim($request->request->get('nom')));
            $employe->setPrenom(trim($request->request->get('prenom')));
            $employe->setEmail(trim($request->request->get('email')));
            $employe->setGsm($request->request->get('gsm'));
            $employe->setRoles(['ROLE_EMPLOYE']);
            $employe->setActif(true);
            $employe->setPassword(
                $passwordHasher->hashPassword($employe, $request->request->get('password'))
            );

            $em->persist($employe);
            $em->flush();

            $this->addFlash('success', 'Compte employé créé avec succès.');
            return $this->redirectToRoute('app_admin_employes');
        }

        return $this->render('admin/creer_employe.html.twig');
    }

    //activer desac compte employé
    #[Route('/employe/{id}/activer', name: 'app_admin_activer_employe', methods: ['POST'])]
    public function activerEmploye(
        int $id,
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {

        $employe = $userRepo->find($id);
        if (!$employe) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        //verif CSRF
        if (!$this->isCsrfTokenValid('activer_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_employes');
        }

        //bascule actif/inactif
        $employe->setActif(!$employe->isActif());
        $em->flush();

        $statut = $employe->isActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Compte de {$employe->getPrenom()} {$employe->getNom()} $statut.");
        return $this->redirectToRoute('app_admin_employes');
    }

    //avis
    #[Route('/avis', name: 'app_admin_avis')]
    public function avis(AvisRepository $avisRepo): Response
    {
        $avis = $avisRepo->findBy(['valide' => false], ['dateAvis' => 'DESC']);
        return $this->render('admin/avis.html.twig', ['avis' => $avis]);
    }

    #[Route('/avis/{id}/valider', name: 'app_admin_valider_avis', methods: ['POST'])]
    public function validerAvis(
        int $id,
        Request $request,
        AvisRepository $avisRepo,
        EntityManagerInterface $em
    ): Response {
        $avis = $avisRepo->find($id);
        if (!$avis) throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('valider_avis_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_admin_avis');
        }

        $avis->setValide(true);
        $em->flush();

        $this->addFlash('success', 'Avis validé et publié.');
        return $this->redirectToRoute('app_admin_avis');
    }

    #[Route('/avis/{id}/refuser', name: 'app_admin_refuser_avis', methods: ['POST'])]
    public function refuserAvis(
        int $id,
        Request $request,
        AvisRepository $avisRepo,
        EntityManagerInterface $em
    ): Response {
        $avis = $avisRepo->find($id);
        if (!$avis) throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('refuser_avis_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_admin_avis');
        }

        $em->remove($avis);
        $em->flush();

        $this->addFlash('success', 'Avis refusé et supprimé.');
        return $this->redirectToRoute('app_admin_avis');
    }

    //horaire
    #[Route('/horaires', name: 'app_admin_horaires', methods: ['GET', 'POST'])]
    public function horaires(
        HoraireRepository $horaireRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $horaires = $horaireRepo->findAll();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('horaires', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token invalide.');
                return $this->redirectToRoute('app_admin_horaires');
            }

            foreach ($horaires as $horaire) {
                $id        = $horaire->getId();
                $ouverture = $request->request->get('ouverture_' . $id);
                $fermeture = $request->request->get('fermeture_' . $id);
                if ($ouverture && $fermeture) {
                    $horaire->setHeureOuverture(new \DateTimeImmutable($ouverture));
                    $horaire->setHeureFermeture(new \DateTimeImmutable($fermeture));
                }
            }

            $em->flush();
            $this->addFlash('success', 'Horaires mis à jour.');
            return $this->redirectToRoute('app_admin_horaires');
        }

        return $this->render('admin/horaires.html.twig', ['horaires' => $horaires]);
    }

    //stats données MongoDB via MongoDBService affichées dans graphique Chart.js
    #[Route('/stats', name: 'app_admin_stats')]
    public function stats(CommandeRepository $commandeRepo): Response
    {
        //stat depuis PostgreSQL (MongoDB sera ajouté sur feature/mongodb)
        $totalCommandes  = count($commandeRepo->findAll());
        $commandesParMois = $commandeRepo->findCommandesParMois();

        return $this->render('admin/stats.html.twig', [
            'total_commandes'   => $totalCommandes,
            'commandes_par_mois' => $commandesParMois,
        ]);
    }

    //mail commande terminé
    private function sendEmailTerminee($commande): void
    {
        /** @var \App\Entity\User $user */
        $user   = $commande->getUtilisateur();
        $prenom = $user->getPrenom();
        $nom    = $user->getNom();
        $email  = $user->getEmail();
        $numero = $commande->getNumeroCommande();

        $html = "<div style='font-family:Arial,sans-serif;color:#2C2B1A;padding:20px;max-width:600px;margin:auto'>
            <div style='background:#C4A882;padding:24px;text-align:center;border-radius:8px 8px 0 0'>
                <h1 style='color:#2C2B1A;margin:0;font-size:24px'>Vite &amp; Gourmand</h1>
            </div>
            <div style='background:#fff;padding:32px;border-radius:0 0 8px 8px;border:1px solid #e0ddd5'>
                <h2>Merci $prenom !</h2>
                <p>Votre commande <strong>$numero</strong> est terminée.</p>
                <p>Nous espérons que vous avez apprécié notre prestation. Votre avis nous est précieux !</p>
                <div style='text-align:center;margin:24px 0'>
                    <a href='https://vite-et-gourmand2-production.up.railway.app/user/dashboard'
                       style='background:#5C7A5C;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:700'>
                        Laisser mon avis
                    </a>
                </div>
                <p style='font-size:12px;color:#6B6B5A'>Bordeaux depuis 1999 · contact@viteetgourmand.fr</p>
            </div>
        </div>";

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.brevo.com/v3/smtp/email',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'api-key: ' . $_ENV['BREVO_API_KEY'],
                    'content-type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'sender'      => ['name' => 'Vite & Gourmand', 'email' => 'contact@viteetgourmand.fr'],
                    'to'          => [['email' => $email, 'name' => "$prenom $nom"]],
                    'subject'     => 'Votre commande est terminée — Laissez votre avis !',
                    'htmlContent' => $html,
                ]),
            ]);
            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            error_log('BREVO ADMIN TERMINEE: ' . $e->getMessage());
        }
    }

    //mail retour matériel
    private function sendEmailRetourMateriel($commande): void
    {
        /** @var \App\Entity\User $user */
        $user   = $commande->getUtilisateur();
        $prenom = $user->getPrenom();
        $nom    = $user->getNom();
        $email  = $user->getEmail();
        $numero = $commande->getNumeroCommande();

        $html = "<div style='font-family:Arial,sans-serif;color:#2C2B1A;padding:20px;max-width:600px;margin:auto'>
            <div style='background:#C4A882;padding:24px;text-align:center;border-radius:8px 8px 0 0'>
                <h1 style='color:#2C2B1A;margin:0;font-size:24px'>Vite &amp; Gourmand</h1>
            </div>
            <div style='background:#fff;padding:32px;border-radius:0 0 8px 8px;border:1px solid #e0ddd5'>
                <h2>Retour de matériel requis</h2>
                <p>Bonjour $prenom,</p>
                <p>Du matériel vous a été prêté lors de votre commande <strong>$numero</strong>.</p>
                <p>Merci de nous le restituer dans un délai de <strong>10 jours ouvrés</strong>.</p>
                <p style='color:#dc2626'>Sans restitution dans ce délai, des frais de <strong>600 €</strong>
                   vous seront facturés conformément à nos CGV.</p>
                <p>Contact : <strong>contact@viteetgourmand.fr</strong></p>
                <p style='font-size:12px;color:#6B6B5A'>Bordeaux depuis 1999 · contact@viteetgourmand.fr</p>
            </div>
        </div>";

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.brevo.com/v3/smtp/email',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'api-key: ' . $_ENV['BREVO_API_KEY'],
                    'content-type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'sender'      => ['name' => 'Vite & Gourmand', 'email' => 'contact@viteetgourmand.fr'],
                    'to'          => [['email' => $email, 'name' => "$prenom $nom"]],
                    'subject'     => 'Retour de matériel — Vite & Gourmand',
                    'htmlContent' => $html,
                ]),
            ]);
            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            error_log('BREVO ADMIN MATERIEL: ' . $e->getMessage());
        }
    }
}
