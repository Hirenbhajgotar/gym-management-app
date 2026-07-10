<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Toast;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToastTest extends TestCase
{
    #[Test]
    public function it_renders_a_session_error_message_on_mount(): void
    {
        session()->flash('error', 'Something went wrong');

        /** @var Testable $toast */
        $toast = Livewire::test(Toast::class);
        $toast->assertSee('Something went wrong');
        $toast->assertSet('showError', true);
    }

    #[Test]
    public function it_renders_a_session_success_message_on_mount(): void
    {
        session()->flash('success', 'Saved successfully');

        /** @var Testable $toast */
        $toast = Livewire::test(Toast::class);
        $toast->assertSee('Saved successfully');
        $toast->assertSet('showSuccess', true);
    }

    #[Test]
    public function show_toast_event_renders_the_error_message(): void
    {
        /** @var Testable $toast */
        $toast = Livewire::test(Toast::class);
        $toast->dispatch('show-toast', 'error', 'Validation failed');
        $toast->assertSee('Validation failed');
        $toast->assertSet('error', 'Validation failed');
        $toast->assertSet('showError', true);
    }

    #[Test]
    public function show_toast_event_renders_the_success_message(): void
    {
        /** @var Testable $toast */
        $toast = Livewire::test(Toast::class);
        $toast->dispatch('show-toast', 'success', 'All good');
        $toast->assertSee('All good');
        $toast->assertSet('success', 'All good');
        $toast->assertSet('showSuccess', true);
    }

    #[Test]
    public function it_coerces_a_non_string_message_to_an_empty_string_without_crashing(): void
    {
        // Reproduces the production crash: htmlspecialchars() given an array.
        /** @var Testable $toast */
        $toast = Livewire::test(Toast::class);
        $toast->dispatch('show-toast', 'error', ['unexpected', 'array', 'payload']);
        $toast->assertSet('error', '');
        $toast->assertOk();
    }

    #[Test]
    public function error_and_success_properties_are_locked_against_client_updates(): void
    {
        // The original attack sent `updates.error` as a nested array. Because the
        // properties are #[Locked], a client-supplied update must not inject a value.
        /** @var Testable $toast */
        $toast = Livewire::test(Toast::class);
        $toast->dispatch('show-toast', 'error', 'legit');
        $toast->assertSet('error', 'legit');

        // A malicious direct property update is rejected with an exception,
        // so the value can never reach the rendered output.
        try {
            /** @var Testable $malicious */
            $malicious = Livewire::test(Toast::class);
            $malicious->set('error', ['malicious' => 'payload']);
            $this->fail('Expected CannotUpdateLockedPropertyException was not thrown.');
        } catch (CannotUpdateLockedPropertyException $e) {
            $this->assertStringContainsString('error', $e->getMessage());
        }
    }
}
