<?php
//requêtes prsnnalisées pr entité menu

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/*ici on a ttes les reuqêtes SQL/DQL qui cconcerne menu, étend ServiceEntityRepository qui fournit méthodes base --> find(), findAll(), findBy() */
/* utilisé pr menucontroller (liste,détail, filtres ajax) et homecontrollr (avis validés) */
class MenuRepository extends ServiceEntityRepository                    
{
    public function __construct(ManagerRegistry $registry)                       /*constructeur--> injecte ManagerRegistry de Doctrine, oblgtoire pr que sylfony instancie ce repository*/
    {
        parent::__construct($registry, Menu::class);
    }

     /*récupère menus actifs w/ filtres optionnels, utilisée par route AJAX /menu/filter, retourne --> iste des menus correspondant aux filtres */
    public function findWithFilters( /*récup menus actifs w/ filtres optionnels*/
        ?string $theme = null,       /*filtre thème menu*/
        ?string $regime = null,      /*filtre régime alimentat°*/
        ?float $prixMax = null,      /*filtre prix max*/
        ?int $nbPersonnes = null    /*filtre nb personnes*/
    ): array {
        $qb = $this->createQueryBuilder('m')               /*QueryBuilder --> construit requête SQL orientée objet et 'm' --> alias entité Menu */
            ->where('m.actif = :actif') // -->uniquement menus actifs
            ->setParameter('actif', 1)        
            ->orderBy('m.titre', 'ASC');    //-->tri alphabétique

        // conditions filtres
        // ajout filtre que si valeur non nulle et non vide
        if ($theme && $theme !== 'tous') {
            $qb->andWhere('m.theme = :theme')    //andwhere -> ajout condit° and à requête
               ->setParameter('theme', $theme); //setparameter -> protec° c/ injec° dql
        }

        if ($regime && $regime !== 'tous') {
            $qb->andWhere('m.regime = :regime')
               ->setParameter('regime', $regime);
        }

        if ($prixMax) {
            $qb->andWhere('m.prix <= :prixMax')      // Prix < ou = au max saisi
               ->setParameter('prixMax', $prixMax);
        }

        if ($nbPersonnes) {
            $qb->andWhere('m.nbPersonnesMin <= :nbPersonnes') // nb_personnes_min < ou = nb_personnes saisi par utilisateur
               ->setParameter('nbPersonnes', $nbPersonnes);
        }

        return $qb->getQuery()->getResult();  //getQuery() --> convertit QueryBuilder en requête DQL et  getResult() exécute requête et retourne tableau w/ objets Menu
    }

    /** récupère thèmes distincts des menus actifs, alimente dynmqmnt filtre catalogue (MenuController::index) qd ajout new thème en bdd.
     * + retourne tableau (ex: ["Classique", "Événement", "Santé"])*/
    public function findThemesDistincts(): array
    {
        return $this->createQueryBuilder('m')
            ->select('DISTINCT m.theme') // DISTINCT évite les doublons
            ->where('m.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('m.theme', 'ASC')
            ->getQuery()
            ->getSingleColumnResult(); // retourne un tableau de valeurs simples
    }

    //régimes distincts des menus actifs, alimente dynmqmnt filtre catalogue (MenuController::index)+ retourne tableau ex: ["Standard", "Végétarien", "Vegan"]
    public function findRegimesDistincts(): array
    {
        return $this->createQueryBuilder('m')
            ->select('DISTINCT m.regime')
            ->where('m.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('m.regime', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}

