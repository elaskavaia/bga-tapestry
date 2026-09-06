<?php

declare(strict_types=1);

require_once __DIR__ . "/../../tapestry.game.php";
require_once __DIR__ . "/DeckInMem.php";
require_once __DIR__ . "/BenefitsInMem.php";

/**
 * Test harness for the game class.
 *
 * The framework stubs run no SQL: every DB accessor returns an empty row. This class replaces the
 * three tables the game logic actually reads back - card, structure and benefit - with in memory
 * models, so tests drive the real engine instead of modelling each table again.
 *
 * Not modelled: the player/playerextra rows (resources, tracks, era) and the capital grid. Those
 * stay per test stubs. The civ selects of argCivAbility() and action_unblock() go straight through
 * getCollectionFromDB(), so they still see an empty benefit table.
 */
class GameUT extends Tapestry {
    public $curid;

    public BenefitsInMem $benefits;
    /** getCurrentEra() for every player, playerextra is not modelled */
    public ?int $era = null;

    function __construct(int $players = 2) {
        parent::__construct();
        include __DIR__ . "/../../material.inc.php";
        include __DIR__ . "/../../states.inc.php";
        $this->gamestate->_setStates($machinestates);
        $this->benefits = new BenefitsInMem();
        $this->_setPlayerBasicInfo(array_fill_keys(range(1, $players), []));
        $this->curid = 1;
        $this->_setCurrentPlayerId($this->curid);
    }

    function init() {
        $this->gamestate->changeActivePlayer(1);
        $this->gamestate->jumpToState(2);
    }

    function getNewDeck(string $tableName): \Bga\GameFramework\Components\Deck {
        $deck = new DeckInMem();
        $deck->init($tableName);
        return $deck;
    }

    /** @var int[] Predetermined bgaRand() results, consumed in order; falls back to $min when empty. */
    public array $randQueue = [];

    function bgaRand(int $min, int $max): int {
        if (!$this->randQueue) {
            fwrite(
                STDERR,
                "\nWARNING: bgaRand($min, $max) with an empty randQueue, returning $min. Use seedRand() to make this test deterministic.\n"
            );
            return $min;
        }
        return (int) array_shift($this->randQueue);
    }

    function seedRand(int ...$values): void {
        $this->randQueue = array_merge($this->randQueue, $values);
    }

    /** The real one moves the row with raw SQL, which the in memory card model never sees. */
    function effect_discardCard($card_id, $player_id = null, $location = null, $private = false) {
        foreach (is_array($card_id) ? $card_id : [$card_id] as $card) {
            $id = is_array($card) ? $card["card_id"] : $card;
            $this->cards->setLocation((int) $id, $location ?? "discard");
        }
    }

    /** Active player at every savepoint taken, in order; the real one writes the undo tables. */
    public array $undoSavepoints = [];

    function prepareUndoSavepoint($first = false) {
        $this->undoSavepoints[] = (int) $this->getActivePlayerId();
    }

    /**
     * The civ ability action as the client sends it, so the civ dispatch in argCivAbilitySingle and
     * saction_civTokenAdvance is exercised rather than the civ class being called directly.
     */
    function civTokenAdvance(int $cid, int $player_id, int $spot, $extra = ""): void {
        $this->gamestate->changeActivePlayer($player_id);
        $this->gamestate->jumpToState(14);
        $this->action_civTokenAdvance($cid, $spot, $extra);
    }

    /** PHPUnit prints the trace of anything that actually escapes, the manual dump is only noise. */
    function logStackTrace($message) {}

    /** The framework stub reports no stats at all, so dbIncStatChecked would reject every one. */
    function getStatTypes() {
        static $stats_type = null;
        if ($stats_type === null) {
            include __DIR__ . "/../../stats.inc.php";
        }
        return $stats_type;
    }

    /** Everything sent so far, as the framework stub collected it: type, log, args. */
    function notifications(): array {
        return $this->notify->_getNotifications();
    }

    function notificationTexts(): array {
        return array_column($this->notifications(), "log");
    }

    function notificationsOfType(string $type): array {
        return array_values(array_filter($this->notifications(), fn($notif) => $notif["type"] == $type));
    }

    /** The first notification whose log holds the fragment, for logs that share a type. */
    function notificationLike(string $fragment): array {
        foreach ($this->notifications() as $notif) {
            if (str_contains($notif["log"], $fragment)) {
                return $notif;
            }
        }
        throw new BgaSystemException("no notification containing $fragment");
    }

