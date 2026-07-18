<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $menu = null;

    #[ORM\Column(length: 50)]
    private ?string $numero_commande = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $date_commande = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date_prestation = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $heure_livraison = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse_livraison = null;

    #[ORM\Column(length: 100)]
    private ?string $ville_livraison = null;

    #[ORM\Column]
    private ?int $nb_personnes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix_menu = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix_livraison = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix_total = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?bool $pret_materiel = null;

    #[ORM\Column]
    private ?bool $materiel_restitue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getNumeroCommande(): ?string
    {
        return $this->numero_commande;
    }

    public function setNumeroCommande(string $numero_commande): static
    {
        $this->numero_commande = $numero_commande;

        return $this;
    }

    public function getDateCommande(): ?\DateTimeImmutable
    {
        return $this->date_commande;
    }

    public function setDateCommande(\DateTimeImmutable $date_commande): static
    {
        $this->date_commande = $date_commande;

        return $this;
    }

    public function getDatePrestation(): ?\DateTimeImmutable
    {
        return $this->date_prestation;
    }

    public function setDatePrestation(\DateTimeImmutable $date_prestation): static
    {
        $this->date_prestation = $date_prestation;

        return $this;
    }

    public function getHeureLivraison(): ?\DateTimeImmutable
    {
        return $this->heure_livraison;
    }

    public function setHeureLivraison(\DateTimeImmutable $heure_livraison): static
    {
        $this->heure_livraison = $heure_livraison;

        return $this;
    }

    public function getAdresseLivraison(): ?string
    {
        return $this->adresse_livraison;
    }

    public function setAdresseLivraison(string $adresse_livraison): static
    {
        $this->adresse_livraison = $adresse_livraison;

        return $this;
    }

    public function getVilleLivraison(): ?string
    {
        return $this->ville_livraison;
    }

    public function setVilleLivraison(string $ville_livraison): static
    {
        $this->ville_livraison = $ville_livraison;

        return $this;
    }

    public function getNbPersonnes(): ?int
    {
        return $this->nb_personnes;
    }

    public function setNbPersonnes(int $nb_personnes): static
    {
        $this->nb_personnes = $nb_personnes;

        return $this;
    }

    public function getPrixMenu(): ?string
    {
        return $this->prix_menu;
    }

    public function setPrixMenu(string $prix_menu): static
    {
        $this->prix_menu = $prix_menu;

        return $this;
    }

    public function getPrixLivraison(): ?string
    {
        return $this->prix_livraison;
    }

    public function setPrixLivraison(string $prix_livraison): static
    {
        $this->prix_livraison = $prix_livraison;

        return $this;
    }

    public function getPrixTotal(): ?string
    {
        return $this->prix_total;
    }

    public function setPrixTotal(string $prix_total): static
    {
        $this->prix_total = $prix_total;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isPretMateriel(): ?bool
    {
        return $this->pret_materiel;
    }

    public function setPretMateriel(bool $pret_materiel): static
    {
        $this->pret_materiel = $pret_materiel;

        return $this;
    }

    public function isMaterielRestitue(): ?bool
    {
        return $this->materiel_restitue;
    }

    public function setMaterielRestitue(bool $materiel_restitue): static
    {
        $this->materiel_restitue = $materiel_restitue;

        return $this;
    }
}
