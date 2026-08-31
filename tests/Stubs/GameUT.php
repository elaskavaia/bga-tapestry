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

    function prepareUndoSavepoint($first = false) {}

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

    /** The capital grid is not modelled, the rest mirrors the real method. */
    function dbSetStructureLocation($structure_id, $location, $state = null, $message = "", $player_id = null) {
        if ($structure_id === null) {
            return;
        }
        $this->structures->setLocation($structure_id, $location, $state);
        $this->notifyMoveStructure($message, $structure_id, [], $player_id);
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

    function getCurrentEra($player_id) {
        return $this->era ?? parent::getCurrentEra($player_id);
    }
}
