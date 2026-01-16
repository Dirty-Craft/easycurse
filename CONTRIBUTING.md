# Contributing

Thank you for your interest in contributing to EasyCurse! This guide outlines the conventions and requirements for contributing to the project.

## Before You Start

**Please read the [documentation](docs/README.md) before making any changes.** The documentation covers setup, architecture, testing, linting, and other important aspects of the project that you need to understand.

## Branch Naming

Use descriptive branch names that indicate the type of change:

- `feat/...` - For new features
- `fix/...` - For bug fixes
- `docs/...` - For documentation changes
- `refactor/...` - For code refactoring
- `test/...` - For test-related changes

Examples:
- `feat/user-authentication`
- `fix/mod-download-error`
- `docs/api-documentation`

## Commit Messages

Follow these commit message conventions:

- **Regular commits**: Use clear, descriptive messages
- **Feature commits**: `feat: add user authentication`
- **Fix commits**: `fix(module): resolve mod download error`
- **Breaking changes**: `feat!: change API response format`

The format `fix(module): description` allows you to specify the affected module or area of the codebase.

## Issues and Pull Requests

1. **Creating Issues**: Before starting work, check if an issue already exists. If not, create one to discuss the change.
2. **Pull Requests**: Reference the related issue in your PR description. Ensure all checks pass before requesting review.

## Development Requirements

### Docker Compatibility

This project is fully dockerized. All changes must be compatible with the Docker setup. Test your changes using:

```shell
$ docker compose up -d
$ docker compose exec app bash setup.sh
```

See the [Setup Guide](docs/setup.md) for more details.

### Linting

**Always run linters before committing code.** Use:

```shell
$ docker compose exec app composer lint
```

This runs Pint, ESLint, and Stylelint. See the [Linter documentation](docs/linter.md) for details.

### Testing

**All features must have tests.** Write feature tests for any new functionality. Run tests using:

```shell
$ docker compose exec app composer test
```

See the [Testing documentation](docs/testing.md) for more information.

## Documentation

Refer to the [documentation index](docs/README.md) for comprehensive guides on:

- Setup and configuration
- Architecture and design patterns
- Database schema
- API integrations (CurseForge, Modrinth)
- Console commands
- Runners system
- Localization
- And more

Make sure you understand the relevant documentation before making changes to those areas.
