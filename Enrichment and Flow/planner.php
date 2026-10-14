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

use Gibbon\Forms\Form;
use Gibbon\Http\Url;
use Gibbon\Module\EnrichmentandFlow\Domain\AnnouncementGateway;
use Gibbon\Module\EnrichmentandFlow\Domain\DailyPlannerGateway;
use Gibbon\Module\EnrichmentandFlow\Forms\DateSelectForm;
use Gibbon\Module\EnrichmentandFlow\View\StaffPlannerView;
use Gibbon\Module\EnrichmentandFlow\View\StudentPlannerView;
use Gibbon\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Enrichment and Flow/planner.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/Enrichment and Flow/planner.php', $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $gibbonCourseClassID = $_REQUEST['gibbonCourseClassID'] ?? null;
    $dailyPlannerGateway = $container->get(DailyPlannerGateway::class);

    // Date selector form
    $url = Url::fromModuleRoute('Enrichment and Flow', 'planner');
    $dateSelectForm = $container->get(DateSelectForm::class)->setDate($_GET);
    $form = $dateSelectForm->createForm($url, false);
    $date = $dateSelectForm->getSelectedDate();

    $col = $form->getRow()->addColumn()->setClass('flex-shrink flex items-center self-center');

    // Display class selector
    if ($highestAction == 'Planner Overview') {
        $class = $dailyPlannerGateway->getENFClassByPerson($session->get('gibbonSchoolYearID'), $session->get('gibbonPersonID'));
        $classes = $dailyPlannerGateway->selectAllENFClasses($session->get('gibbonSchoolYearID'))->fetchKeyPair();
        
        $col->addSelect('gibbonCourseClassID')->fromArray($classes)->setClass('w-48 mr-1')->selected($gibbonCourseClassID ?? $class['gibbonCourseClassID'] ?? '')->placeholder('[ '.__m('Select a Class').' ]');

        if (empty($gibbonCourseClassID)) {
            $gibbonCourseClassID = $class['gibbonCourseClassID'] ?? '';
        }
    }

    $col->addSubmit(__('Go'));

    $announcement = $container->get(AnnouncementGateway::class)->getAnnouncementByDate($date);
    if (!empty($announcement)) {
        $row = $form->addRow()->addClass('flex flex-wrap mb-4');
        $row->addContent('<h3>'.__m('Announcements').'</h3>');
    }
    

    $page->write($form->getOutput());

    // Announcements
    if (!empty($announcement)) {
        $announcement['content'] = trim(preg_replace('/^<p>|<\/p>$/i', '', $announcement['content']));
        $page->write('<div class="relative bg-gray-100 rounded text-gray-800 text-sm leading-snug border p-4 font-sans">'.$announcement['content']. '</div>');
    }

    // Display the ENF dashboards
    if ($highestAction == 'Planner Overview') {
        $page->breadcrumbs->add(__m('Planner Overview').' ('.Format::date($date).')');
        $planner = $container->get(StaffPlannerView::class)->setDate($date)->setClass($gibbonCourseClassID)->compose($page);

    } elseif ($highestAction == 'Plan & Log') {
        $page->breadcrumbs->add(__m('Plan & Log').' ('.Format::date($date).')');
        $planner = $container->get(StudentPlannerView::class)->setDate($date)->compose($page);
    }
}
