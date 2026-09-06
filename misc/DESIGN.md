# Design notes

Game designer: Jamey Stegmaier

Developed by: started by apollo1001, continued by Victoria_La

If you see this file you probably are the unlucky person who wants to fix this game. I have to
warn you: it's a mess. Here are my design notes (I am Victoria_La).

## Raw assets

The source graphics (PSD, PDF, full size scans) are NOT in this repo, they live in
`~/Develop/bga/bga-assets/Tapestry/`. Only the exported sprites end up in `img/`.

## DB tables

- `card` and `structure` are standard "deck" type tables with an extra data field
  `card_location_arg2`.
- `benefit` is an action stack.
- `map` is the big map data.
- `capital` is the capital mat map data.
- `playerextra` is an extended player table. I copied the `player` table and added some fields,
  in case we support Automa and Shadow Empire bots (because we cannot put bots in the real
  `player` table).

### Card

The table contains tech cards, tapestry cards, civ cards, tiles and space tiles.

- `card_id` - unique card id, auto-increment
- `card_type` - card type, defined by constants such as `CARD_CIVILIZATION`

#### Civilizations in `card`

- `card_type` - 5 (`CARD_CIVILIZATION`)
- `card_type_arg` - unique civ type defined in the material file
- `card_location` - `deck_civ` when in deck; `hand` when in hand; `discard` when in discard
- `card_location_arg` - player_id when location is `hand`; deck position when `deck_civ` or
  `discard`
- `card_location_arg2` - not used for civ

#### Tapestries in `card`

- `card_type` - 3 (`CARD_TAPESTRY`)
- `card_type_arg` - unique tapestry card type defined in the material file, there are multiple
  instances of the TRAP card
- `card_location` - `deck_tapestry` when in deck; `hand` when in hand; `discard` when in discard;
  `era1`..`era4` when played (no card is ever played at income 5, so `era5` does not occur);
  `era_6` (underscore) when covered by an overplay
- `card_location_arg` - player_id when location is `hand` or `era*`; deck position when
  `deck_tapestry` or `discard`
- `card_location_arg2` - used for special effects

#### Hex tiles in `card`

- `card_type` - 1 (`CARD_TERRITORY`)
- `card_type_arg` - unique territory type defined in the material file
- `card_location` - `deck_territory` when in deck; `hand` when in hand; `discard` when in
  discard; `map` on the main map; `islanders` when on the Islanders civ map
- `card_location_arg` - player_id when location is `hand`; deck position when `deck_territory`
  or `discard`; rotation (0-5) when `map`
- `card_location_arg2` - when location is `map`, the coords in the form `${x}_${y}`, e.g. `1_2`

### Structure

The table contains outposts, cubes, landmarks and income buildings.

- `card_id` - unique id, auto-increment
- `card_type` - structure type, defined by constants such as `BUILDING_OUTPOST`
- `card_location_arg` - player_id
- `card_type_arg` - 1 when toppled on the map

#### Income buildings

- `card_location` - `capital_cell_${player_id}_${x}_${y}` when in the capital (e.g.
  `capital_cell_2300663_11_9`); `income` when on the income track; `land_${x}_${y}` when on the
  map

#### Landmarks

- `card_type` - 6 (`BUILDING_LANDMARK`)
- `card_location` - `landmark_mat_slot${lm_type}` when on the mat (e.g. `landmark_mat_slot3`);
  `hand` when in hand (usually only Automa)
- `card_location_arg2` - landmark type id

#### Cubes

- `card_location` - `tech_slot_${track}_${slot}` when on a track (e.g. `tech_slot_1_2`);
  `civ_${civ_type}_${slot}` when on a civ mat (e.g. `civ_15_2`)
