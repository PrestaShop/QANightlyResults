<?php

namespace App\Entity;

use App\Repository\SuiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuiteRepository::class)]
class Suite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'suitesCollection')]
    private ?Execution $execution = null;

    #[ORM\Column(length: 50)]
    private ?string $uuid = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $title = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $campaign = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $file = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(name: 'hasSkipped', nullable: true)]
    private ?bool $hasSkipped = null;

    #[ORM\Column(name: 'hasPending', nullable: true)]
    private ?bool $hasPending = null;

    #[ORM\Column(name: 'hasPasses', nullable: true)]
    private ?bool $hasPasses = null;

    #[ORM\Column(name: 'hasFailures')]
    private ?bool $hasFailures = null;

    #[ORM\Column(name: 'totalSkipped', nullable: true)]
    private ?int $totalSkipped = null;

    #[ORM\Column(name: 'totalPending', nullable: true)]
    private ?int $totalPending = null;

    #[ORM\Column(name: 'totalPasses', nullable: true)]
    private ?int $totalPasses = null;

    #[ORM\Column(name: 'totalFailures', nullable: true)]
    private ?int $totalFailures = null;

    #[ORM\Column(name: 'hasSuites', nullable: true)]
    private ?bool $hasSuites = null;

    #[ORM\Column(name: 'hasTests', nullable: true)]
    private ?bool $hasTests = null;

    #[ORM\ManyToOne(inversedBy: 'suites')]
    private ?Suite $parent = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $insertion_date = null;

    /** @var Collection<int, Test> */
    #[ORM\OneToMany(mappedBy: 'suite', targetEntity: Test::class, orphanRemoval: true)]
    private Collection $tests;

    /** @var Collection<int, Suite> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Suite::class)]
    private Collection $suites;

    public function __construct()
    {
        $this->tests = new ArrayCollection();
        $this->suites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getExecution(): ?Execution
    {
        return $this->execution;
    }

    public function setExecution(?Execution $execution): static
    {
        $this->execution = $execution;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function setCampaign(?string $campaign): static
    {
        $this->campaign = $campaign;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function hasSkipped(): ?bool
    {
        return $this->hasSkipped;
    }

    public function setHasSkipped(?bool $hasSkipped): static
    {
        $this->hasSkipped = $hasSkipped;

        return $this;
    }

    public function hasPending(): ?bool
    {
        return $this->hasPending;
    }

    public function setHasPending(?bool $hasPending): static
    {
        $this->hasPending = $hasPending;

        return $this;
    }

    public function hasPasses(): ?bool
    {
        return $this->hasPasses;
    }

    public function setHasPasses(?bool $hasPasses): static
    {
        $this->hasPasses = $hasPasses;

        return $this;
    }

    public function hasFailures(): ?bool
    {
        return $this->hasFailures;
    }

    public function setHasFailures(bool $hasFailures): static
    {
        $this->hasFailures = $hasFailures;

        return $this;
    }

    public function getTotalSkipped(): ?int
    {
        return $this->totalSkipped;
    }

    public function setTotalSkipped(?int $totalSkipped): static
    {
        $this->totalSkipped = $totalSkipped;

        return $this;
    }

    public function getTotalPending(): ?int
    {
        return $this->totalPending;
    }

    public function setTotalPending(?int $totalPending): static
    {
        $this->totalPending = $totalPending;

        return $this;
    }

    public function getTotalPasses(): ?int
    {
        return $this->totalPasses;
    }

    public function setTotalPasses(?int $totalPasses): static
    {
        $this->totalPasses = $totalPasses;

        return $this;
    }

    public function getTotalFailures(): ?int
    {
        return $this->totalFailures;
    }

    public function setTotalFailures(?int $totalFailures): static
    {
        $this->totalFailures = $totalFailures;

        return $this;
    }

    public function getHasSuites(): ?bool
    {
        return $this->hasSuites;
    }

    public function setHasSuites(?bool $hasSuites): static
    {
        $this->hasSuites = $hasSuites;

        return $this;
    }

    public function getHasTests(): ?bool
    {
        return $this->hasTests;
    }

    public function setHasTests(?bool $hasTests): static
    {
        $this->hasTests = $hasTests;

        return $this;
    }

    public function getParent(): ?Suite
    {
        return $this->parent;
    }

    public function setParent(?Suite $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getInsertionDate(): ?\DateTime
    {
        return $this->insertion_date;
    }

    public function setInsertionDate(\DateTime $insertion_date): static
    {
        $this->insertion_date = $insertion_date;

        return $this;
    }

    /**
     * @return Collection<int, Test>
     */
    public function getTests(): Collection
    {
        return $this->tests;
    }

    /**
     * @param array<int, Test> $tests
     */
    public function setTests(array $tests): static
    {
        foreach ($this->tests as $test) {
            $this->removeTest($test);
        }
        foreach ($tests as $test) {
            $this->addTest($test);
        }

        return $this;
    }

    public function addTest(Test $test): static
    {
        if (!$this->tests->contains($test)) {
            $this->tests->add($test);
            $test->setSuite($this);
        }

        return $this;
    }

    public function removeTest(Test $test): static
    {
        if ($this->tests->removeElement($test)) {
            // set the owning side to null (unless already changed)
            if ($test->getSuite() === $this) {
                $test->setSuite(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Suite>
     */
    public function getSuites(): Collection
    {
        return $this->suites;
    }

    /**
     * @param array<int, Suite> $suites
     */
    public function setSuites(array $suites): static
    {
        foreach ($this->suites as $suite) {
            $this->removeSuite($suite);
        }
        foreach ($suites as $suite) {
            $this->addSuite($suite);
        }

        return $this;
    }

    public function addSuite(Suite $suite): static
    {
        if (!$this->suites->contains($suite)) {
            $this->suites->add($suite);
            $suite->setParent($this);
        }

        return $this;
    }

    public function removeSuite(Suite $suite): static
    {
        if ($this->suites->removeElement($suite)) {
            // set the owning side to null (unless already changed)
            if ($suite->getParent() === $this) {
                $suite->setParent(null);
            }
        }

        return $this;
    }
}
