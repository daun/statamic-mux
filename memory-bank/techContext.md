# Technical Context

## Technology Stack

### Core Dependencies

- **PHP 8.1+**: Base language requirement
- **Laravel 9+**: Framework foundation
- **Statamic 4+**: CMS platform
- **Mux PHP SDK**: Official API client for Mux services

### Frontend Components

- **Vue.js**: Used for Control Panel components
- **Tailwind CSS**: Utility-first CSS framework

### Infrastructure Requirements

- **Queue System**: Laravel queues for async processing
- **Webhook Receiver**: For Mux event handling

## Core Technical Patterns

### Dependency Management

```php
// composer.json
"require": {
    "php": "^8.1",
    "statamic/cms": "^4.0",
    "muxinc/mux-php": "^3.0"
}
```

### Configuration Structure

The addon uses Laravel's configuration system with `config/mux.php` defining core settings:

```php
// Key configuration options
[
    'access_token_id' => env('MUX_TOKEN_ID'),
    'access_token_secret' => env('MUX_TOKEN_SECRET'),
    'webhook_secret' => env('MUX_WEBHOOK_SECRET'),
    'signing_key' => env('MUX_SIGNING_KEY')
]
```

### API Integration

The `MuxApi` class wraps the Mux PHP SDK to provide a consistent interface:

```php
// API client initialization
$client = new \MuxPhp\Api\AssetsApi(
    new \MuxPhp\Configuration([
        'username' => config('mux.access_token_id'),
        'password' => config('mux.access_token_secret')
    ])
);
```

### Async Processing

All Mux operations are performed asynchronously through Laravel's queue system:

```php
// Example job dispatch
CreateMuxAssetJob::dispatch($asset)->onQueue('mux');
```

## Key Technical Constraints

## Development Tools

### Testing

- PHPUnit/Pest for PHP testing

### Development Workflow

- Vite for asset compilation
- GitHub Actions for CI/CD

## Data Models and Storage

### MuxAsset

Stores the relationship between Statamic assets and Mux resources:

- Mux Asset ID
- Playback IDs
- Processing status
- Metadata

### MuxPlaybackIds

Represents playback policies and access controls:

- Policy type (public, signed)
- Token requirements
- Domain restrictions
