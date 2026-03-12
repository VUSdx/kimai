<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber\Actions;

use App\Entity\User;
use App\Event\PageActionsEvent;
use App\WorkingTime\Model\Year;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ContractLinksSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'contract_links';
    }

    public function onActions(PageActionsEvent $event): void
    {
        if (!$this->isGranted('hours_other_profile')) {
            return;
        }

        $payload = $event->getPayload();
        $user = $payload['user'] ?? null;
        $year = $payload['year'] ?? null;

        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        if (!$year instanceof Year) {
            return;
        }

        $event->addAction('register_public_holidays', [
            'url' => $this->path('contract_register_holidays', [
                'user' => $user->getId(),
                'year' => $year->getYear()->format('Y'),
                '_token' => $this->csrfTokenManager->getToken('register_holidays')->getValue(),
            ]),
            'class' => 'confirmation-link',
            'attr' => ['data-question' => 'confirm.register_public_holidays'],
            'title' => 'register_public_holidays',
            'icon' => 'calendar'
        ]);
    }
}
