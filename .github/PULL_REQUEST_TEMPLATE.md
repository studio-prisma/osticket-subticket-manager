## What changes

<!-- One or two sentences. What does this PR do, and why? -->

## Type

- [ ] Bug fix
- [ ] New feature
- [ ] Documentation
- [ ] CI / tooling
- [ ] Refactor (no behaviour change)

## Security impact

- [ ] Touches `TicketAccessGuard` or any access decision in the AJAX controller
- [ ] Touches the `subticket_parent` session hand-off or the signal handlers
- [ ] Touches SQL (`DatabaseService`, `HierarchyService`, the admin overview)
- [ ] Touches file deployment into `scp/` (`AssetDeployer`)
- [ ] Touches configuration validation (`pre_save`)
- [ ] No security-relevant surface touched

If any box above is checked, describe what an agent (or a client) could do after this change that they could not do before, and why that is acceptable:

<!-- ... -->

## Verification

- [ ] `vendor/bin/phpunit` passes locally (state your PHP version: ______)
- [ ] Verified against a real osTicket 1.18.x instance as a **non-admin** agent
- [ ] No ticket data, hostnames, credentials or absolute paths added
- [ ] `CHANGELOG.md` has an entry under `[Unreleased]`
- [ ] New files under `src/` are listed in `.releaseinclude`

## Related issues

Closes #
