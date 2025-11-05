# Request Demo - Toast Integration Documentation

## ✅ Overview

The Request Demo feature now uses the **Toast component** for displaying success and error messages. The Toast component supports both **session flash messages** (on page load/refresh) and **Livewire event dispatch** (for real-time notifications without page refresh).

## 🎯 How It Works

### Dual Mode Support

The Toast component works in two ways:

1. **Session Flash Mode**: Displays messages from `session('success')` or `session('error')` when the page loads
2. **Event Dispatch Mode**: Listens for Livewire events and displays messages in real-time without page refresh

## 🔧 Toast Component Implementation

### Toast.php - Updated Component

```php
<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Toast extends Component
{
    public $error;
    public $success;
    public $showError = false;
    public $showSuccess = false;

    public function mount()
    {
        // Check for session flash messages on mount
        $this->error = session('error');
        $this->success = session('success');

        if ($this->error) {
            $this->showError = true;
        }

        if ($this->success) {
            $this->showSuccess = true;
        }
    }

    #[On('show-toast')]
    public function showToast($type, $message)
    {
        if ($type === 'success') {
            $this->success = $message;
            $this->showSuccess = true;
        } elseif ($type === 'error') {
            $this->error = $message;
            $this->showError = true;
        }
    }

    #[On('show-success')]
    public function showSuccess($message)
    {
        $this->success = $message;
        $this->showSuccess = true;
    }

    #[On('show-error')]
    public function showError($message)
    {
        $this->error = $message;
        $this->showError = true;
    }

    public function render()
    {
        return view('livewire.toast');
    }
}
```

### Key Features

- ✅ Listens for 3 events: `show-toast`, `show-success`, `show-error`
- ✅ Checks session flash messages on mount
- ✅ Auto-dismisses after 3 seconds
- ✅ Manual dismiss with close button
- ✅ Smooth slide-in/slide-out animations
- ✅ Fixed position (top-right corner)
- ✅ Beautiful glassmorphism design

## 📡 Dispatching Toast Events

### From RequestDemo Component

```php
// Success message
$this->dispatch(
    'show-success',
    message: 'Request submitted successfully! Thank you for your interest.'
);

// Error message
$this->dispatch(
    'show-error',
    message: 'Too many requests. Please try again later.'
);

// Generic toast with type
$this->dispatch(
    'show-toast',
    type: 'success',
    message: 'Action completed!'
);
```

### Event Options

#### 1. `show-success` Event
```php
$this->dispatch('show-success', message: 'Your success message here');
```

#### 2. `show-error` Event
```php
$this->dispatch('show-error', message: 'Your error message here');
```

#### 3. `show-toast` Event (Generic)
```php
$this->dispatch('show-toast', type: 'success', message: 'Your message');
$this->dispatch('show-toast', type: 'error', message: 'Your message');
```

## 🎨 Toast View Design

### Location
`resources/views/livewire/toast.blade.php`

### Features
- Fixed position: `top-24 right-6`
- Z-index: `z-50` (appears above everything)
- Auto-dismiss: 3 seconds
- Manual dismiss: Close button
- Animations: Smooth slide from right
- Alpine.js powered
- Livewire reactive

### Styling
- **Success Toast**: Green (`bg-green-500/20`, `border-green-500/30`, `text-green-400`)
- **Error Toast**: Red (`bg-red-500/20`, `border-red-500/30`, `text-red-400`)
- **Backdrop**: Blur effect (`backdrop-blur-sm`)
- **Max Width**: `max-w-sm`

## 📦 Integration in Views

### Add Toast Component

Simply add this line at the top of any Livewire view:

```blade
@livewire('toast')
```

### Example in request-demo.blade.php

```blade
<div>
    <section class="min-h-screen py-16 relative overflow-hidden">
        <div class="container mx-auto px-6 relative z-10">
            <!-- Toast Messages -->
            @livewire('toast')

            <!-- Rest of your content -->
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Your content here -->
            </div>
        </div>
    </section>
</div>
```

## 🔄 Migration from Session Flash

### Before (Session Flash)
```php
session()->flash('success', 'Action completed!');
session()->flash('error', 'Something went wrong!');
```

### After (Toast Events)
```php
$this->dispatch('show-success', message: 'Action completed!');
$this->dispatch('show-error', message: 'Something went wrong!');
```

### Why Toast Events Are Better

| Feature | Session Flash | Toast Events |
|---------|--------------|--------------|
| **Page Refresh Required** | Yes | No |
| **Real-time Updates** | No | Yes |
| **User Experience** | Requires reload | Instant feedback |
| **Position** | Inline | Fixed overlay |
| **Animation** | Basic | Smooth slide |
| **Auto-dismiss** | Manual only | Automatic |

## 🧪 Testing Toast Events

### Test Success Event Dispatch

```php
/** @test */
public function it_dispatches_success_event_after_submission(): void
{
    Livewire::test(RequestDemo::class)
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('phone', '+1234567890')
        ->call('submitRequest')
        ->assertDispatched('show-success');
}
```

### Test Error Event Dispatch

```php
/** @test */
public function it_dispatches_error_event_on_rate_limit(): void
{
    $component = Livewire::test(RequestDemo::class);

    // Submit 3 requests
    for ($i = 0; $i < 3; $i++) {
        $component
            ->set('name', "John Doe {$i}")
            ->set('email', "john{$i}@example.com")
            ->set('phone', "+123456789{$i}")
            ->call('submitRequest');
    }

    // 4th request should dispatch error event
    $component
        ->set('name', 'John Doe 4')
        ->set('email', 'john4@example.com')
        ->set('phone', '+1234567894')
        ->call('submitRequest')
        ->assertDispatched('show-error');
}
```

