# Security Rules

## Input Validation
- **Validate All Inputs**: Every user input must be validated before processing.
- **Sanitize Data**: Sanitize inputs to prevent XSS and other injection attacks.
- **Type Validation**: Ensure inputs match expected data types and formats.
- **Size Limits**: Implement reasonable size limits for file uploads and text inputs.

## SQL Security
- **Prepared Statements**: Always use prepared statements or Eloquent ORM to prevent SQL injection.
- **Parameter Binding**: When using raw SQL, always bind parameters safely.
- **Query Review**: Review complex raw SQL queries for potential injection vulnerabilities.
- **Database Permissions**: Ensure database users have minimal required permissions.

## Authentication & Authorization
- **Permission Checks**: Verify user permissions before allowing sensitive operations.
- **Role-Based Access**: Implement proper role-based access control.
- **Session Security**: Secure session handling and timeout management.
- **Password Security**: Enforce strong password policies and secure storage.

## API Security
- **Rate Limiting**: Implement rate limiting to prevent abuse.
- **Input Validation**: Validate all API inputs thoroughly.
- **Output Encoding**: Properly encode outputs to prevent XSS.
- **CORS Configuration**: Configure CORS policies appropriately.

## Data Protection
- **Sensitive Data**: Identify and protect sensitive data (passwords, tokens, personal info).
- **Encryption**: Use encryption for sensitive data at rest and in transit.
- **Logging Security**: Avoid logging sensitive information.
- **Data Minimization**: Only collect and store necessary data.

## File Security
- **Upload Validation**: Validate file types, sizes, and content.
- **File Storage**: Store uploaded files outside web root when possible.
- **File Permissions**: Set appropriate file and directory permissions.
- **Malware Scanning**: Consider malware scanning for uploaded files.

## Configuration Security
- **Environment Variables**: Store secrets in environment variables, not in code.
- **Debug Mode**: Ensure debug mode is disabled in production.
- **Error Messages**: Don't expose sensitive information in error messages.
- **Security Headers**: Implement appropriate security headers.

## Third-Party Dependencies
- **Dependency Updates**: Keep dependencies updated to patch security vulnerabilities.
- **Vulnerability Scanning**: Regularly scan for known vulnerabilities.
- **Package Integrity**: Verify integrity of third-party packages.
- **Minimal Dependencies**: Only include necessary dependencies.

## Specific Laravel Security
- **Mass Assignment**: Protect against mass assignment vulnerabilities.
- **CSRF Protection**: Ensure CSRF protection is enabled and working.
- **Route Security**: Secure routes with appropriate middleware.
- **Blade Security**: Use proper escaping in Blade templates. 