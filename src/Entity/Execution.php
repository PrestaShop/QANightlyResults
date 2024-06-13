<?php

namespace App\Entity;

use App\Repository\ExecutionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExecutionRepository::class)]
class Execution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $ref = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $start_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $end_date = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column(length: 20)]
    private ?string $version = null;

    #[ORM\Column(length: 50)]
    private string $campaign = 'functional';

    #[ORM\Column(length: 50, nullable: false)]
    private string $platform = 'chromium';

    #[ORM\Column(name: '`database`', length: 50, nullable: false)]
    private string $database = 'mysql';

    #[ORM\Column(nullable: true)]
    private ?int $suites = null;

    #[ORM\Column(nullable: true)]
    private ?int $tests = null;

    #[ORM\Column(nullable: true)]
    private ?int $skipped = null;

    #[ORM\Column(nullable: true)]
    private ?int $pending = null;

    #[ORM\Column(nullable: true)]
    private ?int $passes = null;

    #[ORM\Column(nullable: true)]
    private ?int $failures = null;

    #[ORM\Column(nullable: true)]
    private ?int $broken_since_last = null;

    #[ORM\Column(nullable: true)]
    private ?int $fixed_since_last = null;

    #[ORM\Column(nullable: true)]
    private ?int $equal_since_last = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $insertion_start_date;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $insertion_end_date;

    /** @var Collection<int, Suite> */
    #[ORM\OneToMany(mappedBy: 'execution', targetEntity: Suite::class)]
    private Collection $suitesCollection;

    public function __construct()
    {
        $this->suitesCollection = new ArrayCollection();
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setDatabase(string $database): static
    {
        $this->database = $database;

        return $this;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(string $ref): static
    {
        $this->ref = $ref;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTime $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->end_date;
    }

    public function setEndDate(?\DateTime $end_date): static
    {
        $this->end_date = $end_date;

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

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getCampaign(): string
    {
        return $this->campaign;
    }

    public function setCampaign(string $campaign): static
    {
        $this->campaign = $campaign;

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): static
    {
        $this->platform = $platform;

        return $this;
    }

    public function getSuites(): ?int
    {
        return $this->suites;
    }

    public function setSuites(?int $suites): static
    {
        $this->suites = $suites;

        return $this;
    }

    public function getTests(): ?int
    {
        return $this->tests;
    }

    public function setTests(?int $tests): static
    {
        $this->tests = $tests;

        return $this;
    }

    public function getSkipped(): ?int
    {
        return $this->skipped;
    }

    public function setSkipped(?int $skipped): static
    {
        $this->skipped = $skipped;

        return $this;
    }

    public function getPending(): ?int
    {
        return $this->pending;
    }

    public function setPending(?int $pending): static
    {
        $this->pending = $pending;

        return $this;
    }

    public function getPasses(): ?int
    {
        return $this->passes;
    }

    public function setPasses(?int $passes): static
    {
        $this->passes = $passes;

        return $this;
    }

    public function getFailures(): ?int
    {
        return $this->failures;
    }

    public function setFailures(?int $failures): static
    {
        $this->failures = $failures;

        return $this;
    }

    public function getBrokenSinceLast(): ?int
    {
        return $this->broken_since_last;
    }

    public function setBrokenSinceLast(?int $broken_since_last): static
    {
        $this->broken_since_last = $broken_since_last;

        return $this;
    }

    public function getFixedSinceLast(): ?int
    {
        return $this->fixed_since_last;
    }

    public function setFixedSinceLast(?int $fixed_since_last): static
    {
        $this->fixed_since_last = $fixed_since_last;

        return $this;
    }

    public function getEqualSinceLast(): ?int
    {
        return $this->equal_since_last;
    }

    public function setEqualSinceLast(?int $equal_since_last): static
    {
        $this->equal_since_last = $equal_since_last;

        return $this;
    }

    public function getInsertionStartDate(): ?\DateTimeInterface
    {
        return $this->insertion_start_date;
    }

    public function setInsertionStartDate(\DateTimeInterface $insertion_start_date): static
    {
        $this->insertion_start_date = $insertion_start_date;

        return $this;
    }

    public function getInsertionEndDate(): ?\DateTimeInterface
    {
        return $this->insertion_end_date;
    }

    public function setInsertionEndDate(\DateTimeInterface $insertion_end_date): static
    {
        $this->insertion_end_date = $insertion_end_date;

        return $this;
    }

    /**
     * @return Collection<int, Suite>
     */
    public function getSuitesCollection(): Collection
    {
        return $this->suitesCollection;
    }

    public function addSuite(Suite $suite): static
    {
        if (!$this->suitesCollection->contains($suite)) {
            $this->suitesCollection->add($suite);
            $suite->setExecution($this);
        }

        return $this;
    }

    public function removeSuite(Suite $suite): static
    {
        if ($this->suitesCollection->removeElement($suite)) {
            // set the owning side to null (unless already changed)
            if ($suite->getExecution() === $this) {
                $suite->setExecution(null);
            }
        }

        return $this;
    }
}
