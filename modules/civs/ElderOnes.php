<?php

declare(strict_types=1);

class ElderOnes extends AbsCivilization {
    const CHOICE_RESOURCE = 1;
    const CHOICE_TAPESTRY = 2;
    const TRADE_MAX = 6;
    const LANDMARK_VP = 10;

    public function __construct(object $game) {
        parent::__construct(CIV_ELDER_ONES, $game);
    }

    function hasExtendedPlay(): bool {
        return true;
    }

    /** The card says so: no trap or other response card once income turn 5 is over. */
    function playsResponseCards(): bool {
        return false;
    }

    /**
     * Turns 2-4 are the civ ability state, turn 5 is not: the trade is a bonus row, so it uses the
     * hand selection and the Decline button the bonus state already has.
     */
    function queueEraCivAbility($player_id, $incomeTurn = 0) {
        if (!$incomeTurn) {
            $incomeTurn = $this->game->getCurrentEra($player_id);
        }
        if ($incomeTurn == 5) {
            $gain = BE_ANYRES . "," . BE_ANYRES;
            $this->game->queueBonus(BE_TAPESTRY, -self::TRADE_MAX, $gain, ORDER_NORMAL, $player_id);
            return;
        }
        parent::queueEraCivAbility($player_id, $incomeTurn);
    }

    /** A choose-one row cannot hold the two card option, so both options are civ state buttons. */
    function argCivAbilitySingle($player_id, $benefit) {
        $data = $benefit;
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
        $data["title"] = clienttranslate("Choose one of these options");
        $data["slots_choice"] = [
            self::CHOICE_RESOURCE => ["benefit" => [BE_ANYRES]],
            self::CHOICE_TAPESTRY => ["benefit" => [BE_TAPESTRY, BE_TAPESTRY]],
        ];
        return $data;
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $civ = $this->civ;
        $game = $this->game;
        $this->systemAssertTrue("ERR:ElderOnes:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:ElderOnes:12", $game->hasCiv($player_id, $civ));
        $this->systemAssertTrue("ERR:ElderOnes:13", $spot == self::CHOICE_RESOURCE || $spot == self::CHOICE_TAPESTRY);

        $queue = $spot == self::CHOICE_RESOURCE ? [BE_ANYRES] : [BE_TAPESTRY, BE_TAPESTRY];
        $game->queueBenefitNormal($queue, $player_id, reason_civ($civ));
    }

    function onGainLandmark(int $player_id, int $landmark_type): void {
        if ($this->game->isExtendedPlay($player_id)) {
            $this->game->awardVP($player_id, self::LANDMARK_VP, reason_civ($this->civ));
        }
    }
}