    // ------------------------------------------------------------ card table

    function getCardsSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        return $this->cards->search($card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
    }

    function getCardInfoSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        $found = $this->getCardsSearch($card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
        return $found ? reset($found) : null;
    }

    function getCardInfoById($card_id, $aliased = false) {
        $card = $this->cards->getCard((int) $card_id);
        if (!$card) {
            return null;
        }
        return $aliased ? $card : $this->cards->sqlRow($card);
    }

    function getCardsInHand($player_id, $card_type, $card_type_arg = null, $card_ids = null) {
        $found = $this->getCardsSearch($card_type, $card_type_arg, "hand", $player_id);
        if ($card_ids) {
            $found = array_intersect_key($found, array_flip($card_ids));
        }
        return $found;
    }

    function getCardCountInHand($player_id, $type) {
        return count($this->getCardsSearch($type, null, "hand", $player_id));
    }

    function hasCiv($player_id, $civ_id) {
        return count($this->getCardsSearch(CARD_CIVILIZATION, $civ_id, "hand", $player_id)) > 0;
    }

    /** The real one reads the card table with raw SQL, which the in memory card model never sees. */
    function getCivOwner($civ_id) {
        $card = $this->getCardInfoSearch(CARD_CIVILIZATION, $civ_id, "hand");
        return $card ? $card["card_location_arg"] : null;
    }

    /**
     * The real ones read the card table with raw SQL, which the in memory card model never sees.
     * The SQL takes the highest card_id of the era, which is the card played last over the others.
     */
    function getLatestTapestry($player_id, $era = "%") {
        $found = $this->getCardsSearch(CARD_TAPESTRY, null, "era$era", $player_id);
        return $found ? end($found) : null;
    }

    function getTapestryOn($player_id, $loc) {
        $found = $this->getCardsSearch(CARD_TAPESTRY, null, $loc, $player_id);
        return $found ? end($found) : null;
    }

    /** The real one moves the rows with raw SQL, which the in memory card model never sees. */
    function dbMoveCards(array $card_ids, string $location, int $location_arg): void {
        foreach ($card_ids as $card_id) {
            $this->cards->setLocation((int) $card_id, $location);
            $this->cards->setLocationArg((int) $card_id, $location_arg);
        }
    }

    /** The real one moves the row with raw SQL, which the in memory card model never sees. */
    function dbSetTapestryEraSlot($card_id, $location, $player_id = 0) {
        $this->cards->setLocation((int) $card_id, $location);
        if ($player_id) {
            $this->cards->setLocationArg((int) $card_id, $player_id);
        }
    }

    /** Give a player a civilization the way the setup deal does, as a card in their hand. */
    function giveCiv($player_id, $civ_id): int {
        return $this->cards->addRow(CARD_CIVILIZATION, $civ_id, "hand", $player_id);
    }

    function addCard($type, string $location, $player_id = 1, $type_arg = 0): int {
        return $this->cards->addRow($type, $type_arg, $location, $player_id);
    }

    // ------------------------------------------------------- structure table

    function getStructuresSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        return $this->structures->search($card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
    }

    function getStructureInfoSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        $found = $this->getStructuresSearch($card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
        return $found ? reset($found) : null;
    }

    function getStructureInfoById($structure_id, $aliased = false) {
        $structure = $this->structures->getCard((int) $structure_id);
        if (!$structure) {
            return null;
        }
        return $aliased ? $structure : $this->structures->sqlRow($structure);
    }

    function getStructuresOnCiv($cid, $type = BUILDING_CUBE, $arg2 = null) {
        return $this->getStructuresSearch($type, null, "civ\\_$cid\\_%", null, $arg2);
    }

    function getStructuresOnCivExceptArg($cid, $type, $income_turn) {
        $found = $this->getStructuresSearch($type, null, "civ\\_$cid\\_%");
        if ($income_turn === null) {
            return $found;
        }
        return array_filter($found, fn($row) => $row["card_location_arg2"] != $income_turn);
    }

    function getStructureOnCivSlot($cid, $slot) {
        return $this->getStructureInfoSearch(null, null, "civ_{$cid}_$slot");
    }

    function dbAddStructure($player_id, $type, $type_arg, $destination, $arg2 = 0) {
        return $this->structures->addRow($type, $type_arg, $destination, $player_id, $arg2);
    }

