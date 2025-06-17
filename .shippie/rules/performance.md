# Performance Rules

## Database Performance
- **N+1 Query Prevention**: Always use eager loading (`with()`) when iterating over relationships.
- **Query Optimization**: Review database queries for optimization opportunities - use `EXPLAIN` when in doubt.
- **Selective Loading**: Use `select()` to load only necessary columns, especially for large datasets.
- **Chunking**: Use `chunk()` or `lazy()` for processing large datasets to avoid memory issues.
- **Indexes**: Ensure frequently queried columns have appropriate database indexes.

## Memory Management
- **Memory Leaks**: Be aware of potential memory leaks in long-running processes.
- **Large Collections**: Avoid loading large collections into memory at once.
- **Unset Variables**: Unset large variables when no longer needed.
- **Generator Functions**: Use generators for processing large datasets.

## Caching Strategies
- **Query Caching**: Cache expensive database queries when appropriate.
- **Application Caching**: Use Laravel's cache for frequently accessed data.
- **Cache Keys**: Use descriptive and consistent cache key naming.
- **Cache Invalidation**: Implement proper cache invalidation strategies.

## Queue Performance
- **Background Processing**: Move heavy operations to background jobs.
- **Queue Prioritization**: Use appropriate queue names and priorities.
- **Job Chunking**: Break large jobs into smaller, manageable chunks.
- **Failed Job Handling**: Implement proper failed job handling and retry logic.

## API Performance
- **Response Size**: Minimize API response size by returning only necessary data.
- **Pagination**: Implement pagination for endpoints returning large datasets.
- **Rate Limiting**: Implement rate limiting to prevent abuse.
- **HTTP Caching**: Use appropriate HTTP caching headers.

## File Operations
- **Streaming**: Use streaming for large file operations.
- **Lazy Loading**: Load files only when necessary.
- **File Caching**: Cache processed file results when appropriate.
- **Cleanup**: Clean up temporary files promptly.

## Frontend Performance (if applicable)
- **Asset Optimization**: Minimize and compress CSS/JS assets.
- **Image Optimization**: Optimize images for web delivery.
- **Lazy Loading**: Implement lazy loading for images and content.
- **CDN Usage**: Use CDNs for static assets when possible.

## Geometric Computations (OSM2CAI Specific)
- **Spatial Indexes**: Ensure PostGIS spatial indexes are properly configured.
- **Buffer Calculations**: Optimize buffer distance calculations and prefer ST_DWithin over ST_Distance.
- **Batch Operations**: Process geometric operations in batches when possible.
- **Result Caching**: Cache expensive geometric computation results.

## Monitoring and Profiling
- **Query Monitoring**: Monitor slow queries and optimize them.
- **Memory Monitoring**: Monitor memory usage in long-running processes.
- **Performance Metrics**: Track key performance metrics.
- **Profiling**: Use profiling tools to identify bottlenecks.

## General Performance
- **Algorithm Complexity**: Consider time complexity of algorithms, especially for large datasets.
- **Premature Optimization**: Avoid premature optimization - measure first, then optimize.
- **Code Efficiency**: Write efficient code without sacrificing readability.
- **Resource Cleanup**: Properly close file handles, database connections, etc. 