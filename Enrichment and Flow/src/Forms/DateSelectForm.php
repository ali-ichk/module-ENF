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

/**
 * DateSelectForm
 */
class DateSelectForm
{
    protected Session $session;
    protected BlockGateway $blockGateway;
    protected WeekdayDateResolver $dateResolver;

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
     * @return array<int, array<string, mixed>>
     */
    public function getActiveBlocks(): array
    {
        return $this->blockGateway->selectBlocks()->fetchAll();
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return list<int>
     */
    public function extractActiveWeekdayIDs(array $blocks): array
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

    public function prepareDateSelection(
        ?array $request = null,
        ?DateTimeImmutable $today = null
    ): DateSelection {
        $blocks = $this->getActiveBlocks();
        $activeWeekdayIDs = $this->extractActiveWeekdayIDs($blocks);
        $today ??= new DateTimeImmutable('today');

        $requestDate = null;
        if ($request !== null && isset($request['date']) && is_string($request['date'])) {
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

        return new DateSelection(
            $blocks,
            $activeWeekdayIDs,
            $selectedDate->format('Y-m-d'),
            $previousDate,
            $nextDate
        );
    }

    public function createResolvedForm(Url $url, DateSelection $selection): Form
    {
        return $this->createForm(
            $url,
            $selection->getSelectedDate(),
            $selection->getPreviousDate(),
            $selection->getNextDate()
        );
    }

    public function createForm($url, $date, ?string $previousDate = null, ?string $nextDate = null)
    {
        $form = Form::create('dateSelect', $url, 'get');
        $form->setClass('blank w-full');

        $form->addHiddenValue('q', $this->session->get('address'));

        $row = $form->addRow()->addClass('flex flex-wrap mb-4');
        // if (!empty($announcement)) {
        //     $row->addContent('<h3>'.__m('Announcements').'</h3>');
        // }

        $col = $row->addColumn()->addClass('flex items-center justify-end gap-2');

        if ($previousDate !== null) {
            $col->addWebLink(__('Previous'))
                ->setURL($url->withQueryParam('date', $previousDate))
                ->addClass('px-3 py-2 text-xs font-medium text-gray-600');
        }

        $col->addDate('date')->setValue(Format::date($date))->setClass('w-48');

        if ($nextDate !== null) {
            $col->addWebLink(__('Next'))
                ->setURL($url->withQueryParam('date', $nextDate))
                ->addClass('px-3 py-2 text-xs font-medium text-gray-600');
        }

        $col->addSubmit(__('Go'));

        return $form;
    }
}
