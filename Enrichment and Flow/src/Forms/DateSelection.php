<?php

/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
(https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

declare(strict_types=1);

namespace Gibbon\Module\EnrichmentandFlow\Forms;

use DateTimeImmutable;
use InvalidArgumentException;

final class DateSelection
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private readonly array $blocks;

    /**
     * @var list<int>
     */
    private readonly array $activeWeekdayIDs;

    private readonly string $selectedDate;
    private readonly ?string $previousDate;
    private readonly ?string $nextDate;

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param list<int> $activeWeekdayIDs
     */
    public function __construct(
        array $blocks,
        array $activeWeekdayIDs,
        string $selectedDate,
        ?string $previousDate,
        ?string $nextDate
    ) {
        if (!$this->isCompleteISODate($selectedDate)) {
            throw new InvalidArgumentException('Selected date must be a valid ISO date in Y-m-d format.');
        }

        $this->blocks = $blocks;
        $this->activeWeekdayIDs = $activeWeekdayIDs;
        $this->selectedDate = $selectedDate;
        $this->previousDate = $previousDate;
        $this->nextDate = $nextDate;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @return list<int>
     */
    public function getActiveWeekdayIDs(): array
    {
        return $this->activeWeekdayIDs;
    }

    public function hasActiveBlocks(): bool
    {
        return $this->blocks !== [];
    }

    public function hasActiveWeekdaySchedule(): bool
    {
        return $this->activeWeekdayIDs !== [];
    }

    public function getSelectedDate(): string
    {
        return $this->selectedDate;
    }

    public function getPreviousDate(): ?string
    {
        return $this->previousDate;
    }

    public function getNextDate(): ?string
    {
        return $this->nextDate;
    }

    private function isCompleteISODate(string $date): bool
    {
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $date) !== 1) {
            return false;
        }

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        return $parsedDate !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsedDate->format('Y-m-d') === $date;
    }
}
