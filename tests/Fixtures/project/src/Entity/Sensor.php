<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Inverse side of a bidirectional one-to-many between two woven entities (issue #327)
 */
#[ORM\Entity]
#[ORM\Table(name: 'sensor')]
class Sensor
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /** @var Collection<int, SensorValue> */
    #[ORM\OneToMany(targetEntity: SensorValue::class, mappedBy: 'sensor', cascade: ['persist'])]
    private Collection $values;

    public function __construct(
        #[ORM\Column(type: 'string', length: 64)]
        private string $name,
    ) {
        $this->values = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Collection<int, SensorValue>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(int $value): SensorValue
    {
        $sensorValue = new SensorValue($this, $value);
        $this->values->add($sensorValue);

        return $sensorValue;
    }
}
