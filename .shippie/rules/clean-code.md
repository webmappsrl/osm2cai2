# Clean Code Rules

## Method Design
- **Single Responsibility**: Each method should do one thing and do it well.
- **Method Length**: Methods should be short (ideally under 20 lines). If longer, consider extracting smaller methods.
- **Parameters**: Limit method parameters (max 3-4). Use objects or arrays for multiple related parameters.
- **Return Types**: Always declare explicit return types for methods and functions.
- **Early Returns**: Use early returns to reduce nesting and improve readability.

## Naming Conventions
- **Descriptive Names**: Use clear, descriptive names for variables, methods, and classes.
- **Avoid Abbreviations**: Prefer full words over abbreviations (`user` instead of `usr`).
- **Boolean Names**: Boolean variables should be named with is/has/can prefixes (`isValid`, `hasPermission`).
- **Constant Naming**: Use UPPERCASE for constants and meaningful names for magic numbers.

## Code Structure
- **Avoid Deep Nesting**: Limit nesting levels (max 3-4 levels). Use early returns and guard clauses.
- **Extract Constants**: Replace magic numbers and strings with named constants.
- **Remove Dead Code**: Remove commented-out code and unused methods/variables.
- **Consistent Formatting**: Follow PSR-12 coding standards consistently.

## Error Handling
- **Specific Exceptions**: Use specific exception types instead of generic Exception.
- **Error Messages**: Provide clear, actionable error messages.
- **Logging**: Log errors at appropriate levels with sufficient context.
- **Graceful Degradation**: Handle errors gracefully without breaking the entire flow.

## Comments and Documentation
- **Self-Documenting Code**: Write code that explains itself through good naming and structure.
- **Why Not What**: Comments should explain why something is done, not what is being done.
- **Update Comments**: Keep comments in sync with code changes.
- **PHPDoc**: Use proper PHPDoc annotations for all public methods.

## Class Design
- **Single Responsibility**: Each class should have one reason to change.
- **Small Classes**: Keep classes focused and small.
- **Composition over Inheritance**: Prefer composition over deep inheritance hierarchies.
- **Immutability**: Make objects immutable when possible.

## Refactoring Opportunities
- **Duplicate Code**: Look for and eliminate code duplication.
- **Long Parameter Lists**: Replace with objects or configuration arrays.
- **Large Classes**: Break down large classes into smaller, focused ones.
- **Complex Conditionals**: Extract complex conditions into well-named methods.

## Testing Considerations
- **Testable Code**: Write code that is easy to test in isolation.
- **Dependency Injection**: Use dependency injection instead of creating dependencies internally.
- **Pure Functions**: Prefer pure functions (no side effects) when possible.
- **Mocking**: Design code to allow easy mocking of dependencies. 