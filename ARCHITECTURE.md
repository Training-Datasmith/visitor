# Architecture: visitor

## Purpose

A Laravel package that tracks page visits and detects visitor information (device, browser, OS, geographic location) from HTTP requests. Stores visit records in the database and provides Eloquent relationships for models that can be visited.

## Directory Structure

```
src/
  Visitor.php                       - Core visitor tracking service
  Agent.php                         - Detects browser, OS, and device type from User-Agent string
  Contracts/
    Geo_Ip_Resolver.php             - Contract for geographic IP resolution
    User_Agent_Parser.php           - Contract for User-Agent parsing drivers
  Drivers/
    Jenssegers_Agent.php            - User-Agent driver using jenssegers/agent
    UA_Parser.php                   - User-Agent driver using ua-parser/uap-php
  Resolvers/GeoIp/
    Null_Resolver.php               - No-op GeoIP resolver (returns empty location)
    Steve_Bauman_Resolver.php       - GeoIP resolver using stevebauman/location
  Middlewares/
    Log_Visits.php                  - Laravel HTTP middleware: records a visit per request
  Models/
    Visit.php                       - Eloquent model for visit records
  Traits/
    Can_Visit.php                   - Added to User model: records visits for authenticated users
    Visitable.php                   - Added to visited models: hasMany(Visit) relationship
    Visitor.php                     - Shared visitor tracking logic
  Facade/
    Agent.php                       - Laravel Facade for Agent
    Visitor.php                     - Laravel Facade for Visitor
  Provider/
    Agent_Service_Provider.php      - Binds Agent into the container
    Visitor_Service_Provider.php    - Registers middleware, bindings, and migrations
  helpers.php                       - Global visitor() helper function
```

## Key Design Decisions

- **Driver pattern for User-Agent parsing**: Supports both `jenssegers/agent` and `ua-parser/uap-php` via the `User_Agent_Parser` contract — swap drivers without changing application code.
- **Pluggable GeoIP**: `Geo_Ip_Resolver` contract allows using any GeoIP backend or the null resolver when location is not needed.
- **Middleware-driven recording**: Visit recording happens in HTTP middleware, keeping models and controllers free of tracking concerns.
- **Traits for integration**: `Visitable` and `Can_Visit` traits make it easy to add visit tracking to any Eloquent model without boilerplate.

## Extension Points

- Implement `Geo_Ip_Resolver` to add MaxMind GeoIP2 or a custom provider.
- Implement `User_Agent_Parser` to use a different UA parsing library.

## Dependency Flow

```
HTTP Request
  └─> Log_Visits middleware
        └─> Visitor::visit(Request)
              └─> Agent — parse User-Agent
              └─> Geo_Ip_Resolver — resolve IP → location
              └─> Visit::create([...]) — persist to database
```
