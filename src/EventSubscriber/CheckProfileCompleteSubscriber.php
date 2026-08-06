<?php

namespace App\EventSubscriber;

use App\Service\VerifInfoUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

// Permet de rediriger à partir de CHAQUE URL du site vers la page create_account SI le profil n'est pas complété (grâce à verifInfoUser).
// Si profil complet -> redirection vers la page d'accueil
// Intérêt du kernel : se déclenche automatiquement, à chaque requête, avant les controllers
// centralise le code en un seul endroit, pas de duplication dans chaque controller

class CheckProfileCompleteSubscriber implements EventSubscriberInterface
{

public function __construct(
    private VerifInfoUser $verifInfoUser,
    private RouterInterface $router){}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');

        if ($route === null || str_starts_with($route, '_') || in_array($route, [
                'app_profile_create_account',
                'app_login',
                'app_logout',
                'app_forgot_password_request',
                'app_check_email',
                'app_reset_password',
            ], true)) {
            return;
        }


        $isComplete = $this->verifInfoUser->profileIsComplete();


        if (!$isComplete) {
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_profile_create_account')
            ));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

}
