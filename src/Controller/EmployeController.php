<?php

//gère espace employé//

namespace App\Controller;

use App\Entity\HistoriqueStatut;
use App\Repository\CommandeRepository;
use App\Repository\AvisRepository;
use App\Repository\HoraireRepository;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Routes :
 * GET  /employe/dashboard->liste ttes les commandes
 * POST /employe/commande/{id}/statut->change satut commande
 * GET  /employe/avis->liste avis attente valida°
 * POST /employe/avis/{id}/valider->valide avis
 * POST /employe/avis/{id}/refuser->refuse avis
 * GET/POST /employe/horaires->modif horaires
 *uniquement pr employés (ROLE_EMPLOYE)
 */
#[IsGranted('ROLE_EMPLOYE')]
#[Route('/employe')]
class EmployeController extends AbstractController
{
    //dashboard employé, all commandes w/filtres//
    #[Route('/dashboard', name: 'app_employe_dashboard')]
    public function dashboard(
        CommandeRepository $commandeRepo,
        Request $request
    ): Response {
        //filtre optionnel/statut
        $statut = $request->query->get('statut');

        $commandes = $statut
            ? $commandeRepo->findBy(['statut' => $statut], ['dateCommande' => 'DESC'])
            : $commandeRepo->findBy([], ['dateCommande' => 'DESC']);

        return $this->render('employe/dashboard.html.twig', [
            'commandes'     => $commandes,
            'statut_filtre' => $statut,
        ]);
    }

    /**Changement,statut d'une commande:en_attente->validee->en_cours->terminee->retour_materiel->cloturee
     *mail auto envoyés w/ Brevo pr commande terminée (demande avis) + retour matériel (délai 10j+ frais 600€)*/
    #[Route('/commande/{id}/statut', name: 'app_employe_update_statut', methods: ['POST'])]
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

        // Vérification CSRF
        if (!$this->isCsrfTokenValid('statut_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_employe_dashboard');
        }

        $nouveauStatut = $request->request->get('statut');
        $commentaire   = $request->request->get('commentaire');
        $modeContact   = $request->request->get('mode_contact');

        //maj statut
        $commande->setStatut($nouveauStatut);

        //historq changmnt statut
        $historique = new HistoriqueStatut();
        $historique->setCommande($commande);
        $historique->setStatut($nouveauStatut);
        $historique->setDateHeure(new \DateTimeImmutable());
        $historique->setCommentaire($commentaire);
        $historique->setModeContact($modeContact);

        $em->persist($historique);
        $em->flush();

        //mail commande terminée (dmd avis)
        if ($nouveauStatut === 'terminee') {
            $this->sendEmailTerminee($commande);
        }

        //mail retour matériel(délai+frais)
        if ($nouveauStatut === 'retour_materiel') {
            $this->sendEmailRetourMateriel($commande);
        }

        $this->addFlash('success', 'Statut mis à jour avec succès.');
        return $this->redirectToRoute('app_employe_dashboard');
    }

    //liste avis attente de valida°//
    #[Route('/avis', name: 'app_employe_avis')]
    public function avis(AvisRepository $avisRepo): Response
    {
        //récup que avis pas encore validés
        $avis = $avisRepo->findBy(['valide' => false], ['date_avis' => 'DESC']);

        return $this->render('employe/avis.html.twig', [
            'avis' => $avis,
        ]);
    }

    //valida° avis -> visible s/page accueil//
    #[Route('/avis/{id}/valider', name: 'app_employe_valider_avis', methods: ['POST'])]
    public function validerAvis(
        int $id,
        Request $request,
        AvisRepository $avisRepo,
        EntityManagerInterface $em
    ): Response {

        $avis = $avisRepo->find($id);
        if (!$avis) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        //vérif csrf//
        if (!$this->isCsrfTokenValid('valider_avis_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_employe_avis');
        }

        //valida°->avis visible s/pageaccueil//
        $avis->setValide(true);
        $em->flush();

        $this->addFlash('success', 'Avis validé et publié sur la page d\'accueil.');
        return $this->redirectToRoute('app_employe_avis');
    }

    //refus avis, suppr° bdd//
    #[Route('/avis/{id}/refuser', name: 'app_employe_refuser_avis', methods: ['POST'])]
    public function refuserAvis(
        int $id,
        Request $request,
        AvisRepository $avisRepo,
        EntityManagerInterface $em
    ): Response {

        $avis = $avisRepo->find($id);
        if (!$avis) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        //verif csrf
        if (!$this->isCsrfTokenValid('refuser_avis_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_employe_avis');
        }

        //suppr° avis refusé
        $em->remove($avis);
        $em->flush();

        $this->addFlash('success', 'Avis refusé et supprimé.');
        return $this->redirectToRoute('app_employe_avis');
    }

    //horaires d'ouverture//
    #[Route('/horaires', name: 'app_employe_horaires', methods: ['GET', 'POST'])]
    public function horaires(
        HoraireRepository $horaireRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {

        $horaires = $horaireRepo->findAll();

        if ($request->isMethod('POST')) {

            //verif csrf//
            if (!$this->isCsrfTokenValid('horaires', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_employe_horaires');
            }

            //maj horaire
            foreach ($horaires as $horaire) {
                $id = $horaire->getId();
                $ouverture = $request->request->get('ouverture_' . $id);
                $fermeture = $request->request->get('fermeture_' . $id);

                if ($ouverture && $fermeture) {
                    $horaire->setHeureOuverture(new \DateTimeImmutable($ouverture));
                    $horaire->setHeureFermeture(new \DateTimeImmutable($fermeture));
                }
            }

            $em->flush();
            $this->addFlash('success', 'Horaires mis à jour avec succès.');
            return $this->redirectToRoute('app_employe_horaires');
        }

        return $this->render('employe/horaires.html.twig', [
            'horaires' => $horaires,
        ]);
    }

    //mail commande statut "terminée"+ dmd avis//
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
            error_log('BREVO TERMINEE ERROR: ' . $e->getMessage());
        }
    }

    //mail commande statut "retour_materiel"+ rappel délai 10j+ frais 600€//
    private function sendEmailRetourMateriel($commande): void
    {
        // @var \App\Entity\User $user //
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
                   vous seront facturés conformément à nos conditions générales de vente.</p>
                <p>Pour organiser le retour : <strong>contact@viteetgourmand.fr</strong></p>
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
            error_log('BREVO MATERIEL ERROR: ' . $e->getMessage());
        }
    }
}