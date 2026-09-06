<?php

declare(strict_types=1);

/**
 * In memory replacement for the framework Deck.
 *
 * The framework stub is already in memory but keeps its rows private and has no location_arg2,
 * which both tapestry deck tables carry, so this one owns its store. It also answers in the
 * "card_*" column shape the raw SQL accessors of PGameXBody return, which is what lets GameUT
 * serve the Deck API and those accessors from a single table.
 */
class DeckInMem extends \Bga\GameFramework\Components\Deck {
    /** rows keyed by id: id, type, type_arg, location, location_arg, location_arg2 */
    public array $rows = [];
    private int $next_id = 1;

    function init(string $table): void {
        $this->table = $table;
    }

    // ---------------------------------------------------------------- store

    /** Insert one row the way an INSERT INTO card/structure does, returns the new id. */
    function addRow($type, $type_arg, string $location, $location_arg = 0, $location_arg2 = 0): int {
        $id = $this->next_id++;
        $this->rows[$id] = [
            "id" => $id,
            "type" => $type,
            "type_arg" => (int) $type_arg,
            "location" => $location,
            "location_arg" => (int) $location_arg,
            "location_arg2" => $location_arg2,
        ];
        return $id;
    }

    function setLocation($id, string $location, $location_arg2 = null): void {
        $id = (int) $id;
        if (!isset($this->rows[$id])) {
            throw new feException("DeckInMem: no row $id in $this->table");
        }
        $this->rows[$id]["location"] = $location;
        if ($location_arg2 !== null) {
            $this->rows[$id]["location_arg2"] = $location_arg2;
        }
    }

    function setLocationArg($id, $location_arg): void {
        $id = (int) $id;
        if (!isset($this->rows[$id])) {
            throw new feException("DeckInMem: no row $id in $this->table");
        }
        $this->rows[$id]["location_arg"] = (int) $location_arg;
    }

    function setTypeArg($id, $type_arg): void {
        $id = (int) $id;
        if (!isset($this->rows[$id])) {
            throw new feException("DeckInMem: no row $id in $this->table");
        }
        $this->rows[$id]["type_arg"] = (int) $type_arg;
    }

    /** SQL column shape, keyed by card_id, the way getCollectionFromDB returns these tables. */
    function sqlRow(array $row): array {
        return [
            "card_id" => $row["id"],
            "card_type" => $row["type"],
            "card_type_arg" => $row["type_arg"],
            "card_location" => $row["location"],
            "card_location_arg" => $row["location_arg"],
            "card_location_arg2" => $row["location_arg2"],
        ];
    }

    /**
     * The querySelectCardDataFromTable() search: card_location and card_location_arg2 accept SQL
     * wildcards, every field accepts an array as an IN clause.
     */
    function search($type = null, $type_arg = null, $location = null, $location_arg = null, $location_arg2 = null): array {
        $found = [];
        foreach ($this->rows as $id => $row) {
            if (!self::matchEquals($type, $row["type"])) {
                continue;
            }
            if (!self::matchEquals($type_arg, $row["type_arg"])) {
                continue;
            }
            if (!self::matchLike($location, $row["location"])) {
                continue;
            }
            if (!self::matchEquals($location_arg, $row["location_arg"])) {
                continue;
            }
            if (!self::matchLike($location_arg2, $row["location_arg2"])) {
                continue;
            }
            $found[$id] = $this->sqlRow($row);
        }
        return $found;
    }

    static function matchEquals($expected, $value): bool {
        if ($expected === null) {
            return true;
        }
        if (is_array($expected)) {
            return in_array($value, $expected);
        }
        return $expected == $value;
    }

    static function matchLike($pattern, $value): bool {
        if ($pattern === null) {
            return true;
        }
        if (is_array($pattern)) {
            return in_array($value, $pattern);
        }
        $pattern = (string) $pattern;
        if (strpos($pattern, "%") === false && strpos($pattern, "\\_") === false) {
            return $pattern == $value;
        }
        $regexp = "";
        for ($i = 0; $i < strlen($pattern); $i++) {
            $char = $pattern[$i];
            if ($char == "\\" && $i + 1 < strlen($pattern)) {
                $regexp .= preg_quote($pattern[++$i], "/");
            } elseif ($char == "%") {
                $regexp .= ".*";
            } elseif ($char == "_") {
                $regexp .= ".";
            } else {
                $regexp .= preg_quote($char, "/");
            }
        }
        return preg_match("/^$regexp$/i", (string) $value) === 1;
    }

