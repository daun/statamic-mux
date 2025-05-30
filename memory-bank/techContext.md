# Technical Context

## Technology Stack

### Core Dependencies

- **PHP 8.1+**: Base language requirement
- **Laravel 9+**: Framework foundation
- **Statamic 4+**: CMS platform
- **Mux PHP SDK**: Official API client for Mux services

### Control Panel Components

- **Vue.js**: Used for Control Panel components
- **Tailwind CSS**: Utility-first CSS framework

### Infrastructure Requirements

- **Queue System**: Laravel queues for async processing
- **Webhook Receiver**: For long-running Mux processing

## Core Technical Patterns

### Dependency Management

Dependencies are managed via Composer in `composer.json`.

### Configuration

The addon uses Laravel's configuration system with `config/mux.php` defining settings.

### API Integration

The `MuxApi` class wraps the Mux PHP SDK to provide a consistent interface.

### Async Processing

Some Mux operations are performed asynchronously through Laravel's queue and event systems.

## Development Tools

### Testing

- PHPUnit/Pest for PHP testing
- Feature tests with Statamic TestCase extensions

### Development Workflow

- Vite for asset compilation
- GitHub Actions for CI/CD

## Data Models and Storage

### MuxAsset

Stores the relationship between Statamic assets and Mux resources:

- Statamic Asset
- Mux Asset ID
- Playback IDs

### MuxPlaybackIds

Represents playback policies and access controls:

- Unique ID to pass into frontend components
- Policy type (public, signed)
