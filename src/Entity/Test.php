<?php

namespace App\Entity;

use App\Repository\TestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestRepository::class)]
class Test
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Suite $suite = null;

    #[ORM\Column(length: 50)]
    private ?string $uuid = null;

    #[ORM\Column(length: 200)]
    private string $identifier = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $title;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $state = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error_message = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stack_trace = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $diff = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $insertion_date;

    private ?string $formattedStackTrace = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getSuite(): ?Suite
    {
        return $this->suite;
    }

    public function setSuite(?Suite $suite): static
    {
        $this->suite = $suite;

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function setErrorMessage(?string $error_message): static
    {
        $this->error_message = $error_message;

        return $this;
    }

    public function getStackTrace(): ?string
    {
        return $this->stack_trace;
    }

    public function setStackTrace(?string $stack_trace): static
    {
        $this->stack_trace = $stack_trace;

        return $this;
    }

    public function getFormattedStackTrace(): ?string
    {
        return $this->formattedStackTrace;
    }

    public function setFormattedStackTrace(?string $formattedStackTrace): static
    {
        $this->formattedStackTrace = $formattedStackTrace;

        return $this;
    }

    public function getDiff(): ?string
    {
        return $this->diff;
    }

    public function setDiff(?string $diff): static
    {
        $this->diff = $diff;

        return $this;
    }

    public function getInsertionDate(): ?\DateTimeInterface
    {
        return $this->insertion_date;
    }

    public function setInsertionDate(\DateTimeInterface $insertion_date): static
    {
        $this->insertion_date = $insertion_date;

        return $this;
    }
}
