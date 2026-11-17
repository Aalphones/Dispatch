# Findings

- [x] -> Phase 2: Reordering scheduled queue posts uses a separate persisted `schedule_order`; keep fixed-date posts outside that list and preserve `scheduled_since` as the earliest eligible release boundary.
- [x] -> Phase 3: MCP ordering accepts the full current ID set and uses `blog:write`; it must never expose approval or release actions.
