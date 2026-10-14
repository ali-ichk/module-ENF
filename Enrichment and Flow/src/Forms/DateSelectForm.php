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

namespace Gibbon\Module\EnrichmentandFlow\Forms;

use DateTimeImmutable;
use Gibbon\Contracts\Services\Session;
use Gibbon\Http\Url;
use Gibbon\Forms\Form;
use Gibbon\Module\EnrichmentandFlow\Domain\BlockGateway;
use Gibbon\Module\EnrichmentandFlow\Services\WeekdayDateResolver;
use Gibbon\Services\Format;
use LogicException;

/**
 * DateSelectForm
 */
class DateSelectForm
{
    private Session $session;
    private BlockGateway $blockGateway;
    private WeekdayDateResolver $dateResolver;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $blocks = [];

    /**
     * @var list<int>
     */
    private array $activeWeekdayIDs = [];

    private ?string $selectedDate = null;
    private ?string $previousDate = null;
    private ?string $nextDate = null;
    private bool $prepared = false;

    public function __construct(
        Session $session,
        BlockGateway $blockGateway,
        WeekdayDateResolver $dateResolver
    ) {
        $this->session = $session;
        $this->blockGateway = $blockGateway;
        $this->dateResolver = $dateResolver;
    }

    /**
     * Prepare the request-scoped date selection state.
     *
     * @param array<string, mixed>|null $request
     */
    public function setDate(
        ?array $request = null,
        ?DateTimeImmutable $today = null
    ): DateSelectForm {
        $this->blocks = [];
        $this->activeWeekdayIDs = [];
        $this->selectedDate = null;
        $this->previousDate = null;
        $this->nextDate = null;
        $this->prepared = false;

        $blocks = $this->blockGateway->selectBlocks()->fetchAll();
        $activeWeekdayIDs = $this->extractActiveWeekdayIDs($blocks);
        $today ??= new DateTimeImmutable('today');

        $requestDate = null;
        if ($request !== null && array_key_exists('date', $request) && is_string($request['date'])) {
            $requestDate = $request['date'];
        }

        $manualDate = $this->dateResolver->parseManualDate($requestDate);
        $selectedDate = $manualDate;

        if ($selectedDate === null) {
            if ($blocks !== [] && $activeWeekdayIDs !== []) {
                $selectedDate = $this->dateResolver->resolveInitialDate($today, $activeWeekdayIDs) ?? $today;
            } else {
                $selectedDate = $today;
            }
        }

        $previousDate = null;
        $nextDate = null;
        if ($blocks !== [] && $activeWeekdayIDs !== []) {
            $navigationDates = $this->dateResolver->resolveNavigationDates($selectedDate, $activeWeekdayIDs);
            $previousDate = $navigationDates['previousDate']?->format('Y-m-d');
            $nextDate = $navigationDates['nextDate']?->format('Y-m-d');
        }

        $selectedDateString = $selectedDate->format('Y-m-d');

        // Replace the complete state only after all values have been prepared successfully.
        $this->blocks = $blocks;
        $this->activeWeekdayIDs = $activeWeekdayIDs;
        $this->selectedDate = $selectedDateString;
        $this->previousDate = $previousDate;
        $this->nextDate = $nextDate;
        $this->prepared = true;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlocks(): array
    {
        $this->assertPrepared();

        return $this->blocks;
    }

    /**
     * @return list<int>
     */
    public function getActiveWeekdayIDs(): array
    {
        $this->assertPrepared();

        return $this->activeWeekdayIDs;
    }

    public function hasActiveBlocks(): bool
    {
        $this->assertPrepared();

        return $this->blocks !== [];
    }

    public function hasActiveWeekdaySchedule(): bool
    {
        $this->assertPrepared();

        return $this->activeWeekdayIDs !== [];
    }

    public function getSelectedDate(): string
    {
        $this->assertPrepared();

        return $this->selectedDate ?? throw new LogicException('Date selection was prepared without a selected date.');
    }

    public function getPreviousDate(): ?string
    {
        $this->assertPrepared();

        return $this->previousDate;
    }

    public function getNextDate(): ?string
    {
        $this->assertPrepared();

        return $this->nextDate;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return list<int>
     */
    private function extractActiveWeekdayIDs(array $blocks): array
    {
        $activeWeekdayIDs = [];

        foreach ($blocks as $block) {
            if (!is_array($block) || !array_key_exists('gibbonDaysOfWeekID', $block)) {
                continue;
            }

            $weekdayID = $block['gibbonDaysOfWeekID'];
            if (is_int($weekdayID)) {
                $weekday = $weekdayID;
            } elseif (is_string($weekdayID) && preg_match('/^\s*\d+\s*$/D', $weekdayID) === 1) {
                $weekday = (int) trim($weekdayID);
            } else {
                continue;
            }

            if ($weekday < 1 || $weekday > 7 || in_array($weekday, $activeWeekdayIDs, true)) {
                continue;
            }

            $activeWeekdayIDs[] = $weekday;
        }

        return $activeWeekdayIDs;
    }

    private function assertPrepared(): void
    {
        if (!$this->prepared) {
            throw new LogicException('Date selection must be prepared before its state can be read.');
        }
    }

    public function createForm($url, bool $submitButton = true): Form
    {
        $form = Form::create('dateSelect', $url, 'get');
        $form->setClass('blank w-full');

        $form->addHiddenValue('q', $this->session->get('address'));

        $row = $form->addRow()->addClass('flex flex-wrap mb-4');
        // if (!empty($announcement)) {
            $row->addContent('<h3>'.Format::dateReadable($this->selectedDate, Format::FULL_NO_YEAR).'</h3>');
        // }

        $col = $row->addColumn()->addClass('flex items-center justify-end ');

        if ($this->previousDate) {
            $col->addAction('previous', __('Previous'))
                ->setURL($url->withQueryParam('date', $this->previousDate))
                ->addClass('px-3 py-2 text-xs font-medium text-gray-600')
                ->displayLabel(false);
        }

        $col->addDate('date')->setValue(Format::date($this->selectedDate))->setClass('w-48');

        if ($this->nextDate) {
            $col->addAction('next', __('Next'))
                ->setURL($url->withQueryParam('date', $this->nextDate))
                ->addClass('px-3 py-2 text-xs font-medium text-gray-600')
                ->displayLabel(false);
        }

        if ($submitButton) {
            $col->addSubmit(__('Go'));
        }

        return $form;
    }
}
