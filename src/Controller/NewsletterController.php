<?php

//inscript° newsletter, route POST /newsletter->inscrip°newsletter, mail sauvgrd bdd et ajout list brevo

namespace App\Controller;

use App\Entity\Newsletter;
use App\Repository\NewsletterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NewsletterController extends AbstractController
{
    #[Route('/newsletter', name: 'app_newsletter', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EntityManagerInterface $em,
        NewsletterRepository $newsletterRepo
    ): Response {

        //verif CSRF
        if (!$this->isCsrfTokenValid('newsletter', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_home');
        }

        $email = trim($request->request->get('email'));

        //valid°email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse email invalide.');
            return $this->redirectToRoute('app_home');
        }

        //verif si déjà inscrit
        if ($newsletterRepo->findOneBy(['email' => $email])) {
            $this->addFlash('info', 'Cette adresse email est déjà inscrite à notre newsletter.');
            return $this->redirectToRoute('app_home');
        }

        //sauvegrd bdd
        $newsletter = new Newsletter();
        $newsletter->setEmail($email);
        $newsletter->setDateInscription(new \DateTimeImmutable());

        $em->persist($newsletter);
        $em->flush();

        //ajout contact ds Brevo via API
        $this->addContactBrevo($email);

        $this->addFlash('success', 'Vous êtes bien inscrit(e) à notre newsletter !');
        return $this->redirectToRoute('app_home');
    }

    //ajout contact ds liste newsletter Brevo
    private function addContactBrevo(string $email): void
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.brevo.com/v3/contacts',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'api-key: ' . $_ENV['BREVO_API_KEY'],
                    'content-type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'email'          => $email,
                    'listIds'        => [2], //id liste newsletter dans Brevo
                    'updateEnabled'  => true,
                ]),
            ]);
            curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $e) {
            error_log('BREVO NEWSLETTER ERROR: ' . $e->getMessage());
        }
    }
}