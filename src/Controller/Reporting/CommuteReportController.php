<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Reporting;

use App\Controller\AbstractController;
use App\Reporting\CommuteByYear\CommuteByYear;
use App\Reporting\CommuteByYear\CommuteByYearForm;
use App\Repository\CommuteDayRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reporting/commute')]
#[IsGranted('report:user')]
final class CommuteReportController extends AbstractController
{
    #[Route(path: '/year', name: 'report_commute_year', methods: ['GET'])]
    public function commuteByYear(Request $request, CommuteDayRepository $repository): Response
    {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory($currentUser);
        $canChangeUser = $this->isGranted('view_other_timesheet') && $this->isGranted('view_other_reporting');

        $values = new CommuteByYear();
        $values->setUser($currentUser);
        $values->setDate($dateTimeFactory->createStartOfYear());

        $form = $this->createFormForGetRequest(CommuteByYearForm::class, $values, [
            'include_user' => $canChangeUser,
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($currentUser);
        }

        if ($currentUser !== $values->getUser() && !$canChangeUser) {
            throw new AccessDeniedException('User is not allowed to see other users data');
        }

        if ($values->getDate() === null) {
            $values->setDate($dateTimeFactory->createStartOfYear());
        }

        $selectedUser = $values->getUser();
        $year = (int) $values->getDate()->format('Y');

        $previous = clone $values->getDate();
        $previous->modify('-1 year');

        $next = clone $values->getDate();
        $next->modify('+1 year');

        $countsByMonth = $repository->countByYear($selectedUser, $year);

        $total = array_sum($countsByMonth);

        return $this->render('reporting/commute_by_year.html.twig', [
            'report_title' => 'report_commute_year',
            'box_id' => 'commute-year-reporting-box',
            'form' => $form->createView(),
            'user' => $selectedUser,
            'current' => $values->getDate(),
            'previous' => $previous,
            'next' => $next,
            'countsByMonth' => $countsByMonth,
            'total' => $total,
        ]);
    }
}
