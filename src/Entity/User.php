<?php

//utilisateurs application, 3 types (client connecté, employé, admin)// 
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 *ROLE_USER->client connecté, ROLE_EMPLOYE->employé (hérite de ROLE_USER), ROLE_ADMIN-> admin(hérite de ROLE_EMPLOYE et ROLE_USER)
 *implémente 2 interfaces Symfony Security : UserInterface->méthodes requises système sécu, PasswordAuthenticatedUserInterface->indique que le mot de passe est hashé
 *
 * contraintes Validator : mail unique en bdd (UniqueEntity), mail valide (Assert\Email), nom et prénom obligatoires (Assert\NotBlank)
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(
    fields: ['email'],
    message: 'Cette adresse email est déjà utilisée.'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    //id auto-incrémenté en bdd, généré automatiquement par PostgreSQL (SERIAL)
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    //mail->id unique ocnnex°, utilisé par symfony securitu pr idtfier utilisateur, défini dans security.yaml->property: email
    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide.')]
    private ?string $email = null;

    //rôles stockés en JSON dans colonne BDD, Ex: ["ROLE_ADMIN"] ou ["ROLE_EMPLOYE"] ou [], getRoles() ajoute tjrs ROLE_USER automatqmnt
    #[ORM\Column]
    private array $roles = [];

    //mdp hashé w/ bcrypt, jamais stocké en clair,hashé via UserPasswordHasherInterface
    #[ORM\Column]
    private ?string $password = null;

    //nom de famille utilisateur
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    //prénom utilisateur
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $prenom = null;

    // gsm en option, use pr commande
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $gsm = null;

    // adresse optionnelle, pré-remplie pr formulaire commande
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    //ville optionnelle, pré-remplie pr formulaire commande
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    //code postal en option
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    // statut actif/inactif du compte, inactif=pas de connexion, géré par admin via AdminController::activerEmploye() et vérifpar UserCheckerListener avt chaque conneion
    #[ORM\Column]
    private bool $actif = true;

    //commandes faites par utilisateur, OneToMany->utilisateur peut avoir plusieurs commandes, mappedBy->propriété 'utilisateur' dans Commande pointe vers User
    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Commande::class)]
    private Collection $commandes;

    //avis utilisateur,OneToMany->utilisateur peut avoir plusieurs avis
    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Avis::class)]
    private Collection $avis;

    //tokens réinitialisa° mdp, OneToMany->utilisateur peut avoir plusieurs tokens (en pratique 1 seul actif à la fois, anciens suppr)
    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: ResetPasswordToken::class)]
    private Collection $resetPasswordTokens;

    //constructeur initialise collections Doctrine, ArrayCollection-> collection par défaut de Doctrine qui doit être initialisée dans le constructeur
    public function __construct()
    {
        $this->commandes           = new ArrayCollection();
        $this->avis                = new ArrayCollection();
        $this->resetPasswordTokens = new ArrayCollection();
    }

    //getters et setters ici

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    //getUserIdentifier retourne id de l'utilisateur, requis par UserInterface, symfony security use valeur pr idtfier utilisateur en session, id=email.
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    //getRoles retourne rôles utilisateur, requis par UserInterface//
    //ici tjrs ajout ROLE_USER à la liste, même utilisateur sans rôle explicite a ROLE_USER
    //array_unique() évite doublons si ROLE_USER déjà dans $roles
    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    //getPassword retourne mdp hashé requis par PasswordAuthenticatedUserInterface
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    //eraseCredentials efface données sensibles temporaires, requis par UserInterface
    //vide ici var stocke pas mdp en clair
    public function eraseCredentials(): void
    {
        //rien à effacer car mdp en clair jamais stocké
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getGsm(): ?string
    {
        return $this->gsm;
    }

    public function setGsm(?string $gsm): static
    {
        $this->gsm = $gsm;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    //isActif retourne statut actif/inactif, vérif  UserCheckerListener à chaque connexion
    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    //getCommandes retourne ttes les commandes de l'utilisateur utilisé ds UserController::dashboard()
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    //getAvis retourne ts les avis utilisateurs
    public function getAvis(): Collection
    {
        return $this->avis;
    }

    // getResetPasswordTokens retourne tokens de réinitialisa°
    public function getResetPasswordTokens(): Collection
    {
        return $this->resetPasswordTokens;
    }
}