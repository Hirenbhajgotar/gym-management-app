<?php

namespace App\Filament\Admin\WhatsAppSetup\Actions;

use Filament\Notifications\Notification;

class StartEmbeddedSignupAction
{
    public function __construct(public $page) {}

    public function __invoke(): void
    {
        $gym = $this->page->getGym();
        if (! $gym) {
            Notification::make()->title('Something went wrong. Please try again')->danger()->send();

            return;
        }
        $this->page->redirect(route('whatsapp.embedded-signup.redirect', ['tenant' => $gym->slug]));
    }
}
