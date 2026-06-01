# Draft: WhatsAppProviderInterface Refactor - Interface Segregation

## Problem Statement

The `WhatsAppProviderInterface` contains **25+ methods** forcing both providers (Meta and Twilio) to implement methods they don't support:

### Methods in MetaWhatsAppProvider that return "not supported" errors:
| Method | Error Returned |
|--------|----------------|
| `createSubAccount()` | "Meta WABA does not support sub-accounts" |
| `cleanupAccount()` | "Account cleanup not implemented for Meta WABA" |
| `getAvailableNumbers()` | "Phone number search not available for Meta WABA" |
| `purchaseNumber()` | "Phone numbers must be purchased through Meta Business Suite" |
| `verifyOwnNumber()` | "Phone verification must be completed through Meta Business Suite" |
| `resendOwnNumberOtp()` | "OTP resend not available through API" |
| `createWaba()` | "WABA creation must be done through Meta Business Manager" |
| `getMessageStatus()` | Only works with Twilio, Meta doesn't support direct lookup |
| `getMonthlyUsage()` | Uses `twilio_cost` field which doesn't apply to Meta |

### Methods in TwilioWhatsAppProvider that return "not supported" errors:
| Method | Error Returned |
|--------|----------------|
| `verifyWabaConnection()` | "WABA verification is only supported for Meta provider" |

## Interface Segregation Solution

Split the monolithic interface into focused, single-responsibility interfaces:

### Proposed Interface Structure

```
WhatsAppProviderInterface (base marker interface)
├── MessagingInterface (Core - both providers implement)
│   ├── sendMessage()
│   ├── sendTemplateMessage()
│   ├── sendTestMessage()
│   ├── sendBroadcast()
│   └── getMessageStatus() [optional - both may not support]
│
├── AccountManagementInterface (Twilio only)
│   ├── createSubAccount()
│   ├── getAccountStatus()
│   ├── cleanupAccount()
│   ├── getWabaMode()
│   └── requiresEmbeddedSignup()
│
├── PhoneNumberManagementInterface (Twilio only)
│   ├── getAvailableNumbers()
│   ├── purchaseNumber()
│   ├── registerOwnNumber()
│   ├── verifyOwnNumber()
│   └── resendOwnNumberOtp()
│
├── TemplateManagementInterface (Core - both providers implement)
│   ├── createTemplate()
│   ├── submitTemplate()
│   ├── syncTemplateStatus()
│   └── getTemplates()
│
├── CreditManagementInterface (Core - both providers implement)
│   ├── getCreditBalance()
│   └── getMonthlyUsage()
│
├── WabaSetupInterface (Meta-specific)
│   ├── createWaba()
│   ├── connectIndividualWaba()
│   ├── connectManualWaba()
│   └── verifyWabaConnection()
│
├── EmbeddedSignupInterface (Meta-specific)
│   ├── startEmbeddedSignup()
│   ├── getEmbeddedSignupLoginUrl()
│   └── handleEmbeddedSignupCallback()
│
└── TestMessagingInterface (Core - both providers implement)
    └── sendTestWhatsAppMessage()
```

## Benefits

1. **Cleaner Implementations**: No placeholder error methods
2. **Better Type Safety**: IDE can enforce what's actually supported
3. **Easier Testing**: Mock only the interfaces you need
4. **Documentation**: Interface names clearly show capability scope
5. **Single Responsibility**: Each interface has one reason to change

## Technical Decisions Needed

1. **Backward Compatibility**: Keep base `WhatsAppProviderInterface` as marker for factory compatibility?
2. **Provider Type Declaration**: How to declare which interfaces a provider implements?
3. **Optional Methods**: Some methods may be optional (e.g., `getMessageStatus()`)
4. **Null Object Pattern**: For optional interface methods?

## Files to Modify/Create

### New Interface Files:
- `app/Interfaces/MessagingInterface.php`
- `app/Interfaces/AccountManagementInterface.php`
- `app/Interfaces/PhoneNumberManagementInterface.php`
- `app/Interfaces/TemplateManagementInterface.php`
- `app/Interfaces/CreditManagementInterface.php`
- `app/Interfaces/WabaSetupInterface.php`
- `app/Interfaces/EmbeddedSignupInterface.php`
- `app/Interfaces/TestMessagingInterface.php`

### Modified Files:
- `app/Interfaces/WhatsAppProviderInterface.php` (mark as composed interface)
- `app/Providers/TwilioWhatsAppProvider.php` (implement specific interfaces)
- `app/Providers/MetaWhatsAppProvider.php` (implement specific interfaces)
- `app/Providers/WhatsAppProviderFactory.php` (return type consideration)
- `app/Filament/Admin/Pages/WhatsAppSetup.php` (type hints)

## Open Questions

1. Should we keep `WhatsAppProviderInterface` as a composed interface for backward compatibility?
2. How to handle methods that exist but return errors - remove or keep as optional?
3. Should providers declare which interfaces they implement in docblocks?

## Scope Boundaries

### IN:
- Create new focused interfaces
- Update both provider implementations
- Update factory return type
- Update type hints in consuming code

### OUT:
- Don't modify business logic in providers (keep existing implementations)
- Don't change method signatures (only refactor interface structure)
- Don't add new functionality

## Test Strategy

- No automated tests required for interface refactor
- Manual verification that providers still work
- Verify factory still creates correct providers