    /** The real one moves the row with raw SQL, which the in memory structure model never sees. */
    function dbSetStructureLocation($structure_id, $location, $state = null, $message = "", $player_id = null) {
        if ($structure_id === null) {
            return;
        }
        $this->structures->setLocation($structure_id, $location, $state);
        if (startsWith($location, "capital_cell")) {
            $type = $this->structures->getCard($structure_id)["type"] + 1;
            $this->dbSetCapitalCell(getPart($location, 2), getPart($location, 3), getPart($location, 4), $type);
        }
        $this->notifyMoveStructure($message, $structure_id, [], $player_id);
    }

    function dbSetStructureLocationRot($structure_id, $location, $rot) {
        $this->structures->setLocation($structure_id, $location);
        $this->structures->setTypeArg($structure_id, $rot);
    }

    // -------------------------------------------------------- capital table

    /** capital_occupied per player, [player_id][x][y]; the framework stubs run no SQL. */
    public array $capital = [];

    /** Seeds the grid from a capital mat in material; mat 0 is an all empty one. */
    function setCapitalMat($player_id, int $cap = 0): void {
        $cells = array_fill(0, 15, array_fill(0, 15, 0));
        $grid = $cap ? $this->capitals[$cap]["grid"] : array_fill(0, 9, "000000000");
        foreach ($grid as $row => $line) {
            for ($y = 0; $y < 9; $y++) {
                $cells[$row + 3][$y + 3] = (int) substr($line, $y, 1);
            }
        }
        $this->capital[(int) $player_id] = $cells;
    }

    function getCapitalData($player_id) {
        if (!isset($this->capital[(int) $player_id])) {
            $this->setCapitalMat($player_id);
        }
        return $this->capital[(int) $player_id];
    }

    function dbSetCapitalCell($player_id, $x, $y, $type) {
        $this->getCapitalData($player_id);
        $this->capital[(int) $player_id][(int) $x][(int) $y] = (int) $type;
    }

    /** Seeds the landmark supply on the mat, one structure per landmark type. */
    function setLandmarkMat(array $types): void {
        foreach ($types as $type) {
            $this->structures->addRow(BUILDING_LANDMARK, 0, "landmark_mat_slot$type", 0, $type);
        }
    }

    /** Put a cube straight on the board, bypassing the supply in hand. */
    function addCubeAt($player_id, string $location, $type_arg = 0, $arg2 = 0): int {
        return $this->dbAddStructure($player_id, BUILDING_CUBE, $type_arg, $location, $arg2);
    }

    function structureLocation($structure_id): string {
        return $this->structures->getCard((int) $structure_id)["location"];
    }

    // --------------------------------------------------------- benefit table

    function benefitSingleEntry($cat, $type, $player_id, $quantity = 1, $data = "") {
        $this->benefits->insert($cat, $type, 0, $quantity, $data, $player_id);
        $this->notifyBenefitQueue();
    }

    function queueBonus($type, $quantity, $benefit, $after, $player_id) {
        if (!$this->isPlayerAlive($player_id)) {
            return null;
        }
        $id = $this->benefits->insert("bonus", $type, $after, $quantity, $benefit, $player_id);
        $this->notifyBenefitQueue();
        return $id;
    }

    function interruptBenefit() {
        $this->benefits->bumpPrerequisites();
    }

    function benefitCashed($benefit_table_id) {
        if (is_array($benefit_table_id) && isset($benefit_table_id["benefit_id"])) {
            $benefit_table_id = $benefit_table_id["benefit_id"];
        }
        $this->benefits->delete($benefit_table_id);
        $this->notifyBenefitQueue();
    }

    function dbDeleteBenefitsOfPlayer($player_id) {
        $this->benefits->deleteOfPlayer($player_id);
    }

    /**
     * What stBenefitManager does with the row on top of the stack: resolve it, then cash it on
     * true. It pops the head rather than searching, so a test naming a row that is queued behind
     * something else fails instead of quietly resolving out of order.
     */
    function resolveBenefit(int $ben, int $player_id = 1): void {
        $row = $this->benefitQueue()[0] ?? null;
        if (!$row || $row["benefit_category"] != "standard" || $row["benefit_type"] != $ben || $row["benefit_player_id"] != $player_id) {
            throw new BgaSystemException(
                "benefit $ben of player $player_id is not on top of the stack: " . implode(",", $this->benefitLabels())
            );
        }
        if ($this->awardBenefits($player_id, $ben, 1, $row["benefit_data"])) {
            $this->benefitCashed($row["benefit_id"]);
        }
    }

