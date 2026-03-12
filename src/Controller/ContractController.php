<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Activity\ActivityService;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Event\WorkContractDetailControllerEvent;
use App\Form\ContractByUserForm;
use App\Project\ProjectService;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use App\Reporting\YearByUser\YearByUser;
use App\Timesheet\TimesheetService;
use App\Utils\PageSetup;
use App\WorkingTime\Model\BoxConfiguration;
use App\WorkingTime\WorkingTimeService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Yasumi\Yasumi;
use Yasumi\Holiday;

/**
 * Users can control their working time statistics
 */
final class ContractController extends AbstractController
{
    #[Route(path: '/contract', name: 'user_contract', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, WorkingTimeService $workingTimeService, EventDispatcherInterface $eventDispatcher): Response
    {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory($currentUser);
        $canChangeUser = $this->isGranted('hours_other_profile');
        $defaultDate = $dateTimeFactory->createStartOfYear();
        $now = $dateTimeFactory->createDateTime();

        $values = new YearByUser();
        $values->setUser($currentUser);
        $values->setDate($defaultDate);

        $form = $this->createFormForGetRequest(ContractByUserForm::class, $values, [
            'include_user' => $canChangeUser,
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($currentUser);
        }

        /** @var User $profile */
        $profile = $values->getUser();
        if (!$this->isGranted('hours', $profile)) {
            throw $this->createAccessDeniedException('Cannot access user contract settings');
        }

        if ($values->getDate() === null) {
            $values->setDate(clone $defaultDate);
        }

        /** @var \DateTime $yearDate */
        $yearDate = $values->getDate();
        // make sure we use the correct datetime for the selected user
        $yearDate = $this->getDateTimeFactory($profile)->createStartOfYear($yearDate);
        $year = $workingTimeService->getYear($profile, $yearDate, $now);

        $page = new PageSetup('work_times');
        $page->setHelp('contract.html');
        $page->setActionName('contract');
        $page->setActionPayload(['profile' => $profile, 'year' => $yearDate]);
        $page->setPaginationForm($form);

        // additional boxes by plugins
        $controllerEvent = new WorkContractDetailControllerEvent($year);
        $eventDispatcher->dispatch($controllerEvent);

        $summary = $workingTimeService->getYearSummary($year, $now);

        $boxConfiguration = new BoxConfiguration();
        $boxConfiguration->setDecimal(false);
        $boxConfiguration->setCollapsed($summary->count() > 0);

        $hasConfiguration = $profile->hasWorkHourConfiguration();
        $days = [];
        if ($hasConfiguration) {
            $calculator = $workingTimeService->getContractMode($profile)->getCalculator($profile);
            $start = $dateTimeFactory->getStartOfWeek();
            $end = $dateTimeFactory->getEndOfWeek();
            while ($start < $end) {
                $tmp = clone $start;
                $days[] = [
                    'date' => $tmp,
                    'duration' => $calculator->isWorkDay($tmp) ? $calculator->getWorkHoursForDay($tmp) : null
                ];
                $start = $start->add(new \DateInterval('P1D'));
            }
        }

        return $this->render('contract/status.html.twig', [
            'days' => $days,
            'withWorkHourConfiguration' => $hasConfiguration,
            'box_configuration' => $boxConfiguration,
            'page_setup' => $page,
            'decimal' => $boxConfiguration->isDecimal(),
            'summaries' => $summary,
            'now' => $now,
            'boxes' => $controllerEvent->getController(),
            'year' => $year,
            'user' => $profile,
        ]);
    }

    #[Route(path: '/contract/register-holidays', name: 'contract_register_holidays', methods: ['GET'])]
    public function registerPublicHolidays(
        Request $request,
        WorkingTimeService $workingTimeService,
        TimesheetRepository $timesheetRepository,
        TimesheetService $timesheetService,
        ActivityService $activityService,
        ProjectService $projectService,
        UserRepository $userRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        if (!$this->isGranted('hours_other_profile')) {
            throw $this->createAccessDeniedException();
        }

        $token = new CsrfToken('register_holidays', $request->query->get('_token', ''));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $userId = $request->query->getInt('user');
        $user = $userRepository->getUserById($userId);
        if ($user === null) {
            throw $this->createNotFoundException('User not found');
        }

        $year = $request->query->getInt('year');
        if ($year < 2000 || $year > 2100) {
            throw $this->createNotFoundException('Invalid year');
        }

        $project = $projectService->findProjectByName('Time off', null);
        if ($project === null) {
            $this->addFlash('error', 'Project "Time off" not found');

            return $this->redirectToRoute('user_contract', ['user' => $userId]);
        }

        $activity = $activityService->findActivityByName('National holiday', $project);
        if ($activity === null) {
            $this->addFlash('error', 'Activity "National holiday" not found in project "Time off"');

            return $this->redirectToRoute('user_contract', ['user' => $userId]);
        }

        $calculator = $workingTimeService->getContractMode($user)->getCalculator($user);
        $holidays = Yasumi::create('Netherlands', $year);
        $userTimezone = new \DateTimeZone($user->getTimezone());

        $registered = 0;
        $skipped = 0;

        foreach ($holidays as $holiday) {
            if (!($holiday->getType() === Holiday::TYPE_OFFICIAL || $holiday->shortName === 'goodFriday')) {
                continue;
            }

            $dateStr = $holiday->format('Y-m-d');
            $holidayDate = new \DateTimeImmutable($dateStr, $userTimezone);

            $expectedSeconds = $calculator->getWorkHoursForDay($holidayDate);
            if ($expectedSeconds <= 0) {
                continue;
            }

            $count = (int) $timesheetRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :user')
                ->andWhere('t.activity = :activity')
                ->andWhere('t.date = :date')
                ->setParameter('user', $user->getId())
                ->setParameter('activity', $activity->getId())
                ->setParameter('date', $holidayDate, 'date_immutable')
                ->getQuery()
                ->getSingleScalarResult();

            if ($count > 0) {
                $skipped++;
                continue;
            }

            $begin = new \DateTime($dateStr . ' 09:00:00', $userTimezone);
            $end = (clone $begin)->add(new \DateInterval('PT' . $expectedSeconds . 'S'));

            $timesheet = new Timesheet();
            $timesheet->setUser($user);
            $timesheet->setProject($project);
            $timesheet->setActivity($activity);
            $timesheet->setBegin($begin);
            $timesheet->setEnd($end);

            $timesheetService->saveTimesheet($timesheet);
            $registered++;
        }

        if ($registered === 0 && $skipped === 0) {
            $this->addFlash('warning', sprintf('No public holidays found for year %d', $year));
        } else {
            $this->addFlash('success', sprintf('Registered %d public holiday(s), skipped %d already existing', $registered, $skipped));
        }

        return $this->redirectToRoute('user_contract', ['user' => $userId, 'date' => $year . '-01-01']);
    }
}
