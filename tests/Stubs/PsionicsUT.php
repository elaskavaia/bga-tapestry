<?php

declare(strict_types=1);

require_once __DIR__ . "/DiceUT.php";

/**
 * The die and card seams a civilization that samples a second reality hooks into, and the mat's own
 * income table (stage 2). It hands out a real civ card so the seam's own predicate is what the cases
 * exercise, and reads the real CIV_PSIONICS material entry rather than stubbing it.
 */
class PsionicsUT extends DiceUT {
    /** A seat outside the player roster, which is what isRealPlayer separates a bot out by. */
    const BOT = 99;

    /** Card ids upgradeTechCard was asked for; the real one reads the card back with raw SQL the harness does not model. */
    public array $upgraded = [];

    function upgradeTechCard($card_id, $inventors = false) {
        $this->upgraded[] = (int) $card_id;
    }

    function sample(int $player_id = self::ROLLER): void {
        $this->giveCiv($player_id, CIV_PSIONICS);
    }

    /** The income row for the turn and the benefit manager resolving it, the way a real income turn reaches it. */
    function useIncomeAbility(int $turn, int $player_id = self::ROLLER): void {
        $this->startIncomeTurn($player_id, $turn);
        $this->queueEraCivAbility(CIV_PSIONICS, $player_id, $turn);
        $this->runManager();
    }

    /** Take the civ away again, for the cases that pin what a later plain roll leaves behind. */
    function stopSampling(int $player_id = self::ROLLER): void {
        foreach (array_keys($this->getCardsSearch(CARD_CIVILIZATION, CIV_PSIONICS, "hand", $player_id)) as $card_id) {
            $this->cards->setLocation((int) $card_id, "discard");
        }
    }

    /** Cards for a draw to find; with an empty deck the draw is announced as void instead. */
    function fillDeck(int $card_type, int $count, int $type_arg = 1): void {
        $deck = $this->card_types[$card_type]["deck"];
        for ($i = 0; $i < $count; $i++) {
            $this->addCard($card_type, $deck, 0, $type_arg);
        }
    }

    /** [player_id, label] of every pending row in pop order; a composite row shows its category. */
    function rows(): array {
        return array_map(
            fn($row) => [
                (int) $row["benefit_player_id"],
                $row["benefit_category"] == "standard" ? (int) $row["benefit_type"] : $row["benefit_category"],
            ],
            $this->benefitQueue()
        );
    }

    /** Card ids of one type sitting in the draw area of a player deciding what to keep. */
    function drawn(int $card_type, int $player_id = self::ROLLER): array {
        return array_keys($this->getCardsSearch($card_type, null, "draw", $player_id));
    }
}