    private function inLocations(array $row, array $locations): bool {
        return in_array($row["location"], $locations, true);
    }

    // ------------------------------------------------------------- deck api

    function createCards(array $cards, string $location = "deck", ?int $location_arg = null) {
        $auto = $location_arg === null;
        $pos = $auto ? $this->getExtremePosition(true, $location) + 1 : $location_arg;
        foreach ($cards as $row) {
            $nbr = (int) ($row["nbr"] ?? 1);
            for ($i = 0; $i < $nbr; $i++) {
                $this->addRow($row["type"] ?? "", $row["type_arg"] ?? 0, $location, $pos, 0);
                if ($auto) {
                    $pos++;
                }
            }
        }
    }

    function getExtremePosition(bool $getMax, string $location): int {
        $positions = [];
        foreach ($this->rows as $row) {
            if ($row["location"] === $location) {
                $positions[] = (int) $row["location_arg"];
            }
        }
        if (!$positions) {
            return 0;
        }
        return $getMax ? max($positions) : min($positions);
    }

    function shuffle(string $location) {
        $ids = array_keys($this->getCardsInLocation($location));
        $order = range(0, max(0, count($ids) - 1));
        \shuffle($order);
        foreach ($ids as $i => $id) {
            $this->rows[$id]["location_arg"] = $order[$i];
        }
    }

    function deleteAll() {
        $this->rows = [];
        $this->next_id = 1;
    }

    function pickCard(string $location, int $player_id): ?array {
        return $this->pickCardForLocation($location, "hand", $player_id);
    }

    function pickCards(int $nbr, string $location, int $player_id): ?array {
        return $this->pickCardsForLocation($nbr, $location, "hand", $player_id);
    }

    function pickCardForLocation(string $from_location, string $to_location, int $location_arg = 0): ?array {
        $picked = $this->pickCardsForLocation(1, $from_location, $to_location, $location_arg);
        return $picked ? reset($picked) : null;
    }

    function pickCardsForLocation(
        int $nbr,
        string $from_location,
        string $to_location,
        int $location_arg = 0,
        bool $no_deck_reform = false
    ): ?array {
        $picked = [];
        foreach ($this->getCardsOnTop($nbr, $from_location) as $card) {
            $this->moveCard((int) $card["id"], $to_location, $location_arg);
            $picked[(int) $card["id"]] = $this->rows[(int) $card["id"]];
        }
        return $picked;
    }

    function getCardOnTop(string $location): ?array {
        $top = $this->getCardsOnTop(1, $location);
        return $top ? reset($top) : null;
    }

    function getCardsOnTop(int $nbr, string $location): ?array {
        $rows = array_values($this->getCardsInLocation($location));
        usort($rows, fn($a, $b) => $b["location_arg"] <=> $a["location_arg"]);
        $result = [];
        foreach (array_slice($rows, 0, $nbr) as $row) {
            $result[(int) $row["id"]] = $row;
        }
        return $result;
    }

    function reformDeckFromDiscard($from_location = "deck") {
        $discard = $this->autoreshuffle_custom[$from_location] ?? "discard";
        $this->moveAllCardsInLocation($discard, $from_location);
        $this->shuffle($from_location);
    }

    function moveCard(int $card_id, string $location, int $location_arg = 0): void {
        if (!isset($this->rows[$card_id])) {
            return;
        }
        $this->rows[$card_id]["location"] = $location;
        $this->rows[$card_id]["location_arg"] = $location_arg;
    }

    function moveCards(array $cards, string $location, int $location_arg = 0): void {
        foreach ($cards as $id) {
            $this->moveCard((int) $id, $location, $location_arg);
        }
    }

    function insertCard(int $card_id, string $location, int $location_arg): void {
        foreach ($this->rows as $id => $row) {
            if ($id !== $card_id && $row["location"] === $location && (int) $row["location_arg"] >= $location_arg) {
                $this->rows[$id]["location_arg"] = (int) $row["location_arg"] + 1;
            }
        }
        $this->moveCard($card_id, $location, $location_arg);
    }

    function insertCardOnExtremePosition(int $card_id, string $location, bool $bOnTop): void {
        $extreme = $this->getExtremePosition($bOnTop, $location);
        $this->insertCard($card_id, $location, $bOnTop ? $extreme + 1 : $extreme - 1);
    }

