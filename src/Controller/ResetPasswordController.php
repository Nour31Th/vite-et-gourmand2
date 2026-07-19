<?php
// gère réinitialisation du mdp//
namespace App\Controller;

use App\Entity\ResetPasswordToken;
use App\Repository\UserRepository;
use App\Repository\ResetPasswordTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset/password', name: 'app_reset_password')]
    public function request (
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em 
    ): Response {
        
        if ($request->isMethod('POST')) {

            if (!$this->isCsrfTokenValid('reset_password', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_reset_password');
            }

            $email = trim($request->request->get('email'));
            $user  = $userRepo->findOneBy(['email' => $email]);

             if ($user) {                     // Généra° token sécurisé
                $selector    = bin2hex(random_bytes(8));   // 16 caractères partie publique
                $plainToken  = bin2hex(random_bytes(32));  // 64 caractères partie secrète
                $hashedToken = hash('sha256', $plainToken); // hashé avant stockage

                $token = new ResetPasswordToken();
                $token->setUtilisateur($user);
                $token->setSelector($selector);
                $token->setHashedToken($hashedToken);
                $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));  // expire après 1h

                $em->persist($token);
                $em->flush();

                // TODO : envoyer l'email via Brevo (feature/brevo)
                // URL du lien : /reset-password/$selector?token=$plainToken
            }

            $this->addFlash('success', 'Si votre email existe, un lien de réinitialisation vous a été envoyé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/request.html.twig');
    }

    // Formulaire new mdp, utilisateur clique s/ lien reçu par mail
    #[Route('/reset-password/{selector}', name: 'app_reset_password_confirm')]
    public function confirm(
        string $selector,
        Request $request,
        ResetPasswordTokenRepository $tokenRepo,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {

        $tokenEntity = $tokenRepo->findOneBy(['selector' => $selector]);             // Recherche token w/ selector

        if (!$tokenEntity || $tokenEntity->getExpiresAt() < new \DateTimeImmutable()) {     // Vérifica° si token existe et pas expiré
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_reset_password');
        }

        if ($request->isMethod('POST')) {

            if (!$this->isCsrfTokenValid('reset_confirm', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_reset_password');
            }

            $plainToken  = $request->request->get('token');   // Vérifica° token secret
            $hashedToken = hash('sha256', $plainToken);

            if (!hash_equals($tokenEntity->getHashedToken(), $hashedToken)) {
                $this->addFlash('danger', 'Lien invalide.');
                return $this->redirectToRoute('app_reset_password');
            }

            $password = $request->request->get('password');
            $confirm  = $request->request->get('confirm_password');

            if ($password !== $confirm) {
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_reset_password_confirm', ['selector' => $selector]);
            }

            $user = $tokenEntity->getUtilisateur();                               // Maj mdp
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $em->remove($tokenEntity);    // Suppr° token utilisé
            $em->flush();

            $this->addFlash('success', 'Mot de passe modifié avec succès !');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/confirm.html.twig', [
            'selector' => $selector,
            'token'    => $request->query->get('token'),
        ]);
    }






           
}
