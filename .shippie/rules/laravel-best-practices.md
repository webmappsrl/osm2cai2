# Laravel Best Practices Rules

## Models
- **Fillable vs Guarded**: Always use `$fillable` instead of `$guarded` for security. Check that all fillable fields are actually safe to mass assign.
- **Eloquent Relationships**: Ensure relationships use proper naming conventions and return types (e.g., `hasMany()`, `belongsTo()`).
- **Model Events**: Use model events (boot method) carefully - check for infinite loops and performance issues.
- **Soft Deletes**: When using soft deletes, ensure all queries properly handle deleted records.
- **Mass Assignment**: Verify that mass assignment is protected and only safe fields are fillable.

## Database
- **Query Performance**: Look for N+1 query problems. Suggest eager loading with `with()` when iterating over relationships.
- **Raw SQL**: Review raw SQL queries for security vulnerabilities (SQL injection) and suggest Eloquent alternatives when possible.
- **Database Transactions**: Ensure database operations that should be atomic use transactions.
- **Indexing**: Suggest adding database indexes for frequently queried columns.

## Controllers
- **Thin Controllers**: Controllers should be thin - move business logic to Services or Models.
- **Request Validation**: Use Form Requests for validation instead of validating in controllers.
- **Resource Classes**: Use API Resources for consistent API responses.
- **Authorization**: Ensure proper authorization using Policies or Gates.

## Services
- **Single Responsibility**: Services should have a single, clear responsibility.
- **Dependency Injection**: Use constructor injection instead of static calls when possible.
- **Return Types**: All service methods should have explicit return type declarations.

## Commands
- **Error Handling**: Console commands should handle errors gracefully and provide meaningful output.
- **Progress Bars**: Long-running commands should show progress to users.
- **Memory Usage**: Be aware of memory usage in commands that process large datasets.
- **Queues**: Consider using queues for long-running tasks instead of synchronous execution.

## Security
- **Input Validation**: All user inputs must be validated and sanitized.
- **SQL Injection**: Avoid raw SQL queries; use parameter binding when necessary.
- **Mass Assignment**: Check for mass assignment vulnerabilities.
- **Authorization**: Verify that sensitive operations check user permissions.

## Performance
- **Eager Loading**: Use eager loading to prevent N+1 queries.
- **Caching**: Consider caching for expensive operations.
- **Queue Jobs**: Move heavy operations to background jobs.
- **Database Optimization**: Review database queries for optimization opportunities. 