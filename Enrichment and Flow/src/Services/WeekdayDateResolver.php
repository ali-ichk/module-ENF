<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
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

namespace Gibbon\Module\EnrichmentandFlow\Services;

use DateTimeImmutable;

class WeekdayDateResolver
{
    public function parseManualDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }

        return $date;
    }

    public function resolveInitialDate(DateTimeImmutable $today, array $activeWeekdayIDs): ?DateTimeImmutable
    {
        $weekdays = $this->normalizeWeekdayIDs($activeWeekdayIDs);

        if ($weekdays === []) {
            return null;
        }

        if (isset($weekdays[(int) $today->format('N')])) {
            return $today;
        }

        return $this->findNearestDate($today, $weekdays, 1);
    }

    public function resolvePreviousDate(DateTimeImmutable $selectedDate, array $activeWeekdayIDs): ?DateTimeImmutable
    {
        $weekdays = $this->normalizeWeekdayIDs($activeWeekdayIDs);

        if ($weekdays === []) {
            return null;
        }

        return $this->findNearestDate($selectedDate, $weekdays, -1);
    }

    public function resolveNextDate(DateTimeImmutable $selectedDate, array $activeWeekdayIDs): ?DateTimeImmutable
    {
        $weekdays = $this->normalizeWeekdayIDs($activeWeekdayIDs);

        if ($weekdays === []) {
            return null;
        }

        return $this->findNearestDate($selectedDate, $weekdays, 1);
    }

    /**
     * @param array<int, bool> $weekdays
     */
    private function findNearestDate(DateTimeImmutable $date, array $weekdays, int $direction): ?DateTimeImmutable
    {
        for ($offset = 1; $offset <= 7; $offset++) {
            $modifier = $direction > 0 ? "+{$offset} days" : "-{$offset} days";
            $candidate = $date->modify($modifier);

            if ($candidate !== false && isset($weekdays[(int) $candidate->format('N')])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $activeWeekdayIDs
     * @return array<int, bool>
     */
    private function normalizeWeekdayIDs(array $activeWeekdayIDs): array
    {
        $weekdays = [];

        foreach ($activeWeekdayIDs as $weekdayID) {
            if (is_int($weekdayID)) {
                $weekday = $weekdayID;
            } elseif (is_string($weekdayID) && preg_match('/^\s*\d+\s*$/D', $weekdayID) === 1) {
                $weekday = (int) trim($weekdayID);
            } else {
                continue;
            }

            if ($weekday >= 1 && $weekday <= 7) {
                $weekdays[$weekday] = true;
            }
        }

        return $weekdays;
    }
}
