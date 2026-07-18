<?php

namespace App\Entity;

use App\Repository\ResetPasswordTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResetPasswordTokenRepository::class)]
class ResetPasswordToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'resetPasswordTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    #[ORM\Column(length: 20)]
    private ?string $selector = null;

    #[ORM\Column(length: 100)]
    private ?string $hashed_token = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expires_at = null;

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

    public function getSelector(): ?string
    {
        return $this->selector;
    }

    public function setSelector(string $selector): static
    {
        $this->selector = $selector;

        return $this;
    }

    public function getHashedToken(): ?string
    {
        return $this->hashed_token;
    }

    public function setHashedToken(string $hashed_token): static
    {
        $this->hashed_token = $hashed_token;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expires_at;
    }

    public function setExpiresAt(\DateTimeImmutable $expires_at): static
    {
        $this->expires_at = $expires_at;

        return $this;
    }
}