    /**
     * What action_choose_benefit and action_first_benefit do: take one option out of a composite
     * "o," or "a," row, put back what is left, then resolve the option taken.
     */
    function chooseOption(int $ben, int $player_id = 1): void {
        foreach ($this->benefitQueue() as $row) {
            $options = explode(",", $row["benefit_category"]);
            $op = array_shift($options);
            if (($op != "o" && $op != "a") || !in_array((string) $ben, $options)) {
                continue;
            }
            $left = $op == "o" ? (int) $row["benefit_quantity"] - 1 : 1;
            $this->reinjectCompositeBenefitWithChoiceRemoved($ben, $row, $left);
            $this->queueBenefitInterrupt($ben, $player_id, $row["benefit_data"]);
            $this->resolveBenefit($ben, $player_id);
            return;
        }
        throw new BgaSystemException("no pending choice offering benefit $ben");
    }

    function getCurrentBenefit($ben = null, $cat = "standard") {
        if (is_array($ben) && isset($ben["benefit_id"])) {
            return $this->benefits->byId($ben["benefit_id"]);
        }
        if ($ben !== null) {
            return $this->benefits->first(["benefit_type" => $ben, "benefit_category" => $cat]);
        }
        return $this->benefits->first();
    }

    function subtractCurrentBenefit($ben = null, $throw = false) {
        $benefit_data = $this->getCurrentBenefit($ben);
        if (!$benefit_data) {
            return parent::subtractCurrentBenefit($ben, $throw);
        }
        $count = $benefit_data["benefit_quantity"];
        if ($count <= 1) {
            $this->benefitCashed($benefit_data["benefit_id"]);
            return $benefit_data;
        }
        $this->benefits->set($benefit_data["benefit_id"], "benefit_quantity", $count - 1);
        $benefit_data["benefit_quantity"] = 1;
        return $benefit_data;
    }

    function setBenefitDataArg($bene, $arg, $commit = true) {
        $bene = parent::setBenefitDataArg($bene, $arg, false);
        if ($bene && $commit) {
            $this->benefits->set($bene["benefit_id"], "benefit_data", $bene["benefit_data"]);
        }
        return $bene;
    }

    function dbGetBenefits() {
        return $this->benefits->all();
    }

    /** The rows stBenefitManager would pop, in order. */
    function benefitQueue(): array {
        return array_values($this->benefits->all());
    }

    /** Pending rows as "category:type", standard rows shown as just the type. */
    function benefitLabels(): array {
        return array_map(
            fn($row) => $row["benefit_category"] == "standard" ? (string) $row["benefit_type"] : $row["benefit_category"],
            $this->benefitQueue()
        );
    }

    /** Position of a pending row in pop order, -1 when it is not on the stack. */
    function benefitPosition(string $cat, int $type, int $player_id): int {
        foreach ($this->benefitQueue() as $pos => $row) {
            if ($row["benefit_category"] == $cat && $row["benefit_type"] == $type && $row["benefit_player_id"] == $player_id) {
                return $pos;
            }
        }
        return -1;
    }

    /** getCurrentEra() per player, for tests where the players are not all in the same era. */
    public array $eras = [];

    function getCurrentEra($player_id) {
        return $this->eras[(int) $player_id] ?? ($this->era ?? parent::getCurrentEra($player_id));
    }

    /** The real one writes playerextra, which the framework stubs do not model. */
    function dbSetPlayerIncomeTurns($player_id, $turns) {
        $this->eras[(int) $player_id] = (int) $turns;
    }

    /** The player is inside their own income turn: the era may be 5 but extended play has not begun. */
    function startIncomeTurn(int $player_id, int $era): void {
        $this->eras[$player_id] = $era;
        $this->setGameStateValue("current_player_turn", $player_id);
        $this->setGameStateValue("income_turn", 1);
        $this->gamestate->changeActivePlayer($player_id);
    }

    /** An ordinary turn, the state every turn is in once its income phase is over. */
    function startPlayerTurn(int $player_id): void {
        $this->setGameStateValue("current_player_turn", $player_id);
        $this->setGameStateValue("income_turn", 0);
        $this->gamestate->changeActivePlayer($player_id);
    }

    // ------------------------------------------------------------- scores

    /** player_score per player, playerextra is not modelled. */
    public array $scores = [];

    function dbGetScore($player_id) {
        return $this->scores[(int) $player_id] ?? 0;
    }

    function dbSetScore($player_id, $count) {
        $this->scores[(int) $player_id] = (int) $count;
    }
}
