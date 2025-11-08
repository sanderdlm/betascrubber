# Project Guidelines for Claude

## Dependency Injection & Autowiring

This project uses **PHP-DI** with **autowiring enabled**.

### Rules:
- **NEVER manually instantiate services in constructors** (e.g., `$this->foo = new FooService()`)
- Simply add dependencies as constructor parameters and they will be automatically injected
- The DI container is configured in `public/index.php` with `useAutowiring(true)`
- **Always inject interfaces, not concrete implementations** when multiple implementations exist

### Example:
```php
// ❌ WRONG
class MyController {
    private FooService $foo;

    public function __construct(BarService $bar) {
        $this->foo = new FooService($bar); // DON'T DO THIS
    }
}

// ✅ CORRECT
class MyController {
    public function __construct(
        private FooServiceInterface $foo,
        private BarService $bar
    ) {
    }
}
```

### Environment-Based Bindings

Use environment variables to determine which implementation to inject. Configure bindings in `public/index.php`:

```php
$containerBuilder->addDefinitions([
    MyServiceInterface::class => function ($container) use ($appEnv) {
        if ($appEnv === 'dev') {
            return new LocalMyService();
        }
        return new S3MyService($container->get(StorageService::class));
    },
]);
```

**Example:** `VideoStorageManagerInterface` has two implementations:
- `LocalVideoStorageManager` - Used in `APP_ENV=dev` (local filesystem)
- `S3VideoStorageManager` - Used in production (DigitalOcean Spaces)

### Injecting Primitives

You can also autowire scalar values like strings or integers:

```php
$containerBuilder->addDefinitions([
    'tmpDir' => '/tmp',
]);

// Then inject into services
class MyService {
    public function __construct(
        private string $tmpDir
    ) {
    }
}
```

## Code Style

### PHP Version
This project uses **PHP 8.4**. Use all modern PHP features.

### Type Hints
- **Always use strict type hints** for everything
- Type hint class constants: `private const int TIMEOUT = 300;`
- Type hint properties: `private string $name;`
- Type hint parameters and return types: `public function foo(string $bar): int`

### Documentation
- **DO NOT add docblock comments** for functions/methods
- Type hints provide all necessary documentation
- Only add comments for complex business logic that needs explanation
- Use inline comments (`//`) sparingly for non-obvious code

### String Concatenation
- **ALWAYS use explicit concatenation** with `.` operator for building paths and patterns
- **NEVER use string interpolation with curly braces** `"{$var}"` when building file paths or glob patterns
- Curly braces in strings can be misinterpreted by functions like `glob()` as brace expansion patterns

```php
// ❌ WRONG - glob() interprets curly braces as patterns
$pattern = "{$tmpDir}/{$hash}___*_frames";
$path = "{$dir}/{$file}.jpg";

// ✅ CORRECT - explicit concatenation
$pattern = $tmpDir . '/' . $hash . '___*_frames';
$path = $dir . '/' . $file . '.jpg';
```

### CSS & Styling
- **NEVER use inline styles** in Twig templates
- All styles must be defined in `public/css/style.css`
- Use semantic CSS classes for styling elements
- Keep markup clean and separate from presentation

### Example:
```php
// ❌ WRONG - unnecessary docblocks
/**
 * Gets the user by ID
 * @param int $id The user ID
 * @return User The user object
 */
public function getUser(int $id): User
{
    return $this->repository->find($id);
}

// ✅ CORRECT - types are self-documenting
public function getUser(int $id): User
{
    return $this->repository->find($id);
}
```

## Architecture

### Service Layer
Business logic lives in services under `src/Service/`:
- `VideoDownloadService` - Video downloading with yt-dlp
- `VideoSplitService` - Frame extraction with ffmpeg
- `VideoStorageManagerInterface` - File/folder management abstraction
  - `S3VideoStorageManager` - S3/Spaces implementation (production)
  - `LocalVideoStorageManager` - Local filesystem implementation (dev)
- `StorageService` - Low-level S3 operations

### Controllers
Controllers should be **lean** and only handle:
- HTTP request/response
- Input validation
- Calling services
- Rendering views

Keep all business logic in services.

### Environment Detection
Set `APP_ENV=dev` for local development or `APP_ENV=production` for production.
- **Dev:** Uses local filesystem for all storage operations
- **Production:** Uses DigitalOcean Spaces (S3-compatible) for storage

The DI container automatically binds the correct `VideoStorageManagerInterface` implementation based on `APP_ENV`.

### Environment Configuration
The `.env` file in `public/` must be properly configured:

**For development (`APP_ENV=dev`):**
```
APP_ENV=dev
TMP_DIR=/absolute/path/to/project/public/tmp
```

**For production (`APP_ENV=production`):**
```
APP_ENV=production
TMP_DIR=/tmp
```

**IMPORTANT:** `TMP_DIR` must be an absolute path. In dev mode, it should point to `public/tmp` so files are web-accessible. Controllers should NEVER be aware of filesystem paths - only services use `tmpDir`.

## File Structure
```
src/
  Controller/     # HTTP request handlers
  Service/        # Business logic
scripts/          # Background processing scripts
public/           # Web root
  index.php       # Application entry point
  tmp/            # Local temporary storage
templates/        # Twig templates
```
