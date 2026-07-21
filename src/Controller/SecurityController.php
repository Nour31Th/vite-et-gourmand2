<?php

// gère authentifica° utilisateurs//
//routes: /login ->affiche+traite formulaire connex°, /logout->déconnecte user, redirect-after-login ->redirige user connex° selon rôle //

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /*page connexion,GET /login -> formulaire de connexion, POST /login : Symfony Security intercepte automtqmnt soumi° + vérif mail + mdp*/
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        //si utilisateur déjà connecté->redirige vers page accueil//
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError(); //récup erreur connex° si elle existe (ex: "identifiants incorrects")

        $lastUsername = $authenticationUtils->getLastUsername(); //récup dernier email saisi pr utilisateur en cas d'erreur, pr pré-remplir formulaire//

        //formulaire connex° via template twig w/ données
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,            //last email saisi par user
            'error' => $error,                           //message erreur eventuel (ex: "identifiants incorrects")
        ]);
    }
    
    /*déconnex°,méthode vide car Symfony Security gère via route /logout, dc pas besoin code PHP ici
     *logicException là pr sigaler que code pas exécuté.*/
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('cette méthode peut rester vide car elle est interceptée par le firewall de sécurité de Symfony.');
    }

    /*redirec° après connex° selon rôle, hiérarchie des rôles->vérifie d'abord rôle le + élevé*/
    #[Route('/redirect-after-login', name: 'app_redirect_after_login')]
    public function redirectAfterLogin(): Response
    {
        //vérif si l'utilisateur=admin (rôle le+élevé)
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        //vérif si l'utilisateur=employé
        if ($this->isGranted('ROLE_EMPLOYE')) {
            return $this->redirectToRoute('app_employe_dashboard');
        }

        //vérif si l'utilisateur=client
        return $this->redirectToRoute('app_user_dashboard');
    }
}


