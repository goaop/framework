<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Owning side of a bidirectional many-to-one between two woven entities (issue #327)
 */
#[ORM\Entity]
#[ORM\Table(name: 'sensor_value')]
class SensorValue
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Sensor::class, inversedBy: 'values')]
        #[ORM\JoinColumn(nullable: false)]
        private Sensor $sensor,
        #[ORM\Column(type: 'integer')]
        private int $value,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSensor(): Sensor
    {
        return $this->sensor;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
