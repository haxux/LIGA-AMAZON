# Delta for public-views

## ADDED Requirements

### Requirement: Team crest images render from uploaded crest_path with fallback

Public pages that display a team identity (standings table rows, game cards) MUST render
the team's real crest image from `crest_path` via the public disk when it is set, and MUST
render a generic fallback placeholder — not a broken image or an empty element — when
`crest_path` is null.

#### Scenario: Team with an uploaded crest shows its real image

- GIVEN a team has `crest_path` set to an uploaded file
- WHEN that team appears in a standings table row or a game card
- THEN the real crest image renders using that team's `crest_path`

#### Scenario: Team without a crest shows a generic fallback

- GIVEN a team has `crest_path` null
- WHEN that team appears in a standings table row or a game card
- THEN a generic fallback placeholder renders in place of the crest image
