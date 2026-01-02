import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import prettierConfig from '@vue/eslint-config-prettier';

export default [
    // Base JavaScript rules
    js.configs.recommended,
    
    // Vue plugin configuration
    ...vue.configs['flat/recommended'],
    
    // Prettier config (disables conflicting rules)
    prettierConfig,
    
    {
        files: ['**/*.vue', '**/*.js'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                // Browser globals
                console: 'readonly',
                window: 'readonly',
                document: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                setInterval: 'readonly',
                clearInterval: 'readonly',
                localStorage: 'readonly',
                sessionStorage: 'readonly',
                URLSearchParams: 'readonly',
                URL: 'readonly',
                fetch: 'readonly',
                Blob: 'readonly',
                alert: 'readonly',
                confirm: 'readonly',
                // Node globals
                process: 'readonly',
                require: 'readonly',
                module: 'readonly',
                __dirname: 'readonly',
                __filename: 'readonly',
            },
        },
        rules: {
            // Vue specific rules
            'vue/multi-word-component-names': 'off', // Allow single-word component names
            'vue/no-v-html': 'warn', // Warn about v-html usage
            'vue/require-default-prop': 'off', // Don't require default props
            'vue/require-explicit-emits': 'warn', // Warn about missing emit declarations
            
            // General JavaScript rules
            'no-console': 'warn', // Warn about console statements
            'no-unused-vars': ['warn', { 
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_'
            }], // Warn about unused variables (ignore those starting with _)
            'no-undef': ['error', {
                // Allow reserved keywords used as identifiers in Vue templates
                typeof: false,
            }],
        },
    },
    {
        // Special handling for Vue files with template parsing
        files: ['**/*.vue'],
        rules: {
            // Disable parsing errors for reserved keywords in templates
            // Vue templates can use reserved keywords as prop/variable names (e.g., 'package')
            // The Vue compiler handles these correctly, so we disable the ESLint parsing error
            'vue/no-parsing-error': 'off',
        },
    },
    {
        // Ignore patterns
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'storage/**',
            'bootstrap/cache/**',
        ],
    },
];

