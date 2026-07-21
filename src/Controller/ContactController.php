<?php

//formulaire contact, routes : GET/contact->affiche formulaire, POST/contact->sauvegarde message en BDD+mail notif brevo

namespace App\Controller;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(
        Request $request,
        EntityManagerInterface $em
    ): Response {

        if ($request->isMethod('POST')) {

            // Vérification CSRF
            if (!$this->isCsrfTokenValid('contact', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_contact');
            }

            $titre       = trim($request->request->get('titre'));
            $description = trim($request->request->get('description'));
            $email       = trim($request->request->get('email'));

            //valid° champs obligatoires
            if (empty($titre) || empty($description) || empty($email)) {
                $this->addFlash('danger', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_contact');
            }

            //sauvgrde en bdd
            $contact = new Contact();
            $contact->setTitre($titre);
            $contact->setDescription($description);
            $contact->setEmail($email);
            $contact->setDate(new \DateTimeImmutable());

            $em->persist($contact);
            $em->flush();

            //mail notif° Brevo
            $this->sendEmailContact($titre, $description, $email);

            $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig');
    }

    //notif ° msg email brevo
    private function sendEmailContact(
        string $titre,
        string $description,
        string $emailExpediteur
    ): void {
        $html = "<div style='font-family:Arial,sans-serif;color:#2C2B1A;padding:20px;max-width:600px;margin:auto'>
            <div style='background:#C4A882;padding:24px;text-align:center;border-radius:8px 8px 0 0'>
                <h1 style='color:#2C2B1A;margin:0;font-size:24px'>Vite &amp; Gourmand</h1>
            </div>
            <div style='background:#fff;padding:32px;border-radius:0 0 8px 8px;border:1px solid #e0ddd5'>
                <h2>Nouveau message de contact</h2>
                <p><strong>De :</strong> $emailExpediteur</p>
                <p><strong>Sujet :</strong> $titre</p>
                <p><strong>Message :</strong></p>
                <p style='background:#f5f2ec;padding:12px;border-radius:4px;border-left:3px solid #C4A882'>
                    $description
                </p>
                <p style='font-size:12px;color:#6B6B5A'>
                    Message reçu via le formulaire de contact du site.
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
                    'to'          => [['email' => 'contact@viteetgourmand.fr', 'name' => 'Vite & Gourmand']],
                    'replyTo'     => ['email' => $emailExpediteur],
                    'subject'     => 'Nouveau message de contact : ' . $titre,
                    'htmlContent' => $html,
                ]),
            ]);
            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            error_log('BREVO CONTACT ERROR: ' . $e->getMessage());
        }
    }
}
