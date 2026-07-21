<?php

//gère formulaire commande client, que pr role user (client connect)// 
//routes: GET /commande/new/{id} -> affiche formulaire, POST /commande/new/{id} ->traite commande//


namespace App\Controller;

use App\Entity\Commande;
use App\Entity\HistoriqueStatut;
use App\Repository\MenuRepository;
use App\Service\MongoDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CommandeController extends AbstractController
{
    /**
     * MongoDBService injecté via constructeur (injec° de dépendance)
     *symfony instancie automtqmnt le service grâce à l'autowiring
     */
    public function __construct(
        private MongoDBService $mongoDBService
    ) {}
     /**
     *formlaire commande pr un menu, 
     *calcul du prix : prix base = prix_menu × (nb_personnes / nb_personnes_min), réduction 10% si nb_personnes >= nb_personnes_min +5,  +5€ si ville != 'bordeaux'
     */
    #[Route('/commande/new/{id}', name: 'app_commande_new', requirements: ['id' => '\d+'])]
    public function new(
        int $id,
        Request $request,
        MenuRepository $menuRepo,
        EntityManagerInterface $em
    ): Response {
        $menu = $menuRepo->find($id);                         //recup° menu par id

        if (!$menu || !$menu->isActif()) {                    //menu existe pas ou inactif->404
            throw $this->createNotFoundException('Menu introuvable.');
        }

        if ($menu->getStock() <= 0) {                                        // 0 stock ->redirect°
            $this->addFlash('danger', 'Ce menu n\'est plus disponible.');        
            return $this->redirectToRoute('app_menu_show', ['id' => $id]);
        }

        if ($request->isMethod('POST')) {

            if (!$this->isCsrfTokenValid('commande_' . $id, $request->request->get('_token'))) {   //verif° CSRF
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_commande_new', ['id' => $id]);
            }

            $nbPersonnes    = (int) $request->request->get('nb_personnes');
            $adresse        = trim($request->request->get('adresse_livraison'));
            $ville          = trim($request->request->get('ville_livraison'));
            $datePrestation = $request->request->get('date_prestation');
            $heureLivraison = $request->request->get('heure_livraison');

            if ($nbPersonnes < $menu->getNbPersonnesMin()) {               //valida_ nb pers min
                $this->addFlash('danger', 'Le nombre minimum est ' . $menu->getNbPersonnesMin() . ' personnes.');
                return $this->redirectToRoute('app_commande_new', ['id' => $id]);
            }

            $prixBase = $menu->getPrix() * ($nbPersonnes / $menu->getNbPersonnesMin());    //calcul prix de base

            if ($nbPersonnes >= $menu->getNbPersonnesMin() + 5) {   //réduc° 10% -> nb_personnes >= nb_personnes_min + 5
                $prixBase = $prixBase * 0.90;
            }

            $prixLivraison = (strtolower($ville) !== 'bordeaux') ? 5.00 : 0.00;  //frais livraison —> 0 pr Bdx, +5€ autres
            $prixTotal     = $prixBase + $prixLivraison;

            $numeroCommande = 'VG-' . strtoupper(uniqid());  //donne num commande unique

            //créa° commande
            $commande = new Commande();
            $commande->setUtilisateur($this->getUser());
            $commande->setMenu($menu);
            $commande->setNumeroCommande($numeroCommande);
            $commande->setDateCommande(new \DateTimeImmutable());
            $commande->setDatePrestation(new \DateTimeImmutable($datePrestation));
            $commande->setHeureLivraison(new \DateTimeImmutable($heureLivraison));
            $commande->setAdresseLivraison($adresse);
            $commande->setVilleLivraison($ville);
            $commande->setNbPersonnes($nbPersonnes);
            $commande->setPrixMenu($menu->getPrix());
            $commande->setPrixLivraison($prixLivraison);
            $commande->setPrixTotal($prixTotal);
            $commande->setStatut('en_attente');
            $commande->setPretMateriel(false);
            $commande->setMaterielRestitue(false);

            //historique statut initial
            $historique = new HistoriqueStatut();
            $historique->setCommande($commande);
            $historique->setStatut('en_attente');
            $historique->setDateHeure(new \DateTimeImmutable());
            $historique->setCommentaire('Commande créée par le client');

            //décrémenta° stock si limité
            if ($menu->getStock() < 9999) {
                $menu->setStock($menu->getStock() - 1);
            }

            //persistance base PostgreSQL
            $em->persist($commande);
            $em->persist($historique);
            $em->flush();
            
            //sauvegarde MongoDB (stats analytiques),composant MongoDB dédié, données dupliquées pr l'analytique sans impacter performances PostgreSQL
            $this->mongoDBService->saveCommandeStat([
                'id'              => $commande->getId(),
                'numero_commande' => $numeroCommande,
                'menu_id'         => $menu->getId(),
                'menu_titre'      => $menu->getTitre(),
                'prix_total'      => $prixTotal,
                'nb_personnes'    => $nbPersonnes,
                'ville_livraison' => $ville,
                'statut'          => 'en_attente',
            ]);

            //mail confirma° via Brevo cURL (feature/brevo)
            $this->sendEmailConfirmation($commande, $menu, $nbPersonnes, $prixTotal, $prixLivraison, $ville);

            $this->addFlash('success', 'Votre commande ' . $numeroCommande . ' a bien été enregistrée !');
            return $this->redirectToRoute('app_user_dashboard');
        }

        return $this->render('commande/new.html.twig', [
            'menu' => $menu,
        ]);
    }

    /*mail confirmation via API Brevo (cURL direct), *call après persistance commande en base*/
    private function sendEmailConfirmation(
        Commande $commande,
        $menu,
        int $nbPersonnes,
        float $prixTotal,
        float $prixLivraison,
        string $ville
    ): void {
        // cast nécessaire car getUser() retourne UserInterface qui connaît pas getPrenom(), getNom() de entité User @var \App\Entity\User $user //
        $user = $this->getUser();

        //variables pr éviter erreurs concaténation HTML
        $prenom  = $user->getPrenom();
        $nom     = $user->getNom();
        $email   = $user->getEmail();
        $numero  = $commande->getNumeroCommande();
        $titre   = $menu->getTitre();
        $date    = $commande->getDatePrestation()->format('d/m/Y');
        $adresse = $commande->getAdresseLivraison() . ', ' . $ville;
        $frais   = $prixLivraison > 0 ? number_format($prixLivraison, 2) . ' €' : 'Gratuit';
        $total   = number_format($prixTotal, 2) . ' €';

        //html email
        $htmlContent = "<div style='font-family:Arial,sans-serif;color:#2C2B1A;padding:20px;max-width:600px;margin:auto'>
            <div style='background:#C4A882;padding:24px;text-align:center;border-radius:8px 8px 0 0'>
                <h1 style='color:#2C2B1A;margin:0;font-size:24px'>Vite &amp; Gourmand</h1>
            </div>
            <div style='background:#fff;padding:32px;border-radius:0 0 8px 8px;border:1px solid #e0ddd5'>
                <h2>Votre commande est confirmée !</h2>
                <p>Bonjour $prenom $nom,</p>
                <p>Votre commande <strong>$numero</strong> a bien été enregistrée.</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr style='border-bottom:1px solid #e0ddd5'>
                        <td style='padding:8px;color:#6B6B5A'>Menu</td>
                        <td style='padding:8px;font-weight:700'>$titre</td>
                    </tr>
                    <tr style='border-bottom:1px solid #e0ddd5'>
                        <td style='padding:8px;color:#6B6B5A'>Nombre de personnes</td>
                        <td style='padding:8px;font-weight:700'>$nbPersonnes</td>
                    </tr>
                    <tr style='border-bottom:1px solid #e0ddd5'>
                        <td style='padding:8px;color:#6B6B5A'>Date de prestation</td>
                        <td style='padding:8px;font-weight:700'>$date</td>
                    </tr>
                    <tr style='border-bottom:1px solid #e0ddd5'>
                        <td style='padding:8px;color:#6B6B5A'>Adresse de livraison</td>
                        <td style='padding:8px;font-weight:700'>$adresse</td>
                    </tr>
                    <tr style='border-bottom:1px solid #e0ddd5'>
                        <td style='padding:8px;color:#6B6B5A'>Frais de livraison</td>
                        <td style='padding:8px;font-weight:700'>$frais</td>
                    </tr>
                    <tr>
                        <td style='padding:8px;color:#6B6B5A'>Prix total</td>
                        <td style='padding:8px;font-weight:700;color:#C4693A;font-size:18px'>$total</td>
                    </tr>
                </table>
                <p style='font-size:13px;color:#6B6B5A'>
                    Vous pouvez suivre votre commande depuis votre espace personnel.
                </p>
                <p style='font-size:12px;color:#6B6B5A'>
                    Bordeaux depuis 1999 · contact@viteetgourmand.fr
                </p>
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
                    'to'          => [['email' => $email, 'name' => $prenom . ' ' . $nom]],
                    'subject'     => 'Confirmation de votre commande — Vite & Gourmand',
                    'htmlContent' => $htmlContent,
                ]),
            ]);
            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            error_log('BREVO COMMANDE ERROR: ' . $e->getMessage()); //s echec mail, commande reste enregistrée en base
        }
    }
}