    function moveAllCardsInLocation(
        ?string $from_location,
        ?string $to_location,
        ?int $from_location_arg = null,
        int $to_location_arg = 0
    ): void {
        foreach ($this->rows as $id => $row) {
            if ($from_location !== null && $row["location"] !== $from_location) {
                continue;
            }
            if ($from_location_arg !== null && (int) $row["location_arg"] !== $from_location_arg) {
                continue;
            }
            $this->rows[$id]["location"] = $to_location;
            $this->rows[$id]["location_arg"] = $to_location_arg;
        }
    }

    function moveAllCardsInLocationKeepOrder(string $from_location, string $to_location): void {
        foreach ($this->rows as $id => $row) {
            if ($row["location"] === $from_location) {
                $this->rows[$id]["location"] = $to_location;
            }
        }
    }

    function getCardsInLocation(string|array $location, ?int $location_arg = null, ?string $order_by = null): array {
        $locations = is_array($location) ? $location : [$location];
        $matches = [];
        foreach ($this->rows as $id => $row) {
            if (!$this->inLocations($row, $locations)) {
                continue;
            }
            if ($location_arg !== null && (int) $row["location_arg"] !== $location_arg) {
                continue;
            }
            $matches[$id] = $row;
        }
        if ($order_by === null) {
            return $matches;
        }
        $field = str_replace("card_", "", $order_by);
        $ordered = array_values($matches);
        usort($ordered, fn($a, $b) => $a[$field] <=> $b[$field]);
        return $ordered;
    }

    function getPlayerHand(int $player_id): array {
        return $this->getCardsInLocation("hand", $player_id);
    }

    function getCard(int $card_id): ?array {
        return $this->rows[$card_id] ?? null;
    }

    function getCards(array $cards_array): array {
        $result = [];
        foreach ($cards_array as $id) {
            $id = (int) $id;
            if (isset($this->rows[$id])) {
                $result[$id] = $this->rows[$id];
            }
        }
        return $result;
    }

    function getCardsFromLocation(array $cards_array, string $location, ?int $location_arg = null): array {
        $result = [];
        foreach ($cards_array as $id) {
            $id = (int) $id;
            $row = $this->rows[$id] ?? null;
            if (!$row || $row["location"] !== $location) {
                throw new feException("card $id not in location $location");
            }
            if ($location_arg !== null && (int) $row["location_arg"] !== $location_arg) {
                throw new feException("card $id not at location_arg $location_arg");
            }
            $result[$id] = $row;
        }
        return $result;
    }

    function getCardsOfType(mixed $type, ?int $type_arg = null): array {
        return $this->ofType($type, $type_arg, null, null);
    }

    function getCardsOfTypeInLocation(mixed $type, ?int $type_arg, string $location, ?int $location_arg = null): array {
        return $this->ofType($type, $type_arg, $location, $location_arg);
    }

    private function ofType(mixed $type, ?int $type_arg, ?string $location, ?int $location_arg): array {
        $result = [];
        foreach ($this->rows as $id => $row) {
            if ((string) $row["type"] !== (string) $type) {
                continue;
            }
            if ($type_arg !== null && (int) $row["type_arg"] !== $type_arg) {
                continue;
            }
            if ($location !== null && $row["location"] !== $location) {
                continue;
            }
            if ($location_arg !== null && (int) $row["location_arg"] !== $location_arg) {
                continue;
            }
            $result[$id] = $row;
        }
        return $result;
    }

    function playCard(int $card_id): void {
        $this->insertCardOnExtremePosition($card_id, "discard", true);
    }

    function countCardInLocation(string $location, ?int $location_arg = null): int|string {
        return $this->countCardsInLocation($location, $location_arg);
    }

    function countCardsInLocation(string $location, ?int $location_arg = null): int|string {
        return count($this->getCardsInLocation($location, $location_arg));
    }

    function countCardsInLocations(): array {
        $result = [];
        foreach ($this->rows as $row) {
            $result[$row["location"]] = ($result[$row["location"]] ?? 0) + 1;
        }
        return $result;
    }

    function countCardsByLocationArgs(string $location): array {
        $result = [];
        foreach ($this->getCardsInLocation($location) as $row) {
            $result[$row["location_arg"]] = ($result[$row["location_arg"]] ?? 0) + 1;
        }
        return $result;
    }
}
