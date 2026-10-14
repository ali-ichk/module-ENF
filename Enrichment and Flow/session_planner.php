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

use Gibbon\Http\Url;
use Gibbon\Services\Format;
use Gibbon\Forms\MultiPartForm;
use Gibbon\Module\EnrichmentandFlow\Domain\BlockFacilityGateway;
use Gibbon\Module\EnrichmentandFlow\Domain\BlockGateway;
use Gibbon\Module\EnrichmentandFlow\Domain\PlannedSessionGateway;
use Gibbon\Module\EnrichmentandFlow\Domain\PlannedSessionTeacherGateway;
use Gibbon\Module\EnrichmentandFlow\Domain\SessionStudentGateway;
use Gibbon\Module\EnrichmentandFlow\ENFFormat;
use Gibbon\Module\EnrichmentandFlow\Forms\DateSelectForm;
use Gibbon\Module\EnrichmentandFlow\Services\WeekdayDateResolver;


if (isActionAccessible($guid, $connection2, '/modules/Enrichment and Flow/session_planner.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('Session Planner'));

    $blockGateway = $container->get(BlockGateway::class);
    $blocks = $blockGateway->selectBlocks()->fetchAll();
    $dateResolver = $container->get(WeekdayDateResolver::class);
    $manualDateValue = isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : null;
    $manualDate = $dateResolver->parseManualDate($manualDateValue);
    $url = Url::fromModuleRoute('Enrichment and Flow', 'sessions_my');
    $dateSelectForm = $container->get(DateSelectForm::class);

    if (empty($blocks)) {
        $date = $manualDate?->format('Y-m-d') ?? date('Y-m-d');
        $page->write($dateSelectForm->createForm($url, $date)->getOutput());
        $page->write($page->getBlankSlate(__m('There are no ENF sessions running at this time.')));
        return;
    }

    $activeWeekdayIDs = [];
    foreach ($blocks as $block) {
        $weekdayID = $block['gibbonDaysOfWeekID'] ?? null;
        if (is_int($weekdayID)) {
            $weekdayID = (string) $weekdayID;
        }

        if (is_string($weekdayID) && preg_match('/^\s*\d+\s*$/D', $weekdayID) === 1) {
            $weekdayID = (int) trim($weekdayID);
            if ($weekdayID >= 1 && $weekdayID <= 7) {
                $activeWeekdayIDs[$weekdayID] = true;
            }
        }
    }
    $activeWeekdayIDs = array_keys($activeWeekdayIDs);

    $selectedDate = $manualDate ?? $dateResolver->resolveInitialDate(new DateTimeImmutable('today'), $activeWeekdayIDs);
    if ($selectedDate === null) {
        $page->write($page->getBlankSlate(__m('There are no ENF sessions running at this time.')));
        return;
    }

    $date = $selectedDate->format('Y-m-d');
    $previousDate = $dateResolver->resolvePreviousDate($selectedDate, $activeWeekdayIDs);
    $nextDate = $dateResolver->resolveNextDate($selectedDate, $activeWeekdayIDs);
    $page->write($dateSelectForm->createForm(
        $url,
        $date,
        $previousDate?->format('Y-m-d'),
        $nextDate?->format('Y-m-d')
    )->getOutput());

    $blockFacilityGateway = $container->get(BlockFacilityGateway::class);
    $sessionStudentGateway = $container->get(SessionStudentGateway::class);
    $plannedSessionGateway = $container->get(PlannedSessionGateway::class);
    $plannedSessionTeacherGateway = $container->get(PlannedSessionTeacherGateway::class);

    // FORM
    $form = MultiPartForm::create('sessionPlanner', $session->get('absoluteURL').'/modules/Enrichment and Flow/session_plannerProcess.php');
    $form->setClass('blank w-full');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('date', $date);

    $checks = ['absent' => 0, 'unstaffed' => 0];

    foreach ($blocks as $blockID => $block) {
        $capacity = ['used' => 0, 'available' => 0];

        $teachers = $plannedSessionTeacherGateway->selectAvailableTeachersByBlock($block['enfBlockID'], $date)->fetchAll();
        $cover = $plannedSessionTeacherGateway->selectCoverTeachersByBlock($block['enfBlockID'], $date)->fetchAll();
        $facilities = $blockFacilityGateway->selectUsedAndAvailableFacilitiesByBlock($block['enfBlockID'])->fetchAll();
        
        $sessions = [];
        $sessionList = $plannedSessionGateway->selectPlannedSessionsByDate($block['enfBlockID'], $date, true)->fetchAll();

        foreach ($sessionList as $values) {
            $values['css'] = ENFFormat::$sessionTypes[$values['type']] ?? '';
            $values['teachers'] = $plannedSessionTeacherGateway->selectTeachersByPlannedSession($values['enfPlannedSessionID'], $date)->fetchAll();
            $values['students'] = $sessionStudentGateway->selectStudentsByPlannedSessionAndDate($values['enfPlannedSessionID'], $date)->fetchAll();
            $values['studentCount'] = count($values['students']);

            $values['actions'] = [
                // 'add' => $form->getFactory()->createAction('add', __('Add'))
                //     ->setURL('/modules/Enrichment and Flow/sessions_view_addEditStudent.php')
                //     ->addParams(['enfBlockID' => $block['enfBlockID'], 'date' => $date, 'mode' => 'manage', 'enfPlannedSessionID' => $values['enfPlannedSessionID']])
                //     ->setType('interface')
                //     ->setIcon('user-plus')
                //     ->getOutput(),

                'edit' => $form->getFactory()->createAction('edit', __('Edit'))
                    ->setURL('/modules/Enrichment and Flow/sessions_my_addEdit.php')
                    ->addParams(['enfBlockID' => $block['enfBlockID'], 'date' => $date, 'mode' => 'manage', 'enfPlannedSessionID' => $values['enfPlannedSessionID']])
                    ->setType('interface')
                    ->getOutput(),

                'delete' => $form->getFactory()->createAction('delete', __('Delete'))
                    ->setURL('/modules/Enrichment and Flow/sessions_my_delete.php')
                    ->addParams(['enfBlockID' => $block['enfBlockID'], 'date' => $date, 'mode' => 'manage', 'enfPlannedSessionID' => $values['enfPlannedSessionID']])
                    ->setType('interface')
                    ->getOutput(),
        
            ];

            $sessions[intval($values['gibbonSpaceID'])][] = $values;

            // Check for absent teachers and unstaffed sessions
            foreach ($values['teachers'] as $item) {
                if (!empty($item['absenceAllDay']) && ($item['absenceAllDay'] == 'Y' || (
                    ($values['timeStart'] >= $item['absenceStart'] && $values['timeStart'] < $item['absenceEnd']) ||
                    ($item['absenceStart'] >= $values['timeStart'] && $item['absenceStart'] < $values['timeEnd'])
                ))) {
                    $checks['absent']++;
                }
            }
            if ( count($values['teachers']) == 0 ) {
                $checks['unstaffed']++;
            }

            $capacity['available'] += $values['maxStudents'];
            $capacity['used'] += $values['studentCount'];
        }

        $addButton = $form->getFactory()->createAction('add', __m('Add a Session'))
            ->setURL('/modules/Enrichment and Flow/sessions_my_addEdit.php')
            ->addParams(['enfBlockID' => $block['enfBlockID'], 'date' => $date, 'mode' => 'manage'])
            ->getOutput();

        

        // Display the drag-drop group editor
        $blocks[$blockID]['blockData'] = [
            'block'      => $block,
            'capacity'   => $capacity,
            'facilities' => $facilities,
            'sessions'   => $sessions,
            'teachers'   => array_merge($teachers, $cover),
            'addButton'  => $addButton,
        ];
    }

    if ($checks['absent'] > 0) {
        echo Format::alert(__n('Warning: {count} teacher is absent today.', 'Warning, {count} teachers are absent today.', $checks['absent']), 'error');
    }

    if ($checks['unstaffed'] > 0) {
        echo Format::alert(__n('Warning: {count} session does not have a teacher assigned.', 'Warning, {count} sessions do not have teachers assigned.', $checks['unstaffed']), 'error');
    }
    
    foreach ($blocks as $blockID => $block) {
        $form->addRow()->addContent($page->fetchFromTemplate('sessionPlanner.twig.html', $blocks[$blockID]['blockData']));
    }

    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
    $row = $table->addRow()->addSubmit(__('Submit'));
    
    echo $form->getOutput();
}
