# Localization

The application supports multiple languages through Laravel's translation system integrated with Inertia.js. Currently, **English** and **Persian (Farsi)** are supported.

## Supported Languages

- `en` - English (default)
- `fa` - Persian/Farsi

## Translation Files

Translation strings are stored in `src/lang/{locale}/messages.php`. Each locale has its own file containing all translatable strings organized by feature/section.

**Structure:**
```
src/lang/
├── en/
│   └── messages.php
└── fa/
    └── messages.php
```

Translation keys use dot notation for organization (e.g., `landing.hero.title`, `auth.login.email`).

## Language Detection & Persistence

The language preference is determined in the following order:

1. **Query Parameter**: If `?lang=en` or `?lang=fa` is present in the URL, that language is used
2. **Cookie**: If a `lang` cookie exists from a previous visit, that preference is used
3. **Default**: Falls back to English (`en`)

The language preference is saved in a cookie that expires after 1 year, ensuring the user's choice persists across sessions.

## Using Translations in Vue Components

Vue components use the `useTranslations` composable to access translations.

**Example:**
```vue
<template>
    <div>
        <h1>{{ t('landing.hero.title') }}</h1>
        <p>{{ t('landing.hero.subtitle') }}</p>
    </div>
</template>

<script setup>
import { useTranslations } from '../composables/useTranslations';

const { t } = useTranslations();
</script>
```

### Translation with Parameters

Some translations include placeholders that need to be replaced with dynamic values:

```vue
<template>
    <p>{{ t('modpack.mods_without_version', { 
        version: '1.20.1', 
        software: 'Forge',
        mods: 'Mod A, Mod B'
    }) }}</p>
</template>
```

In the translation file, placeholders use `:param` syntax:
```php
'modpack.mods_without_version' => 'The following mods do not have a version available for :version (:software): :mods',
```

## Using Translations in Laravel Controllers

In PHP controllers, use Laravel's `__()` helper function:

```php
use Illuminate\Support\Facades\Lang;

// Simple translation
$message = __('messages.auth.credentials_invalid');

// Translation with parameters
$message = __('messages.modpack.mods_without_version', [
    'version' => '1.20.1',
    'software' => 'Forge',
    'mods' => 'Mod A, Mod B'
]);
```

## Language Switcher

The `LanguageSwitcher` component is available in the navigation bar, allowing users to switch between supported languages. It:

- Displays the current language code (e.g., "EN", "FA")
- Shows a dropdown with all available languages
- Updates the URL with the `?lang=` parameter
- Persists the selection in a cookie

The component is already included in `AppLayout.vue` and appears in the navbar alongside the theme switcher.

## Locale-Specific Configuration

Each locale can define additional configuration beyond translations:

### Text Direction

Languages can specify their text direction (LTR or RTL):

```php
// src/lang/en/messages.php
'direction' => 'LTR',

// src/lang/fa/messages.php
'direction' => 'RTL',
```

### Font Configuration

Different fonts can be configured per locale:

```php
// English uses Poppins
'font.family' => "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",

// Persian uses Vazir
'font.family' => "'Vazir', 'Tahoma', 'Arial', sans-serif",
```

## How It Works

1. **Middleware**: `HandleInertiaRequests` middleware handles language detection and sets the application locale
2. **Shared Props**: All translations are shared with Inertia via the `translations` prop, making them available to all Vue components
3. **Locale Prop**: The current locale is shared as the `locale` prop for components that need to know the active language

## Adding a New Language

To add support for a new language:

1. **Create translation file**: Copy `src/lang/en/messages.php` to `src/lang/{locale}/messages.php`
2. **Translate all strings**: Replace all English strings with translations in the new language
3. **Update supported locales**: Add the new locale code to the `$supportedLocales` array in `HandleInertiaRequests.php`:
   ```php
   $supportedLocales = ['en', 'fa', 'de']; // Add 'de' for German
   ```
4. **Add to LanguageSwitcher**: Update the `languages` array in `LanguageSwitcher.vue`:
   ```javascript
   const languages = [
       { code: "en", name: "English" },
       { code: "fa", name: "فارسی" },
       { code: "de", name: "Deutsch" }, // Add new language
   ];
   ```
5. **Configure locale-specific settings**: Set `direction`, `font.family`, and `font.family_heading` in the new translation file

## Best Practices

- **Always use translation keys**: Never hardcode user-facing strings in components or controllers
- **Use descriptive keys**: Organize keys by feature (e.g., `landing.*`, `auth.*`, `modpack.*`)
- **Keep keys consistent**: Use the same key structure across all locale files
- **Test both languages**: Ensure all UI elements work correctly in both LTR and RTL layouts
- **Parameter naming**: Use clear, descriptive parameter names in translation strings

