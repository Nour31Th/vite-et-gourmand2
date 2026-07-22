<?php

//vérif compta ctif pddt connex°, service automtq symfony pr tentative connex°, si inactif connex° refusé w/ msg erreur//

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

//save dans config/services.yaml comme UserChecker.
class UserCheckerListener implements UserCheckerInterface
{
    //call avt authentifica°, vérif compte existe+actif
    public function checkPreAuth(UserInterface $user): void
    {
        //vérif que instances entité User
        if (!$user instanceof User) {
            return;
        }

        //si compte désac par admin->connexion refusée
        if (!$user->isActif()) {
            throw new CustomUserMessageAuthenticationException(
                'Votre compte a été désactivé. Contactez-nous à contact@viteetgourmand.fr'
            );
        }
    }

    //call après authentifica°, vide icicar pas de vérification post-auth nécessaire
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // rien à faire ap authtfca°
    }
}
