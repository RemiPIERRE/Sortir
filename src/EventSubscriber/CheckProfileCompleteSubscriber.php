<?php

namespace App\EventSubscriber;

use App\Service\VerifInfoUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Force la complétion du profil.
 *
 * À chaque requête principale, redirige tout utilisateur au profil incomplet vers
 * la page de complétion de compte, sauf pour les routes techniques (préfixe « _ »)
 * et les routes de connexion / déconnexion / complétion elles-mêmes.
 */
class CheckProfileCompleteSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private VerifInfoUser   $verifInfoUser,
        private RouterInterface $router)
    {
    }

    /**
     * Redirige vers la complétion de compte si le profil courant est incomplet.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $route = $request->attributes->get('_route');

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

        if (!$this->verifInfoUser->profileIsComplete()) {
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_profile_create_account')
            ));
        }
    }

    /**
     * S'abonne à l'événement kernel.request.
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

}
