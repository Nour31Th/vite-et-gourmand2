<?php

//gère inscription new clients, route GET/register --> affiche formulaire, route POST/register-->traite inscrip°//

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register (
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em 
    ): Response {
       if ($this->getUser()) {                                //si user déjà connecté--> redirection//
           return $this->redirectToRoute('app_home');
        }  
        
        if ($request->isMethod('POST')) {                      //traitmnt formulaire//
            if(!$this->isCsrfTokenValid('register', $request->request->get('_token'))) {         //vérifica° c/ attaques//
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_register');
            }

            $nom      = trim($request->request->get('nom'));
            $prenom   = trim($request->request->get('prenom'));
            $email    = trim($request->request->get('email'));
            $password = $request->request->get('password');
            $confirm  = $request->request->get('confirm_password');
            
            if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {                //valida° données//
                $this->addFlash('danger', 'Tous les champs obligatoires doivent être remplis.');
                return $this->redirectToRoute('app_register');
            }

            if ($password !== $confirm) {
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_register');
            }

            if (strlen($password) < 8) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->redirectToRoute('app_register');
            }

            $user = new User();                  //création new utilisateur//
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $user->setActif(true);
            $user->setPassword(                 //hashage mdp avt stockage//
                $passwordHasher->hashPassword($user, $password)
            );

            $em->persist($user);            //sauvegarde bdd//
            $em->flush();

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

    return $this->render('registration/register.html.twig');
    }
}