- `card_location_arg` - owner. A cube on a civ mat can belong to an opponent (GENIES keeps the
  opponents' tokens in `civ_44_0`, its slot 0). A cube in a `capital_cell_*` is a WEEFOLK plot
  token and belongs to the WEEFOLK player, not to the owner of that capital.

### Benefit

- `benefit_id` - primary key
- `benefit_category` - there are a few categories which, for some reason, are handled
  differently: `standard`, `bonus`, `civ`
- `benefit_type` - type of benefit (i.e. subrule); in case of `bonus` it's the type of resource
  to pay (???)
- `benefit_prerequisite` - not actually a prerequisite, it's an order field, lower number goes
  first
- `benefit_quantity` - quantity for `standard`, number of resources to pay for `bonus`
- `benefit_data` - assorted crap, I use it to put the "reason" for `standard`
- `benefit_player_id` - who owns it

#### `benefit_category`

- `standard` - `benefit_type` is a number representing a standard benefit listed in the
  `$this->benefit_types` table; `benefit_quantity` is how many times.
- `o,...` - starts with `o` (means "or"), the rest is a list of standard benefits the player
  chooses one of.
- `a,...` - starts with `a` (means "and"), the rest is a list of standard benefits, all of them
  must be taken but the player chooses the order.
- `civ` - almost a standard benefit but `benefit_type` is the civilization id, not a key in
  `benefit_types`.
- `bonus` - this is the weird one:
  - `benefit_quantity` - how many resources/things to pay; N pays exactly N units and gains the
    benefit list once; -1 pays any number and gains the list once per unit paid (DEMOCRACY).
    `stBonus` skips the row when the player cannot pay N; a negative quantity never skips.
  - `benefit_type` - what to pay; I think it's a key into `$this->benefit_types` but used in
    the reverse meaning.
  - `benefit_data` - comma separated list of standard benefits to gain.

## Benefit resolution rules

Let's call everything that can give you something a "benefit". That includes standard benefits,
bonuses, awards, civ abilities, tech upgrades, tapestry abilities, etc.

A benefit may contain more than one simple action:

- `A, B` - A then B
- `A / B` - A or B (can be more)
- `A + B` - A then B, or B then A
- `A > B` - A then B (where A has to be paid in full)

A simple action can be:

- mandatory if possible - can decline if not possible
- mandatory - cannot be performed if not possible
- mandatory payment - payment has to be in full (same as mandatory)
- mandatory gain - gain as much as possible, cut off at max if not (same as mandatory if
  possible)
- optional - provide a decline button

Track advancement slot:

- Standard benefit - mandatory if possible; multiple simple actions are treated as `A + B`
- Bonus - optional `A > B` (A is a mandatory payment, B is mandatory if possible)
- Landmark bonus - only if advanced (i.e. from below) onto the slot (except Futurists) and the
  landmark is not claimed

Tech card:

- Circle - optional
- Square - mandatory if possible, prerequisite

Income:

- Civ abilities - optional, multiple are treated as `A + B`
- Play Tapestry - mandatory if possible, if not, a face down card
- Upgrade - optional
- Income - mandatory gain

`TrackMove(track, spot, direction, freedom, modes)`:

- direction - 0 / +1 / -1
- freedom - optional, mandatory, mandatory if possible
- modes - take_benefit, pay_bonus, free_bonus, maxout_bonus, no_landmark

## Player lifecycle

`playerextra.player_income_turns`:

- 1..5 - current era, bumped at the start of the income turn (`takeIncomeAuto`)
- 6 - finished, written by `effect_endOfIncome` at turn 5 after `finalGameScoring`
- `isPlayerFinished` - era > 5, or a zombie real player. `getPlayersInGame` filters on it,
  `stTransition` skips finished players and calls `endOfGame` when nobody is left.
- The client greys a player out on the `income` notification with `turn_number` >= 6.

Globals around a turn:

- `current_player_turn` - whose turn it is, as opposed to the active player (cross-player
  prompts change the active player, not this)
- `income_turn` - the current turn is an income turn; set in `takeIncomeAuto`, cleared in
  `effect_endOfTurn`
- `income_turn_phase` - `INCOME_*` phase inside the active player's income turn, 0 outside
