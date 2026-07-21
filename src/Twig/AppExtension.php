<?php

//variables globles twig, inject° horaire dans ts les templates sans les passer manuellemnt ds ts les controller. Bien pr données transvrsles//

namespace App\Twig;

use App\Repository\HoraireRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private HoraireRepository $horaireRepo
    ) {}

    //horaires dispo pr ts templates Twig, via la variable {{ horaires }}//
    public function getGlobals(): array
    {
        return [
            'horaires' => $this->horaireRepo->findAll(),
        ];
    }
}