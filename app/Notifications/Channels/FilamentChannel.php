<?php

namespace App\Notifications\Channels;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

class FilamentChannel
{
    public function send($notifiable, $notification)
    {
        if (! method_exists($notification, 'toFilament')) {
            return;
        }

        $filamentData = $notification->toFilament($notifiable);

        $filamentNotification = Notification::make()
            ->title($filamentData['title'] ?? 'Notification')
            ->body($filamentData['body'] ?? '');

        // Add icon if provided
        if (isset($filamentData['icon'])) {
            $filamentNotification->icon($filamentData['icon']);
        }

        // Add color if provided
        if (isset($filamentData['color'])) {
            $colorMethod = $filamentData['color'];
            if (method_exists($filamentNotification, $colorMethod)) {
                $filamentNotification->$colorMethod();
            }
        }

        // Add actions if provided
        if (isset($filamentData['actions']) && is_array($filamentData['actions'])) {
            $actions = [];
            foreach ($filamentData['actions'] as $actionData) {
                $action = Action::make($actionData['name'] ?? 'action')
                    ->label($actionData['label'] ?? 'Action');

                if (isset($actionData['url'])) {
                    $action->url($actionData['url']);
                }

                if (isset($actionData['button']) && $actionData['button']) {
                    $action->button();
                }

                $actions[] = $action;
            }
            $filamentNotification->actions($actions);
        }

        $filamentNotification->sendToDatabase($notifiable);
    }
}
