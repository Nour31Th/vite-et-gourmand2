<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/** Security Controller --> gère authentification utilisateurs 
 * Ici il gère 3 routes:
 * -/login qui affiche et traite le formulaire de connexion
 * -/logout qui déconnecte l'utilisateur (automatiquement géré par Symfony)
 * -/redirect-after-login qui redirige l'utilisateur après connexion selon son rôle
 */
class SecurityController extends AbstractController
{
    /**
     * Page de connexion
     * 
     * GET /login  : affiche le formulaire de connexion
     * POST /login : Symfony Security intercepte automatiquement la soumission
     *               et vérifie email + mot de passe. Pas besoin de code PHP ici.
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, on le redirige vers la page d'accueil, pour éviter qu'il ne se reconnecte inutilement.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Récupère l'erreur de connexion si elle existe (ex: "identifiants incorrects")
        $error = $authenticationUtils->getLastAuthenticationError();

        // Récupère le dernier email saisi par l'utilisateur en cas d'erreur, pour le pré-remplir dans le formulaire pour améliorer l'expérience utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        // Affiche le formulaire de connexion via template twig avec bonnes données
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,            //dernier email saisi par l'utilisateur
            'error' => $error,                           //message d'erreur eventuel (ex: "identifiants incorrects")
        ]);
    }
    
    /**
     * Déconnexion
     * 
     * méthode vide car Symfony Security gère automatiquement la déconnexion via la route /logout, donc pas besoin de code PHP ici.
     * LogicException là pour sigaler que ce code ne sera pas exécuté.
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('cette méthode peut rester vide car elle est interceptée par le firewall de sécurité de Symfony.');
    }

    /**
     * Redirection après connexion selon rôle
     * après connexion, Symfony redirige l'utilisateur vers cette route
     * vérification du rôle de l'utilisateur pour le rediriger vers la page appropriée : 
     *  - ROLE_ADMIN => /admin/dashboard
     * - ROLE_EMPLOYE => /employe/dashboard
     * - ROLE_USER => /user/dashboard
     * comme il y a la hiérarchie des rôles, on vérifie d'abord le rôle le plus élevé 
     * */
    #[Route('/redirect-after-login', name: 'app_redirect_after_login')]
    public function redirectAfterLogin(): Response
    {
        // Vérifie si l'utilisateur est l'admin (rôle le plus élevé)
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Vérifie si l'utilisateur est un employé
        if ($this->isGranted('ROLE_EMPLOYE')) {
            return $this->redirectToRoute('app_employe_dashboard');
        }

        // Par défaut, utilisateur est un client
        return $this->redirectToRoute('app_user_dashboard');
    }
}


