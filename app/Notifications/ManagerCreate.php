<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManagerCreate extends Notification implements ShouldQueue
{
    use Queueable;

    private User $user;
    private string $password;
    private string $loginUrl;
    private string $appName;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
        $this->loginUrl = $this->generateLoginUrl();
        $this->appName = config('app.name', 'Gym Management System');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this->buildWelcomeMessage();
    }

    /**
     * Build the welcome email message with improved structure and security.
     */
    private function buildWelcomeMessage(): MailMessage
    {
        $userName = $this->sanitizeUserName();
        $userEmail = $this->user->email ?? 'Not provided';

        return (new MailMessage)
            ->subject($this->getEmailSubject())
            ->greeting($this->getPersonalizedGreeting($userName))
            ->line($this->getWelcomeMessage())
            ->line($this->getCredentialsHeader())
            ->line($this->formatCredentialLine('Email', $userEmail))
            ->line($this->formatCredentialLine('Password', $this->password))
            ->line('')
            ->line($this->getSecurityNotice())
            ->action('Login Now', $this->loginUrl)
            ->line($this->getClosingMessage())
            ->salutation($this->getSalutation());
    }

    /**
     * Generate secure login URL.
     */
    private function generateLoginUrl(): string
    {
        return url('/staff-member/login');
    }

    /**
     * Sanitize and validate user name for display.
     */
    private function sanitizeUserName(): string
    {
        $name = $this->user->name ?? 'Team Member';
        return Str::limit(strip_tags($name), 50);
    }

    /**
     * Get dynamic email subject.
     */
    private function getEmailSubject(): string
    {
        return "Welcome to {$this->appName} - Manager Access Granted";
    }

    /**
     * Get personalized greeting.
     */
    private function getPersonalizedGreeting(string $userName): string
    {
        return "Hello {$userName}!";
    }

    /**
     * Get welcome message.
     */
    private function getWelcomeMessage(): string
    {
        return "Congratulations! You have been granted manager access to {$this->appName}. Below are your login credentials:";
    }

    /**
     * Get credentials header.
     */
    private function getCredentialsHeader(): string
    {
        return "**Your Login Credentials:**";
    }

    /**
     * Format credential lines consistently.
     */
    private function formatCredentialLine(string $label, string $value): string
    {
        return "• **{$label}:** {$value}";
    }

    /**
     * Mask password for security (show partial).
     */
    private function maskPassword(): string
    {
        $length = strlen($this->password);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($this->password, 0, 2) . str_repeat('*', $length - 4) . substr($this->password, -2);
    }

    /**
     * Get security notice.
     */
    private function getSecurityNotice(): string
    {
        return "🔒 **Important Security Notice:** Please log in immediately and change your password. For security reasons, this temporary password.";
    }

    /**
     * Get closing message.
     */
    private function getClosingMessage(): string
    {
        return "We're excited to have you on our team! If you have any questions or need assistance, please don't hesitate to contact our support team.";
    }

    /**
     * Get email salutation.
     */
    private function getSalutation(): string
    {
        return "Best regards,\nThe {$this->appName} Team";
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'role' => 'manager',
            'created_at' => now()->toISOString(),
        ];
    }
}