## 🎯 Use Cases

### 1. Form Submission Success
```php
public function submitRequest(): void
{
    try {
        // Process form...
        
        $this->dispatch(
            'show-success',
            message: 'Request submitted successfully!'
        );
    } catch (\Exception $e) {
        $this->dispatch(
            'show-error',
            message: 'Something went wrong. Please try again.'
        );
    }
}
```

### 2. Rate Limiting
```php
if (RateLimiter::tooManyAttempts($key, 3)) {
    $minutes = ceil(RateLimiter::availableIn($key) / 60);
    
    $this->dispatch(
        'show-error',
        message: "Too many requests. Please try again in {$minutes} minutes."
    );
    
    return;
}
```

### 3. Validation Errors
```php
catch (\Illuminate\Validation\ValidationException $e) {
    $this->dispatch(
        'show-error',
        message: 'Please check the form and correct the errors.'
    );
}
```

### 4. General Errors
```php
catch (\Exception $e) {
    Log::error('Error occurred', ['exception' => $e]);
    
    $this->dispatch(
        'show-error',
        message: 'Something went wrong. Please try again later.'
    );
}
```

## 🔧 Customization

### Change Toast Position

Edit `resources/views/livewire/toast.blade.php`:

```blade
<!-- Top-right (default) -->
class="fixed top-24 right-6 z-50"

<!-- Top-left -->
class="fixed top-24 left-6 z-50"

<!-- Bottom-right -->
class="fixed bottom-6 right-6 z-50"

<!-- Bottom-left -->
class="fixed bottom-6 left-6 z-50"

<!-- Top-center -->
class="fixed top-24 left-1/2 transform -translate-x-1/2 z-50"
```

### Change Auto-dismiss Duration

Edit the Alpine.js init in `toast.blade.php`:

```javascript
init() {
    if (this.showError) {
        setTimeout(() => this.showError = false, 5000); // 5 seconds
    }
    if (this.showSuccess) {
        setTimeout(() => this.showSuccess = false, 5000); // 5 seconds
    }
}
```

### Change Toast Colors

```blade
<!-- Success - make it blue instead of green -->
class="... bg-blue-500/20 border-blue-500/30 text-blue-400"

<!-- Error - make it orange instead of red -->
class="... bg-orange-500/20 border-orange-500/30 text-orange-400"
```

## 📊 Benefits

### 1. **Better User Experience**
- Instant feedback without page refresh
- Non-intrusive notifications
- Auto-dismiss reduces clutter

### 2. **Developer Friendly**
- Simple API: `$this->dispatch('show-success', message: '...')`
- Consistent across all components
- Easy to test with `assertDispatched()`

### 3. **Flexible**
- Works with session flash (page refresh)
- Works with events (real-time)
- Both modes work simultaneously

### 4. **Beautiful Design**
- Matches application theme
- Smooth animations
- Professional appearance

## 🚀 Implementation Checklist

- [x] Updated Toast component with event listeners
- [x] Added `#[On('show-success')]` method
- [x] Added `#[On('show-error')]` method
- [x] Added `#[On('show-toast')]` method
- [x] Updated RequestDemo to dispatch events
- [x] Removed duplicate flash message display
- [x] Updated tests to check for dispatched events
- [x] Formatted code with Laravel Pint
- [x] All tests passing

## 📝 Files Modified

1. ✅ `app/Livewire/Toast.php` - Added event listeners
2. ✅ `app/Livewire/Frontend/RequestDemo.php` - Dispatch events instead of session flash
3. ✅ `resources/views/livewire/frontend/request-demo.blade.php` - Removed duplicate flash messages
4. ✅ `tests/Feature/RequestDemoTest.php` - Updated to test event dispatch

## 🎓 Best Practices

### DO ✅
- Use `show-success` for successful operations
- Use `show-error` for errors and validation issues
- Keep messages concise and actionable
- Use the Toast component in all frontend views
- Test event dispatch in feature tests

### DON'T ❌
- Don't mix session flash and events in the same action
- Don't create duplicate toast components
- Don't forget to add `@livewire('toast')` in views
- Don't use inline flash message divs when Toast is available

## 🔍 Troubleshooting

### Toast not appearing?
1. Check `@livewire('toast')` is in the view
2. Verify event name is correct: `show-success` or `show-error`
3. Check browser console for JavaScript errors
4. Ensure Alpine.js is loaded

### Toast appears but doesn't auto-dismiss?
1. Check Alpine.js is loaded
2. Verify `x-data` is on the toast container
3. Check setTimeout duration in `init()`

### Multiple toasts showing?
1. Only one Toast component should be in the view
2. Each page should have one `@livewire('toast')` call

## 📚 Related Documentation

- [Livewire Events Documentation](https://livewire.laravel.com/docs/events)
- [Alpine.js Documentation](https://alpinejs.dev/)
- [REQUEST_DEMO_FEATURE.md](./REQUEST_DEMO_FEATURE.md)
- [REQUEST_DEMO_SETUP.md](./REQUEST_DEMO_SETUP.md)

---

**Created**: November 4, 2025  
**Last Updated**: November 4, 2025  
**Version**: 1.0.0  
**Status**: ✅ Complete and Production Ready