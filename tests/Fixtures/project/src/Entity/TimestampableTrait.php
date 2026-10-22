<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mapped properties imported from a trait, like Gedmo's Timestampable (issue #327)
 */
trait TimestampableTrait
{
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
