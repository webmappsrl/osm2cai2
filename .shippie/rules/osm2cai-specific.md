# OSM2CAI Project Specific Rules

## HikingRoute Model
- **OSM2CAI Status**: Always validate that `osm2cai_status` changes follow the correct workflow (0->1->2->3->4).
- **Geometry Handling**: Ensure geometry updates trigger the necessary geometric computation jobs.
- **OSMFeatures Sync**: When updating `osmfeatures_data`, ensure the nested `properties.osm2cai_status` stays in sync with the main `osm2cai_status` field.
- **Model Events**: Be careful with model events in HikingRoute - use `saveQuietly()` when needed to avoid infinite loops.

## Geometric Computations
- **Job Dispatching**: Geometry-related operations should dispatch appropriate jobs (intersections, nearby entities) to the 'geometric-computations' queue.
- **Buffer Calculations**: When calculating nearby entities (huts, springs, POIs), ensure buffer distances are configurable and reasonable.
- **PostGIS Functions**: Review PostGIS functions for correctness and performance - prefer ST_DWithin over ST_Distance for proximity queries.

## Data Synchronization
- **Legacy Migration**: When syncing from legacy databases, always use transactions and handle missing records gracefully.
- **OSMFeatures API**: Handle API failures gracefully and implement proper retry mechanisms.
- **Batch Processing**: Large data operations should show progress and handle memory efficiently.

## Commands
- **Dry Run Support**: All data modification commands should support `--dry-run` flag.
- **Progress Indication**: Long-running commands must show progress bars and meaningful status messages.
- **Error Reporting**: Log errors appropriately and provide summary statistics.
- **Quiet Operations**: Use `updateQuietly()` and `saveQuietly()` when appropriate to avoid triggering unwanted events.

## API Integration
- **External Services**: Handle external API failures (DEM service, OSMFeatures) with proper error handling and fallbacks.
- **Rate Limiting**: Implement rate limiting for external API calls.
- **Timeout Handling**: Set reasonable timeouts for HTTP requests.

## Validation Status
- **Status 4 Protection**: Routes with `osm2cai_status = 4` should have additional protection against unwanted changes.
- **User Authorization**: Check user permissions before allowing validation operations.
- **Region Validation**: Ensure users can only validate routes in their assigned regions.

## Performance Considerations
- **Eager Loading**: Load relationships like `sectors` when computing `ref_rei_comp` to avoid N+1 queries.
- **Caching**: Cache expensive computations like TDH data and region/sector intersections.
- **Queue Management**: Use appropriate queue names for different types of jobs (geometric-computations, sync, etc.).

## Data Integrity
- **Orphaned Relations**: Clean up orphaned relationships when deleting models.
- **JSON Validation**: Validate `osmfeatures_data` structure before saving.
- **Required Fields**: Ensure critical fields like `osmfeatures_id` are always properly formatted (e.g., 'R' + osm_id). 