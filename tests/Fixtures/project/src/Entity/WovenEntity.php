<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity woven by EntityFieldAspect: one intercepted column (becomes a hooked property
 * of the proxy), one plain column and an intercepted lifecycle callback (issue #671)
 */
#[ORM\Entity]
#[ORM\Table(name: 'woven_entity')]
#[ORM\HasLifecycleCallbacks]
class WovenEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    public string $name = '';

    #[ORM\Column(type: 'integer')]
    private int $counter = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    #[ORM\PrePersist]
    public function beforePersist(): void
    {
        ++$this->counter;
    }
}
